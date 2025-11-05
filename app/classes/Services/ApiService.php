<?php

namespace App\Services;

use Exception;
use Throwable;
use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use GuzzleHttp\RequestOptions;
use App\Utilities\LoggerUtility;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ServerRequestInterface;

final class ApiService
{
    protected ?Client $client = null;
    protected int $maxRetries;
    protected int $delayMultiplier;
    protected float $jitterFactor;
    protected int $maxRetryDelay;
    protected ?string $bearerToken = null;
    protected array $headers = [];

    protected CommonService $commonService;

    public function __construct(CommonService $commonService, ?Client $client = null, int $maxRetries = 3, int $delayMultiplier = 1000, float $jitterFactor = 0.2, int $maxRetryDelay = 10000)
    {
        $this->maxRetries = $maxRetries;
        $this->delayMultiplier = $delayMultiplier;
        $this->jitterFactor = $jitterFactor;
        $this->maxRetryDelay = $maxRetryDelay;
        $this->commonService = $commonService;

        // Use the injected client if provided, or create a new one
        $this->client = $client ?? $this->createApiClient();

        // Set default headers
        $this->headers = [
            'X-Instance-ID' => $this->commonService->getInstanceId(),
            'X-Requestor-Version' => VERSION ?? $this->commonService->getAppVersion()
        ];
    }

    private function logError(Throwable $e, string $message): void
    {
        LoggerUtility::logError("$message: " . $e->getMessage(), [
            'exception' => $e,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'stacktrace' => $e->getTraceAsString()
        ]);
    }

    public function setHeaders(array $headers): void
    {
        $this->headers = array_merge($this->headers, $headers);
    }

    public function setBearerToken(string $bearerToken): void
    {
        $this->bearerToken = $bearerToken;
    }

    public static function generateAuthToken(string $prefix = 'at', $format = '%s_%s'): string
    {
        return sprintf(
            $format,
            $prefix,
            str_replace('-', '', MiscUtility::generateUUID())
        );
    }

    protected function createApiClient(): Client
    {
        $handlerStack = HandlerStack::create();
        $handlerStack->push(Middleware::retry(
            $this->retryDecider(),
            $this->retryDelay()
        ));

        return new Client([
            'handler'        => $handlerStack,
            'connect_timeout' => 10,
            'read_timeout'   => 120,
            'timeout'        => 120,
            'decode_content' => true, // auto-decodes gzip/br when libcurl supports it
            // 'http_errors'  => false, // enable for no exceptions on 4xx/5xx
        ]);
    }


    private function retryDecider()
    {
        return function ($retries, $request, $response, $exception) {
            if ($retries >= $this->maxRetries) {
                return false;
            }
            if ($exception instanceof RequestException) {
                if ($response) {
                    $statusCode = $response->getStatusCode();
                    // Retry on server errors (5xx) or rate limiting errors (429)
                    return $statusCode >= 500 || $statusCode === 429;
                }
                return true;
            }
            return false;
        };
    }

    private function retryDelay()
    {
        return function ($retries) {
            $delay = $this->delayMultiplier * (2 ** $retries);
            $jitter = random_int(0, (int)($this->jitterFactor * 1000)) / 1000;
            return min($this->maxRetryDelay, $delay * (1 + $jitter));
        };
    }


    public function checkConnectivity(string $url): bool
    {
        try {
            $headers = [
                'X-Request-ID' => MiscUtility::generateULID(),
                'X-Timestamp' => time()
            ];
            $headers = !empty($this->headers) ? array_merge($headers, $this->headers) : $headers;
            if (!empty($this->bearerToken)) $headers['Authorization'] = "Bearer $this->bearerToken";

            try {
                $res = $this->client->head($url, [RequestOptions::HEADERS => $headers]);
                return $res->getStatusCode() === 200;
            } catch (Throwable $e) {
                $res = $this->client->get($url, [RequestOptions::HEADERS => $headers]);
                return $res->getStatusCode() === 200;
            }
        } catch (Throwable $e) {
            LoggerUtility::logError("Unable to connect to $url: " . $e->getMessage(), [ /* … */]);
            return false;
        }
    }


    public function post($url, $payload, $gzip = false, $returnWithStatusCode = false, $async = false): array|string|null|\GuzzleHttp\Promise\PromiseInterface
    {
        $options = [
            RequestOptions::HEADERS => [
                'X-Request-ID'        => MiscUtility::generateULID(),
                'X-Timestamp'         => time(),
                'Content-Type'        => 'application/json; charset=utf-8',
            ],
        ];

        if (!empty($this->headers)) {
            $options[RequestOptions::HEADERS] = array_merge($options[RequestOptions::HEADERS], $this->headers);
        }

        // Add Authorization header if a bearer token is provided
        if (!empty($this->bearerToken) && $this->bearerToken != '') {
            $options[RequestOptions::HEADERS]['Authorization'] = "Bearer $this->bearerToken";
        }

        $returnPayload = null;
        try {
            // Ensure payload is JSON-encoded
            $payload = JsonUtility::isJSON($payload) ? $payload : JsonUtility::encodeUtf8Json($payload);

            // 1 KB threshold; can tune if needed
            if ($gzip && strlen($payload) > 1024 && !str_starts_with($payload, "\x1f\x8b")) {
                $payload = gzencode($payload);
                $options[RequestOptions::HEADERS]['Content-Encoding'] = 'gzip';
            }

            $options[RequestOptions::EXPECT] = false;
            $options[RequestOptions::HEADERS]['Accept'] = 'application/json';

            // Set the request body
            $options[RequestOptions::BODY] = $payload;

            if ($async) {
                // Perform an asynchronous request
                $returnPayload = $this->client->postAsync($url, $options);
            } else {
                // Synchronous request
                $response     = $this->client->post($url, $options);
                $responseBody = $response->getBody()->getContents();
                if ($returnWithStatusCode) {
                    $returnPayload = [
                        'httpStatusCode' => $response->getStatusCode(),
                        'body'           => $responseBody,
                    ];
                } else {
                    $returnPayload = $responseBody;
                }
            }
        } catch (RequestException $e) {
            // Handle request exceptions
            $responseBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null;
            $this->logError($e, "Unable to post to $url. Server responded with: " . ($responseBody ?? 'No response body'));

            if ($returnWithStatusCode) {
                $returnPayload = [
                    'httpStatusCode' => $e->getResponse() ? $e->getResponse()->getStatusCode() : 500,
                    'body'           => $responseBody,
                ];
            } else {
                $returnPayload = $responseBody;
            }
        } catch (Throwable $e) {
            // Log other errors
            $this->logError($e, "Unable to post to $url");
            $returnPayload = null;
        }

        return $returnPayload;
    }


    public function postFile($url, $fileName, $jsonFilePath, $params = [], $gzip = true): ?string
    {
        // Prepare multipart data
        $multipartData = [];

        try {

            if ($gzip) {
                $fileContents = gzencode(file_get_contents($jsonFilePath));
            } else {
                $fileContents = fopen($jsonFilePath, 'r');
            }

            // Prepare file content for multipart
            $multipartData = [
                [
                    'name'     => $fileName,
                    'contents' => $fileContents,                          // gzencoded bytes
                    'filename' => basename($jsonFilePath) . ($gzip ? '.gz' : ''),
                    'headers'  => [
                        'Content-Type'     => 'application/json',
                        'Content-Encoding' => $gzip ? 'gzip' : 'identity',
                    ],
                ],
            ];

            // Add additional parameters to multipart data
            // Check if params are in ['name' => 'x','contents' => 'y'] format or associative array
            if (isset($params[0]['contents']) && isset($params[0]['name'])) {
                // Params are in the ['name' => 'x','contents' => 'y'] format, merge them directly
                $multipartData = array_merge($multipartData, $params);
            } else {
                // Params are in associative array format, handle them as key-value pairs
                foreach ($params as $name => $value) {
                    $multipartData[] = [
                        'name' => $name,
                        'contents' => $value
                    ];
                }
            }
            // Prepare headers
            $headers = [
                'X-Timestamp'    => time(),
                'X-Request-ID'    => MiscUtility::generateULID()
            ];

            // Initialize the options array for multipart form data
            $options = [
                RequestOptions::MULTIPART => $multipartData,
                RequestOptions::HEADERS   => $headers
            ];

            if (!empty($this->headers)) {
                $options[RequestOptions::HEADERS] = array_merge($options[RequestOptions::HEADERS], $this->headers);
            }

            // Add Authorization header if a bearer token is provided
            if (!empty($this->bearerToken) && $this->bearerToken != '') {
                $options[RequestOptions::HEADERS]['Authorization'] = "Bearer $this->bearerToken";
            }

            // Send the request
            $response = $this->client->post($url, $options);

            $apiResponse = $response->getBody()->getContents();
        } catch (RequestException $e) {
            // Extract the response body from the exception, if available
            $responseBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null;
            $errorCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 500;
            // Log the error along with the response body
            $this->logError($e, "Unable to post to $url. Server responded with $errorCode : " . ($responseBody ?? 'No response body'));

            $apiResponse = $responseBody ?? null;
        } catch (Throwable $e) {
            $this->logError($e, "Unable to post to $url");
            $apiResponse = null; // Error occurred while making the request
        }
        return $apiResponse;
    }

    public function getJsonFromRequest(ServerRequestInterface $request, bool $decode = false)
    {
        try {
            $encoding = strtolower(trim($request->getHeaderLine('Content-Encoding')));
            $body     = (string)$request->getBody(); // reads full stream; OK for your sizes

            // normalize common tokens
            if (str_contains($encoding, 'gzip') || str_contains($encoding, 'application/gzip')) {
                $decoded = @gzdecode($body);
                if ($decoded !== false) {
                    $body = $decoded;
                } else {
                    LoggerUtility::logError('Gzip decompression failed; treating as raw JSON');
                }
            } elseif (str_contains($encoding, 'deflate') || str_contains($encoding, 'application/deflate')) {
                // try both possibilities (zlib-wrapped vs raw)
                $decoded = @gzuncompress($body);
                if ($decoded === false) {
                    $decoded = @gzinflate($body);
                }
                if ($decoded !== false) {
                    $body = $decoded;
                } else {
                    LoggerUtility::logError('Deflate decompression failed; treating as raw JSON');
                }
            }

            return $decode ? _sanitizeJson($body, true, true) : _sanitizeJson($body);
        } catch (Throwable $e) {
            $this->logError($e, "Unable to retrieve json");
            return $decode ? [] : '{}';
        }
    }


    /**
     * Download a file from a given URL and save it to a specified path.
     *
     * @param string $fileUrl The URL of the file to download.
     * @param string $downloadPath The local path with filename where the file should be saved.
     * @param array $allowedFileTypes An array of allowed file types.
     * @param string $safePath The base path to ensure the download path is within the allowed directory.
     * @return bool Returns true on successful download, false otherwise.
     */
    public function downloadFile(string $fileUrl, string $downloadPath, array $allowedFileTypes = [], $safePath = ROOT_PATH): int|bool
    {

        // Validate the URL
        if (!filter_var($fileUrl, FILTER_VALIDATE_URL)) {
            $this->logError(new Exception("Invalid URL"), "Invalid URL provided for downloading");
            return false;
        }
        $downloadFolder = dirname($downloadPath);
        $fileName = basename($downloadPath);
        // Check if $fileName is null or empty
        if (empty($fileName)) {
            $fileName = basename($fileUrl);
        }
        // Normalize the safePath and downloadPath to ensure both are absolute and resolved
        $resolvedSafePath = realpath($safePath);
        $resolvedDownloadPath = realpath($downloadFolder);

        // If realpath returns false, the path does not exist
        if (!$resolvedDownloadPath) {
            // Try creating the directory or handling the error as needed
            if (!MiscUtility::makeDirectory($downloadFolder) && !is_dir($downloadFolder)) {
                $this->logError(new Exception("Invalid path"), "The download path cannot be created or does not exist");
                return false;
            }
            $resolvedDownloadPath = realpath($downloadFolder);
        }

        // Ensure the downloadPath starts with the resolved safePath
        if ($resolvedDownloadPath === false || !str_starts_with($resolvedDownloadPath, $resolvedSafePath)) {
            $this->logError(new Exception("Invalid path"), "The download path is not within the allowed directory");
            return false;
        }

        try {
            // Use Guzzle to download the file
            $response = $this->client->request('GET', $fileUrl, ['stream' => true]);

            // Validate response status
            if ($response->getStatusCode() !== 200) {
                $this->logError(new Exception("HTTP error " . $response->getStatusCode()), "Failed to download file from $fileUrl");
                return false;
            }

            // Check MIME type if allowed file types are specified
            if (!empty($allowedFileTypes)) {
                $contentType = $response->getHeaderLine('Content-Type');
                $allowedMimeTypes = MiscUtility::getMimeTypeStrings($allowedFileTypes);
                if (!in_array($contentType, $allowedMimeTypes)) {
                    $this->logError(new Exception("Invalid file type"), "The file type '$contentType' is not allowed.");
                    return false;
                }
            }

            // Save the file
            $fileResource = fopen($resolvedDownloadPath . DIRECTORY_SEPARATOR . $fileName, 'wb');
            if ($fileResource === false || stream_copy_to_stream($response->getBody()->detach(), $fileResource) === false) {
                if ($fileResource !== false) {
                    fclose($fileResource);
                }
                $this->logError(new Exception("Failed to save file"), "Unable to save the downloaded file.");
                return false;
            }
            fclose($fileResource);
            return true;
        } catch (Throwable $e) {
            $this->logError($e, "Unable to download file from $fileUrl");
            return false;
        }
    }

    public static function generateJsonResponse(mixed $payload, ServerRequestInterface $request): string
    {
        // Always go through JsonUtility so UTF-8 normalization and validation apply
        $jsonPayload = JsonUtility::encodeUtf8Json($payload);

        if ($jsonPayload === null) {
            // We failed to encode or validate JSON on the server side -> 500
            http_response_code(500);
            LoggerUtility::logError('JSON encoding failed in generateJsonResponse');
            $jsonPayload = '{"error":"JSON encoding failed"}';
        }

        // Propagate request id for tracing
        $reqId = $request->getHeaderLine('X-Request-ID');
        if ($reqId !== '') {
            header("X-Request-ID: $reqId");
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        return $jsonPayload; // caller echoes
    }





    /**
     * Retrieves the bearer token from the Authorization header using ServerRequestInterface.
     *
     * @param ServerRequestInterface $request The request object.
     * @return string|null Returns the bearer token if present, otherwise null.
     */
    public static function extractBearerToken(ServerRequestInterface $request): ?string
    {
        $authorization = $request->getHeaderLine('Authorization');
        if (preg_match('/bearer\s+(\S+)/i', $authorization, $matches)) {
            return $matches[1];
        }

        return null;
    }


    /**
     * Retrieves a specific header value or values from the request.
     *
     * @param ServerRequestInterface $request The request object.
     * @param string $key The header key to retrieve.
     * @return string|array|null Returns the header values as a single string if concatenated, an array if multiple, or null if not present.
     */
    public function getHeader(ServerRequestInterface $request, string $key)
    {
        $headerValues = $request->getHeader($key);
        if (empty($headerValues)) {
            return null;
        } elseif (count($headerValues) === 1) {
            return $headerValues[0];
        } else {
            return $headerValues;
        }
    }
}
