<?php


namespace Vlsm\Interop;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class Fhir
{
	private string $fhirURL;
	private string $bearerToken;
	private string $contentType;
	private bool $authenticated;


	public function __construct($fhirURL, $bearerToken, $contentType = 'application/fhir+json')
	{
		$this->fhirURL = $fhirURL;
		$this->bearerToken = $bearerToken;
		$this->contentType = $contentType;
	}

	public function isAuthenticated(): bool
	{
		return $this->authenticated;
	}

	// Used to specify content type
	// can be 'application/xml' or 'application/json'
	public function setContentType($contentType): void
	{
		$this->contentType = $contentType;
	}

	protected function getBearerToken(): string
	{
		return $this->bearerToken;
	}

	// Get content type
	public function getContentType(): string
	{
		return $this->contentType;
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

	public function get($path, $urlParams = array())
	{

		if (empty($path)) return false;

		if (!empty($urlParams)) {
			$urlParams = '?' . implode("&", $urlParams);
		} else {
			$urlParams = "";
		}

		$url = $this->getFhirURL() . "{$path}{$urlParams}";

		$client = new Client();
		$headers = [
			'Content-Type' => $this->getContentType(),
			'Authorization' => $this->getBearerToken()
		];
		$request = new Request('GET', $url, $headers);
		$res = $client->sendAsync($request)->wait();
		return $res->getBody()->getContents();
	}
}
