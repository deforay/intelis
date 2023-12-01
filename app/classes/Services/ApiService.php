<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use GuzzleHttp\Exception\RequestException;

class ApiService
{

    protected ?DatabaseService $db = null;
    protected ?Client $client = null;

    public function __construct($db = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
        $this->client = $this->createApiClient();
    }

    protected function createApiClient(): Client
    {
        $handlerStack = HandlerStack::create();

        // Add retry middleware with exponential backoff
        $handlerStack->push(Middleware::retry(function ($retries, $request, $response = null, $exception = null) {
            // Retry up to 3 times
            return $retries < 3 && ($exception instanceof RequestException);
        }, function ($retries) {
            return 1000 * 2 ** $retries; // Exponential backoff with a base delay of 1 second
        }));

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
        } catch (RequestException | Exception $e) {
            error_log($e->getMessage());
            return false; // Error occurred while making the request
        }
    }

    public function post($url, $payload, $gzip = true): string
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
        } catch (RequestException | Exception $e) {
            error_log($e->getMessage());
            return null; // Error occurred while making the request
        }
    }

    public function postFile($url, $fileName, $jsonFilePath, $params = [], $gzip = false): string
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
                    'headers' => ['Content-Encoding' => 'gzip']
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
        } catch (RequestException | Exception $e) {
            error_log($e->getMessage());
            return null; // Error occurred while making the request
        }
    }

    public function getJsonFromRequest($request, $decode = false)
    {
        $response = null;
        try {
            // Get the content encoding header to check for gzip
            $contentEncoding = $request->getHeaderLine('Content-Encoding');

            // Read the JSON response from the input
            $jsonData = $request->getBody()->getContents();

            // Check if the data might already be decompressed
            if ($contentEncoding !== 'gzip') {
                $response = $jsonData; // Return raw JSON data
            } else {
                // Attempt gzip decompression
                $decompressedData = gzdecode($jsonData);
                if ($decompressedData === false) {
                    // Handle decompression failure
                    $response = "[]";
                } else {
                    // Check if the data is valid UTF-8, convert if not
                    if (!mb_check_encoding($decompressedData, 'UTF-8')) {
                        $decompressedData = mb_convert_encoding($decompressedData, 'UTF-8', 'auto');
                    }
                    // Return decompressed JSON data
                    $response = $decompressedData;
                }
            }
            if ($decode) {
                $response = json_decode((string) $response, true);
            }
            return $response;
        } catch (Exception $e) {
            throw new SystemException("Unable to retrieve json : " . $e->getMessage(), 500);
        }
    }
}
