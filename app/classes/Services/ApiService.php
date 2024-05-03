<?php

namespace App\Services;

use Exception;
use Throwable;
use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use App\Utilities\MiscUtility;
use GuzzleHttp\RequestOptions;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ServerRequestInterface;

final class ApiService
{
    protected ?Client $client = null;
    protected int $maxRetries;
    protected int $delayMultiplier;
    protected float $jitterFactor;
    protected int $maxRetryDelay;

    public function __construct(int $maxRetries = 3, int $delayMultiplier = 1000, float $jitterFactor = 0.2, int $maxRetryDelay = 10000)
    {
        $this->maxRetries = $maxRetries;
        $this->delayMultiplier = $delayMultiplier;
        $this->jitterFactor = $jitterFactor;
        $this->maxRetryDelay = $maxRetryDelay;
        $this->client = $this->createApiClient();
    }

    private function logError(Throwable $e, string $message): void
    {
        LoggerUtility::log('error', "$message: " . $e->getMessage(), [
            'exception' => $e,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'stacktrace' => $e->getTraceAsString()
        ]);
    }

    public static function generateAuthToken(): string
    {
        return base64_encode(MiscUtility::generateUUID() . "-" . MiscUtility::generateRandomString(32));
    }

    protected function createApiClient(): Client
    {
        $handlerStack = HandlerStack::create();
        $handlerStack->push(Middleware::retry(
            $this->retryDecider(),
            $this->retryDelay()
        ));

        return new Client(['handler' => $handlerStack]);
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
            $jitter = $this->jitterFactor * random_int(0, 1000) / 1000;
            return min($this->maxRetryDelay, $delay * (1 + $jitter));
        };
    }


    public function checkConnectivity(string $url): bool
    {
        try {
            $response = $this->client->get($url);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 200) {
                return true; // Successful response
            } else {
                return false; // API returned a non-200 status code
            }
        } catch (Throwable $e) {
            LoggerUtility::log('error', "Unable to connect to $url: " . $e->getMessage(), [
                'exception' => $e,
                'file' => $e->getFile(), // File where the error occurred
                'line' => $e->getLine(), // Line number of the error
                'stacktrace' => $e->getTraceAsString()
            ]);
            return false; // Error occurred while making the request
        }
    }

    public function post($url, $payload, $gzip = true): string|null
    {
        $options = [
            RequestOptions::HEADERS => ['Content-Type' => 'application/json']
        ];
        try {
            if ($gzip) {
                $compressedPayload = gzencode(json_encode($payload));
                $options[RequestOptions::BODY] = $compressedPayload;
                $options[RequestOptions::HEADERS]['Content-Encoding'] = 'gzip';
                $options[RequestOptions::HEADERS]['Accept-Encoding'] = 'gzip, deflate';
            } else {
                $options[RequestOptions::JSON] = $payload;
            }

            $response = $this->client->post($url, $options);

            return $response->getBody()->getContents();
        } catch (Throwable $e) {
            $this->logError($e, "Unable to post to $url");
            return null; // Error occurred while making the request
        }
    }

    public function postFile($url, $fileName, $jsonFilePath, $params = [], $gzip = true): string|null
    {
        // Prepare multipart data
        $multipartData = [];

        try {
            // File content handling
            if ($gzip) {
                // GZip the file content and add to multipart data
                $multipartData[] = [
                    'name' => $fileName,
                    'contents' => gzencode(file_get_contents($jsonFilePath)),
                    'filename' => basename((string) $jsonFilePath) . '.gz', // adding .gz to indicate gzip
                ];
            } else {
                // Add regular file content to multipart data
                $multipartData[] = [
                    'name' => $fileName,
                    'contents' => fopen($jsonFilePath, 'r'),
                    'filename' => basename((string) $jsonFilePath)
                ];
            }

            // Add additional parameters to multipart data
            foreach ($params as $param) {
                $multipartData[] = $param;
            }

            // Initialize the options array for multipart form data
            $options = [
                RequestOptions::MULTIPART => $multipartData
            ];

            // Send the request
            $response = $this->client->post($url, $options);

            return $response->getBody()->getContents();
        } catch (Throwable $e) {
            $this->logError($e, "Unable to post to $url");
            return null; // Error occurred while making the request
        }
    }

    public function getJsonFromRequest(ServerRequestInterface $request, bool $decode = false)
    {
        try {
            $contentEncoding = $request->getHeaderLine('Content-Encoding');
            $jsonData = (string) $request->getBody();

            if ($contentEncoding === 'gzip') {
                $jsonData = gzdecode($jsonData) ?: throw new SystemException("Decompression failed");
            }

            // if (!mb_check_encoding($jsonData, 'UTF-8')) {
            //     $jsonData = mb_convert_encoding($jsonData, 'UTF-8', 'auto');
            // }

            if ($decode) {
                $decodedJson = json_decode($jsonData, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new SystemException("JSON decoding error: " . json_last_error_msg());
                }
                return $decodedJson;
            }

            return $jsonData;
        } catch (Throwable $e) {
            $this->logError($e, "Unable to retrieve json");
            return null;
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

    public function sendJsonResponse(mixed $payload)
    {
        // Ensure payload is a JSON string
        $jsonPayload = is_array($payload) || is_object($payload) ? json_encode($payload) : $payload;

        // Check for json_encode errors
        if (json_last_error() != JSON_ERROR_NONE) {
            // Handle the error, maybe log it or set an error message
            return null;
        }

        // Gzip compress, assuming $jsonPayload is never null
        $gzipPayload = gzencode($jsonPayload);

        // Check for gzencode errors
        if ($gzipPayload === false) {
            // Handle the error
            return null;
        }

        header('Content-Encoding: gzip');
        header('Content-Length: ' . mb_strlen($gzipPayload, '8bit'));
        return $gzipPayload;
    }

    /**
     * Retrieves the bearer token from the Authorization header using ServerRequestInterface.
     *
     * @param ServerRequestInterface $request The request object.
     * @return string|null Returns the bearer token if present, otherwise null.
     */
    public function getAuthorizationBearerToken(ServerRequestInterface $request): ?string
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
        } else if (count($headerValues) === 1) {
            return $headerValues[0];
        } else {
            return $headerValues;
        }
    }
}
