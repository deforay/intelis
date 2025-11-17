<?php

namespace App\Interop;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class Fhir
{
	public string $requestUrl;
	public bool $authenticated;


	public function __construct(private readonly string $fhirURL, private readonly string $bearerToken, private string $contentType = 'application/fhir+json')
 {
 }

	public function isAuthenticated(): bool
	{
		return $this->authenticated;
	}

	// Used to specify content type
	// can be 'application/xml' or 'application/json'
	public function setContentType(string $contentType): void
	{
		$this->contentType = $contentType;
	}

	protected function getBearerToken(): string
	{
		return $this->bearerToken ?: "";
	}

	// Get content type
	public function getContentType(): string
	{
		return $this->contentType ?: 'application/fhir+json';
	}

	// Get FHIR URL without trailing slash
	public function getFhirURL(): string
	{
		return rtrim($this->fhirURL, "/");
	}

	public function getFHIRReference($referencePath)
	{
		$client = new Client();
		$headers = [
			'Content-Type' => $this->getContentType(),
			'Authorization' => $this->getBearerToken()
		];
		$request = new Request('GET', $this->getFhirURL() . "/$referencePath", $headers);
		$res = $client->sendAsync($request)->wait();
		return $res->getBody()->getContents();
	}

	public function get(?string $path, $urlParams = [])
	{

		if ($path === null || $path === '' || $path === '0') {
			return false;
		}

		$urlParams = empty($urlParams) ? "" : '?' . implode("&", $urlParams);

		$this->requestUrl = $this->getFhirURL() . $path . $urlParams;

		$client = new Client();
		$headers = [
			'Content-Type' => $this->getContentType(),
			'Authorization' => $this->getBearerToken()
		];

		$request = new Request('GET', $this->requestUrl, $headers);
		$res = $client->sendAsync($request)->wait();
		return $res->getBody()->getContents();
	}

	public function getRequestUrl(): string
	{
		return $this->requestUrl ?: "";
	}

	public function post($path = null, $body = [])
	{

		$client = new Client();
		$headers = [
			'Content-Type' => $this->getContentType(),
			'Authorization' => $this->getBearerToken()
		];

		$this->requestUrl = $this->getFhirURL() . $path;

		$request = new Request('POST', $this->requestUrl, $headers, $body);
		$res = $client->sendAsync($request)->wait();

		return $res->getBody()->getContents();
	}
}
