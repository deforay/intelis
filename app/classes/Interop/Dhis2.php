<?php

namespace App\Interop;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use App\Utilities\LoggerUtility;
use GuzzleHttp\Exception\GuzzleException;

class Dhis2
{
	private const string DEFAULT_CONTENT_TYPE = 'application/json';
	private readonly Client $httpClient;
	private bool $authenticated = false;

	public function __construct(public string $currentRequestUrl, string $username, string $password, private string $contentType = self::DEFAULT_CONTENT_TYPE)
	{
		$this->httpClient = new Client([
			'base_uri' => rtrim($this->currentRequestUrl, '/'),
			'auth' => [$username, $password],
			'headers' => ['Content-Type' => $this->contentType]
		]);

		try {
			$response = $this->httpClient->get('/api/33/system/ping');
			$this->authenticated = $response->getStatusCode() === 200;
		} catch (GuzzleException) {
			$this->authenticated = false;
		}
	}

	public function isAuthenticated(): bool
	{
		return $this->authenticated;
	}

	public function setContentType(string $contentType): void
	{
		$this->contentType = $contentType;
	}

	public function getContentType(): string
	{
		return $this->contentType;
	}


	// Returns all orgs if $orgUnitID is not specified
	public function getOrgUnits($orgUnitID = null): false|Response|null
	{
		if (!$this->isAuthenticated()) {
      return false;
  }

		$urlParams[] = "paging=false";

		$path = $orgUnitID == null ? "/api/organisationUnits" : "/api/organisationUnits/" . $orgUnitID;


		return $this->get($path, $urlParams);
	}

	// Returns all programs if programId is not specified
	public function getPrograms($orgUnitID, $programId = null): false|Response|null
	{
		if (!$this->isAuthenticated() || empty($orgUnitID)) {
      return false;
  }

		$urlParams[] = "paging=false";

		if ($programId == null) {
			$urlParams[] = "fields=programs[:all]";
			$path = "/api/organisationUnits/$orgUnitID";
		} else {
			$path = "/api/$orgUnitID/programs/$programId";
		}

		return $this->get($path, $urlParams);
	}

	//Get all data sets for specified orgUnit
	public function getDataSets($orgUnitID, $dataSetId = ""): false|Response|null
	{

		if (!$this->isAuthenticated() || empty($orgUnitID)) {
      return false;
  }


		$urlParams[] = "paging=false";

		if ($dataSetId == null) {
			$urlParams[] = "fields=dataSets[:all]";
			$path = "/api/organisationUnits/$orgUnitID";
		} else {
			$path = "/api/$orgUnitID/dataSets/$dataSetId";
		}

		return $this->get($path, $urlParams);
	}

	//Get all data set elements for specified data set
	public function getDataElements(?string $dataSetID): false|Response|null
	{

		if (!$this->isAuthenticated() || ($dataSetID === null || $dataSetID === '' || $dataSetID === '0')) {
      return false;
  }

		$urlParams[] = "paging=false";
		$urlParams[] = "filter=dataSetElements.dataSet.id:eq:" . $dataSetID;
		$path = "/api/dataElements";

		return $this->get($path, $urlParams);
	}

	public function getCurrentRequestUrl(): string
	{
		return $this->currentRequestUrl;
	}

	//Get all data set elements combo for specified data set element
	public function getDataElementsCombo($dataElementID): false|Response|null
	{

		if (!$this->isAuthenticated() || empty($dataElementID)) {
			return false;
		}

		$urlParams[] = "paging=false";
		$urlParams[] = "fields=categoryCombo[:all,categoryOptionCombos[:all]]";
		$path = "/api/dataElements/$dataElementID";

		return $this->get($path, $urlParams);
	}

	public function sendDataValueSets($orgUnitId, $dataSetId, $period, $completeDate, $dataValues): ?Response
	{

		if (!empty($orgUnitId)) {
			$data['orgUnit'] = $orgUnitId;
		}
		if (!empty($dataSetId)) {
			$data['dataSet'] = $dataSetId;
		}
		if (!empty($completeDate)) {
			$data['completeDate'] = $completeDate;
		}
		if (!empty($period)) {
			$data['period'] = $period;
		}

		$data['dataValues'] = $dataValues;

		return $this->post("/api/dataValueSets", $data);
	}


	// Get data value sets from Dhis2
	public function getDataValueSets($orgUnitId, $dataSetId, $period = null, $startDate = null, $endDate = null): false|Response|null
	{

		if (empty($orgUnitId) || empty($dataSetId)) {
			return false;
		}

		$urlParams[] = "dataSet=$dataSetId";
		$urlParams[] = "orgUnit=$orgUnitId";

		if (!empty($startDate) && !empty($endDate)) {
      $urlParams[] = "startDate=$startDate";
      $urlParams[] = "endDate=$endDate";
  } elseif (!empty($period)) {
      $urlParams[] = "period=$period";
  } else {
			// Either period or startDate/endDate need to be present
			return false;
		}

		return $this->get("/api/dataValueSets", $urlParams);
	}

	// Send GET request to DHIS2
	public function get(string $path, array $urlParams = []): ?Response
	{
		$queryString = $urlParams === [] ? '' : '?' . implode('&', $urlParams);

		try {
			return $this->httpClient->get($this->currentRequestUrl . $path . $queryString);
		} catch (GuzzleException $e) {
			LoggerUtility::logError($e->getMessage(), [
				'url' => $this->currentRequestUrl . $path . $queryString
			]);
			return null;
		}
	}

	// Send POST request to DHIS2
	public function post(string $path, array $data, array $urlParams = []): ?Response
	{
		if (!$this->isAuthenticated()) {
			return null;
		}

		$queryString = $urlParams === [] ? '' : '?' . implode('&', $urlParams);

		try {
			return $this->httpClient->post($this->currentRequestUrl . $path . $queryString, [
				'json' => $data
			]);
		} catch (GuzzleException $e) {
			LoggerUtility::logError($e->getMessage(), [
				'url' => $this->currentRequestUrl . $path . $queryString,
				'data' => $data
			]);
			return null;
		}
	}

	// Send PUT request to DHIS2
	public function put(string $path, array $data, array $urlParams = []): ?Response
	{
		if (!$this->isAuthenticated()) {
			return null;
		}

		$queryString = $urlParams === [] ? '' : '?' . implode('&', $urlParams);

		try {
			return $this->httpClient->put($path . $queryString, [
				'json' => $data
			]);
		} catch (GuzzleException $e) {
			LoggerUtility::logError($e->getMessage(), [
				'url' => $this->currentRequestUrl . $path . $queryString,
				'data' => $data
			]);
			return null;
		}
	}

	public function addDataValuesToEventPayload($eventPayload, $inputArray)
	{

		$dataValues = [];
		if (empty($inputArray)) {
			return $eventPayload;
		}
		foreach ($inputArray as $name => $value) {
			$dataValues[] = ["dataElement" => $name, "value" => $value, "providedElsewhere" => false];
		}

		if (!empty($eventPayload['dataValues'])) {
			$eventPayload['dataValues'] = array_merge($eventPayload['dataValues'], $dataValues);
		} else {
			$eventPayload['dataValues'] = $dataValues;
		}

		return $eventPayload;
	}
}
