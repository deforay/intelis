<?php

namespace App\Interop;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use App\Utilities\LoggerUtility;
use GuzzleHttp\Exception\GuzzleException;

class Dhis2
{
	private const DEFAULT_CONTENT_TYPE = 'application/json';
	private readonly Client $httpClient;
	private bool $authenticated = false;
	private string $contentType;
	public string $currentRequestUrl;

	public function __construct(string $dhis2url, string $username, string $password, string $contentType = self::DEFAULT_CONTENT_TYPE)
	{
		$this->currentRequestUrl = $dhis2url;
		$this->contentType = $contentType;
		$this->httpClient = new Client([
			'base_uri' => rtrim($dhis2url, '/'),
			'auth' => [$username, $password],
			'headers' => ['Content-Type' => $this->contentType]
		]);

		try {
			$response = $this->httpClient->get('/api/33/system/ping');
			$this->authenticated = $response->getStatusCode() === 200;
		} catch (GuzzleException $e) {
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
	public function getOrgUnits($orgUnitID = null)
	{
		if (!$this->isAuthenticated())
			return false;

		$urlParams[] = "paging=false";

		if ($orgUnitID == null) {
			$path = "/api/organisationUnits";
		} else {
			$path = "/api/organisationUnits/" . $orgUnitID;
		}


		return $this->get($path, $urlParams);
	}

	// Returns all programs if programId is not specified
	public function getPrograms($orgUnitID, $programId = null)
	{
		if (!$this->isAuthenticated() || empty($orgUnitID))
			return false;

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
	public function getDataSets($orgUnitID, $dataSetId = "")
	{

		if (!$this->isAuthenticated() || empty($orgUnitID))
			return false;


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
	public function getDataElements($dataSetID)
	{

		if (!$this->isAuthenticated() || empty($dataSetID))
			return false;

		$urlParams[] = "paging=false";
		$urlParams[] = "filter=dataSetElements.dataSet.id:eq:" . $dataSetID;
		$path = "/api/dataElements";

		return $this->get($path, $urlParams);
	}

	public function getCurrentRequestUrl()
	{
		return $this->currentRequestUrl;
	}

	//Get all data set elements combo for specified data set element
	public function getDataElementsCombo($dataElementID)
	{

		if (!$this->isAuthenticated() || empty($dataElementID)) {
			return false;
		}

		$urlParams[] = "paging=false";
		$urlParams[] = "fields=categoryCombo[:all,categoryOptionCombos[:all]]";
		$path = "/api/dataElements/$dataElementID";

		return $this->get($path, $urlParams);
	}

	public function sendDataValueSets($orgUnitId, $dataSetId, $period, $completeDate, $dataValues)
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
	public function getDataValueSets($orgUnitId, $dataSetId, $period = null, $startDate = null, $endDate = null)
	{

		if (empty($orgUnitId) || empty($dataSetId)) {
			return false;
		}

		$urlParams[] = "dataSet=$dataSetId";
		$urlParams[] = "orgUnit=$orgUnitId";

		if (!empty($startDate) && !empty($endDate)) {
			$urlParams[] = "startDate=$startDate";
			$urlParams[] = "endDate=$endDate";
		} else if (!empty($period)) {
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
		if (!empty($urlParams)) {
			$queryString = '?' . implode('&', $urlParams);
		} else {
			$queryString = '';
		}

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

		if (!empty($urlParams)) {
			$queryString = '?' . implode('&', $urlParams);
		} else {
			$queryString = '';
		}

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

		if (!empty($urlParams)) {
			$queryString = '?' . implode('&', $urlParams);
		} else {
			$queryString = '';
		}

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
			$dataValues[] = array(
				"dataElement" => $name,
				"value" => $value,
				"providedElsewhere" => false
			);
		}

		if (!empty($eventPayload['dataValues'])) {
			$eventPayload['dataValues'] = array_merge($eventPayload['dataValues'], $dataValues);
		} else {
			$eventPayload['dataValues'] = $dataValues;
		}

		return $eventPayload;
	}
}
