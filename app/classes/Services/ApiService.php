<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use App\Exceptions\SystemException;
use App\Utilities\LoggerUtility;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ServerRequestInterface;

class ApiService
{
    protected ?Client $client = null;
    protected int $maxRetries;
    protected int $delayMultiplier;

    public function __construct(int $maxRetries = 3, int $delayMultiplier = 1000)
    {
        $this->maxRetries = $maxRetries;
        $this->delayMultiplier = $delayMultiplier;
        $this->client = $this->createApiClient();
    }

    protected function createApiClient(): Client
    {
        $handlerStack = HandlerStack::create();
        $handlerStack->push(Middleware::retry(
            fn ($retries, $request, $response = null, $exception = null) =>
            $retries < $this->maxRetries && ($exception instanceof RequestException),
            fn ($retries) =>
            $this->delayMultiplier * 2 ** $retries
        ));

        return new Client(['handler' => $handlerStack]);
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
        } catch (GuzzleException | RequestException | Exception $e) {
            error_log($e->getMessage());
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
        } catch (GuzzleException | RequestException | Exception $e) {
            error_log($e->getMessage());
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
        } catch (GuzzleException | RequestException | Exception $e) {
            error_log($e->getMessage());
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
        } catch (\Throwable $e) {
            LoggerUtility::log('error', "Unable to retrieve json: " . $e->getMessage(), [
                'exception' => $e,
                'file' => $e->getFile(), // File where the error occurred
                'line' => $e->getLine(), // Line number of the error
                'stacktrace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }


    /**
     * Download a file from a given URL and save it to a specified path.
     *
     * @param string $url The URL of the file to download.
     * @param string $path The local path where the file should be saved.
     * @return bool Returns true on successful download, false otherwise.
     */
    public function downloadFile(string $url, string $path): int|bool
    {
        try {
            return file_put_contents($path, file_get_contents($url));
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false; // Handle any exception
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
}
