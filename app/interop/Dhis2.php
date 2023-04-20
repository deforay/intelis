<?php


namespace App\Interop;

class Dhis2
{
	private string $dhis2url;
    private string $username;
    private string $password;
    private string $contentType;
	private bool $authenticated;


	public function __construct($dhis2url, $username = "admin", $password = "district", $contentType = 'application/json')
	{
		// ensuring there is no trailing slash
		$this->dhis2url = rtrim($dhis2url, '/');

		// Dhis2 Credentials
		$this->username = $username;
		$this->password = $password;

		$this->contentType = $contentType;

		// Let us authenticate
		//$urlParams[] = "authOnly=true";
		$response = $this->get("/api/33/system/ping");
		if ($response) {
			$this->authenticated  = true;
			return $this;
		} else {
			$this->authenticated  = false;
			return false;
		}
	}

	public function isAuthenticated(): bool
    {
		return $this->authenticated;
	}

	// Used to specify content type
	// can be 'application/xml' or 'application/json'
	public function setContentType($contentType)
	{
		$this->contentType = $contentType;
	}

	// Get content type
	public function getContentType()
	{
		return $this->contentType;
	}


	// Returns all orgs if $orgUnitID is not specified
	public function getOrgUnits($orgUnitID = null)
	{
		if (!$this->authenticated) return false;

		$urlParams[] = "paging=false";

		if ($orgUnitID == null) {
			$path = "/api/organisationUnits";
		} else {
			$path =  "/api/organisationUnits/" . $orgUnitID;
		}


		return $this->get($path, $urlParams);
	}

	// Returns all programs if programId is not specified
	public function getPrograms($orgUnitID, $programId = null)
	{
		if (!$this->authenticated || empty($orgUnitID)) return false;

		$urlParams[] = "paging=false";

		if ($programId == null) {
			$urlParams[] = "fields=programs[:all]";
			$path =  "/api/organisationUnits/$orgUnitID";
		} else {
			$path =  "/api/$orgUnitID/programs/$programId";
		}

		return $this->get($path, $urlParams);
	}

	//Get all data sets for specified orgUnit
	public function getDataSets($orgUnitID, $dataSetId = "")
	{

		if (!$this->authenticated || empty($orgUnitID)) return false;


		$urlParams[] = "paging=false";

		if ($dataSetId == null) {
			$urlParams[] = "fields=dataSets[:all]";
			$path =  "/api/organisationUnits/$orgUnitID";
		} else {
			$path =  "/api/$orgUnitID/dataSets/$dataSetId";
		}

		return $this->get($path, $urlParams);
	}

	//Get all data set elements for specified data set
	public function getDataElements($dataSetID)
	{

		if (!$this->authenticated || empty($dataSetID)) return false;

		$urlParams[] = "paging=false";
		$urlParams[] = "filter=dataSetElements.dataSet.id:eq:" . $dataSetID;
		$path =  "/api/dataElements";

		return $this->get($path, $urlParams);
	}

	//Get all data set elements combo for specified data set element
	public function getDataElementsCombo($dataElementID)
	{

		if (!$this->authenticated || empty($dataElementID)) return false;

		$urlParams[] = "paging=false";
		$urlParams[] = "fields=categoryCombo[:all,categoryOptionCombos[:all]]";
		$path =  "/api/dataElements/$dataElementID";

		return $this->get($path, $urlParams);
	}


	// Send data value sets to Dhis2
	/*
	$dataValues is an array containing dateElement and value
	$dataValues = [
		[
			"dataElement" => "X1g4CrwfVFj",
			"value" => "90"
		],
		[
			"dataElement" => "EsEyXHADpbX",
			"value" => "70"
		]
	];
	*/
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

		return $this->post("/api/dataValueSets", json_encode($data));
	}


	// Get data value sets from Dhis2
	public function getDataValueSets($orgUnitId, $dataSetId, $period = null, $startDate = null, $endDate = null)
	{

		if (empty($orgUnitId) || empty($dataSetId)) return false;

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
	public function get($path, $urlParams = array())
	{

		if (empty($path)) return false;

		if (!empty($urlParams)) {
			$urlParams = '?' . implode("&", $urlParams);
		} else {
			$urlParams = "";
		}

		$url = $this->dhis2url . "{$path}{$urlParams}";
		//echo $url. "<br>";


		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:" . $this->getContentType()));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->password");
		$return = curl_exec($ch);
		//$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return $return;

//		if ($httpStatus === 200) {
//			return $return;
//		} else {
//			return false;
//		}
	}

	// Send POST request to DHIS2
	public function post($path, $data, $urlParams = array())
	{
		if (!$this->authenticated || empty($path) || empty($data)) return false;

		if (!empty($urlParams)) {
			$urlParams = '?' . implode("&", $urlParams);
		} else {
			$urlParams = "";
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->dhis2url . "{$path}{$urlParams}");
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:" . $this->getContentType()));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->password");
		$return = curl_exec($ch);
		$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return $return;

		
//		if ($httpStatus === 200 && !empty($return)) {
//			return $return;
//		} else {
//			return false;
//		}
	}

	// Send PUT request to DHIS2
	public function put($path, $data, $urlParams = array())
	{

		if (!$this->authenticated || empty($path) || empty($data)) return false;

		if (!empty($urlParams)) {
			$urlParams = '?' . implode("&", $urlParams);
		} else {
			$urlParams = "";
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->dhis2url . "{$path}{$urlParams}");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:" . $this->getContentType()));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->password");
		$return = curl_exec($ch);

		$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpStatus === 200) {
			return true;
		} else {
			return false;
		}
	}

	// Send PATCH request to DHIS2
	public function patch($path, $data, $urlParams = array())
	{

		if (!$this->authenticated || empty($path) || empty($data)) return false;

		if (!empty($urlParams)) {
			$urlParams = '?' . implode("&", $urlParams);
		} else {
			$urlParams = "";
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->dhis2url . "{$path}{$urlParams}");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: ". $this->getContentType()));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->password");
		$return = curl_exec($ch);
		$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpStatus === 200) {
			return true;
		} else {
			return false;
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

		if(!empty($eventPayload['dataValues'])){
			$eventPayload['dataValues'] = array_merge($eventPayload['dataValues'], $dataValues);
		}else{
			$eventPayload['dataValues'] = $dataValues;
		}
		
		return $eventPayload;
	}
}
