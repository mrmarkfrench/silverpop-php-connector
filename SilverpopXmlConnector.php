<?php
require_once __DIR__.'/SilverpopBaseConnector.php';
require_once __DIR__.'/SilverpopRestConnector.php';
require_once __DIR__.'/SilverpopConnectorException.php';

/**
 * This is a basic class for connecting to the Silverpop XML API. If you
 * need to connect only to the XML API, you can use this class directly.
 * However, if you would like to utilize resources spread between the XML
 * and REST APIs, you shoudl instead use the generalized SilverpopConnector
 * class.
 * 
 * @author Mark French, Argyle Social
 */
class SilverpopXmlConnector extends SilverpopBaseConnector {
	protected static $instance = null;

	protected $baseUrl   = null;
	protected $username  = null;
	protected $password  = null;
	protected $sessionId = null;

	// Contact creation source constants
	const CREATED_FROM_DB_IMPORT   = 0;
	const CREATED_FROM_MANUAL      = 1;
	const CREATED_FROM_OPT_IN      = 2;
	const CREATED_FROM_TRACKING_DB = 3;

	// List export formatting constants
	const EXPORT_FORMAT_CSV  = 'CSV';
	const EXPORT_FORMAT_TAB  = 'TAB';
	const EXPORT_FORMAT_PIPE = 'PIPE';
	// List formatting filter constants
	const EXPORT_TYPE_ALL           = 'ALL';
	const EXPORT_TYPE_OPT_IN        = 'OPT_IN';
	const EXPORT_TYPE_OPT_OUT       = 'OPT_OUT';
	const EXPORT_TYPE_UNDELIVERABLE = 'UNDELIVERABLE';

	///////////////////////////////////////////////////////////////////////////
	// PUBLIC ////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////

	/**
	 * Add a new contact to an existing database.
	 * 
	 * @param integer $listId
	 * @param array   $fields
	 * @param bool    $upsert Perform an update if contact already exists?
	 * @param integer $createdFrom
	 * @param array   $lists
	 * @return int Returns the RecipientId of the new recipient
	 * @throws SilverpopConnectorException
	 */
	public function addRecipient($listId, $fields, $upsert=false, $createdFrom=self::CREATED_FROM_MANUAL, $lists=array()) {
		if (!preg_match('/^\d+$/', $listId)) {
			$listId = (int)$listId;
		}
		$createdFrom = (int)$createdFrom;
		if (!in_array($createdFrom, array(0,1,2,3))) {
			throw new SilverpopConnectorException("Unrecognized contact createdFrom value: {$createdFrom}");
		}
		$updateIfFound = $upsert ? 'TRUE' : 'FALSE';
		$lists = array_map("intval", $lists);

		$params = "<AddRecipient>
	<LIST_ID>{$listId}</LIST_ID>
	<CREATED_FROM>{$createdFrom}</CREATED_FROM>
	<UPDATE_IF_FOUND>{$updateIfFound}</UPDATE_IF_FOUND>\n";
		if (count($lists)) {
			$params .= "\t<CONTACT_LISTS>\n";
			foreach($lists as $list) {
				$params .= "\t\t<CONTACT_LIST_ID>{$list}</CONTACT_LIST_ID>\n";
			}
			$params .= "\t</CONTACT_LISTS>\n";
		}

		foreach ($fields as $key => $value) {
			$params .= "\t<COLUMN>\n";
			$params .= "\t\t<NAME>{$key}</NAME>\n";
			$params .= "\t\t<VALUE>{$value}</VALUE>\n";
			$params .= "\t</COLUMN>\n";
		}
		$params .= "</AddRecipient>";
		$params = new SimpleXmlElement($params);
		$result = $this->post($params);
		$recipientId = $result->Body->RESULT->RecipientId;
		if (!preg_match('/^\d+$/', $recipientId)) {
			$recipientId = (int)$recipientId;
		}
		return $recipientId;
	}

	/**
	 * Performs Silverpop authentication using the supplied credentials,
	 * or with the cached credentials if none are supplied. Any new credentials
	 * will be cached for the next request.
	 * 
	 * @param string $clientId
	 * @param string $clientSecret
	 * @param string $refreshToken
	 *
	 * @throws SilverpopConnectorException
	 */
	public function authenticate($username=null, $password=null) {
		$this->username = empty($username) ? $this->username : $username;
		$this->password = empty($password) ? $this->password : $password;

		$params = "<Envelope>
	<Body>
		<Login>
			<USERNAME>{$username}</USERNAME>
			<PASSWORD>{$password}</PASSWORD>
		</Login>
	</Body>
</Envelope>";

		$ch = curl_init();
		$curlParams = array(
			CURLOPT_URL            => $this->baseUrl.'/XMLAPI',
			CURLOPT_FOLLOWLOCATION => 1,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST           => 1,
			CURLOPT_POSTFIELDS     => http_build_query(array('xml'=>$params)),
			);
		$set = curl_setopt_array($ch, $curlParams);

		$resultStr = curl_exec($ch);
		curl_close($ch);
		$result = $this->checkResponse($resultStr);

		$this->sessionId = $result->Body->RESULT->SESSIONID;
	}

	/**
	 * Initiate a job to export a list from Silverpop. Will return a job ID
	 * that can be queried to determine when the list is complete, and a file
	 * path to retrieve it.
	 * 
	 * If a list of export columns is specified, only those columns will be
	 * included in the result (see the getListMetaData() method to determine
	 * which fields are available for your list). If no columns are specified,
	 * by default all custom fields and all system fields (except RECIPIENT_ID)
	 * will be included in the export.
	 * 
	 * @param int    $listId
	 * @param int    $startDate A timestamp for date boundaries
	 * @param int    $endDate   A timestamp for date boundaries
	 * @param string $type      One fo the EXPORT_TYPE_* constants
	 * @param string $format    One of the EXPORT_FORMAT_* constants
	 * @param array  $columns   A list of column names to export
	 * @return array An array of ('jobId'=>[int],'filePath'=>[string])
	 */
	public function exportList(
		$listId,
		$startDate = null,
		$endDate   = null,
		$type      = self::EXPORT_TYPE_ALL,
		$format    = self::EXPORT_FORMAT_CSV,
		$columns   = array()) {

		if (!preg_match('/^\d+$/', $listId)) {
			$listId = (int)$listId;
		}
		$type   = urlencode(strtoupper($type));
		$format = urlencode(strtoupper($format));

		$columnsToIgnore = array(
			'LIST_ID',
			'MAILING_ID',
			);

		$params = "<ExportList>
	<LIST_ID>{$listId}</LIST_ID>
	<EXPORT_TYPE>{$type}</EXPORT_TYPE>
	<EXPORT_FORMAT>{$format}</EXPORT_FORMAT>
	<ADD_TO_STORED_FILES/>\n";
		if (!empty($startDate)) {
			$params .= '	<DATE_START>'.date('m/d/Y H:i:s', $startDate)."</DATE_START>\n";
		}
		if (!empty($endDate)) {
			$params .= '	<DATE_END>'.date('m/d/Y H:i:s', $endDate)."</DATE_END>\n";
		}
		if (count($columns)) {
			$params .= "\t<EXPORT_COLUMNS>\n";
			foreach ($columns as $column) {
				if (in_array($column, $columnsToIgnore)) {
					continue;
				}
				$params .= "\t\t<COLUMN>{$column}</COLUMN>\n";
			}
			$params .= "\t</EXPORT_COLUMNS>\n";
		}
		$params .= '</ExportList>';
		$params = new SimpleXmlElement($params);
		$result = $this->post($params);
		return array(
			'jobId'    => $result->Body->RESULT->JOB_ID,
			'filePath' => $result->Body->RESULT->FILE_PATH,
			);
	}

	/**
	 * Check the status of a data job.
	 * 
	 * @param int $jobId
	 * @return string {WAITING, RUNNING, CANCELLED, ERROR, COMPLETE}
	 */
	public function getJobStatus($jobId) {
		if (!preg_match('/^\d+$/', $jobId)) {
			$jobId = (int)$jobId;
		}

		$params = "<GetJobStatus>\n\t<JOB_ID>{$jobId}</JOB_ID>\n</GetJobStatus>";
		$params = new SimpleXmlElement($params);
		$result = $this->post($params);
		return (string)$result->Body->RESULT->JOB_STATUS;
	}

	/**
	 * Get metadata for the specified list.
	 * 
	 * @param int $listId
	 * @return SimpleXmlElement
	 */
	public function getListMetaData($listId) {
		if (!preg_match('/^\d+$/', $listId)) {
			$listId = (int)$listId;
		}
		$params = "<GetListMetaData>\n\t<LIST_ID>{$listId}</LIST_ID>\n</GetListMetaData>";
		$params = new SimpleXmlElement($params);
		$result = $this->post($params);
		return $result->Body->RESULT;
	}

	/**
	 * Get a set of lists (DBs, queries, and contact lists) defined for this
	 * account.
	 * 
	 * @return array Returns an array of SimpleXmlElement objects, one for each list
	 * @throws SilverpopConnectorException
	 */
	public function getLists() {
		// Get private lists
		$params = '<GetLists>
	<VISIBILITY>0</VISIBILITY>
	<LIST_TYPE>2</LIST_TYPE>
</GetLists>';
		$params = new SimpleXmlElement($params);
		$result = $this->post($params);
		$lists = array();
		foreach ($result->Body->RESULT->LIST as $list) {
			$lists[] = $list;
		}

		// Get shared lists
		$params = '<GetLists>
	<VISIBILITY>1</VISIBILITY>
	<LIST_TYPE>2</LIST_TYPE>
</GetLists>';
		$params = new SimpleXmlElement($params);
		$result = $this->post($params);
		foreach ($result->Body->RESULT->LIST as $list) {
			$lists[] = $list;
		}
		return $lists;
	}

	/**
	 * Get a list of recipients modified within the specified time range.
	 * 
	 * @param int $lastModifiedStart An integer timestamp
	 * @param int $lastModifiedEnd   An integer timestamp
	 * @param int $listId
	 * 
	 * @return array Returns an array of SimpleXmlElement objects, one for each recipient
	 * @throws SilverpopConnectorException
	 */
	public function getModifiedRecipients($lastModifiedStart, $lastModifiedEnd, $listId) {
		if (!preg_match('/^\d+$/', $listId)) {
			$listId = (int)$listId;
		}

		$params = "<GetModifiedRecipients>
	<INSERTS_ONLY>false</INSERTS_ONLY>
	<CONTACT_TYPE>Contact</CONTACT_TYPE>
	<LIST_ID>{$listId}</LIST_ID>
	<INSERTS_ONLY>false</INSERTS_ONLY>
	<LAST_MODIFIED_TIME_START>".date('m/d/Y H:i:s', $lastModifiedStart).'</LAST_MODIFIED_TIME_START>
	<LAST_MODIFIED_TIME_END>'.date('m/d/Y H:i:s', $lastModifiedEnd).'</LAST_MODIFIED_TIME_END>
	<COLUMNS>
		<COLUMN name="FirstName" />
		<COLUMN name="LastName" />
		<COLUMN name="Email" />
	</COLUMNS>
</GetModifiedRecipients>';
		$params = new SimpleXmlElement($params);

		$result =$this->post($params);
		$modifiedRecipients = array();
		foreach ($result->Body->RESULT->RECIPIENTS as $recipient) {
			$modifiedRecipients[] = $recipient;
		}
		return $modifiedRecipients;
	}

	/**
	 * Delete a recpient from a database.
	 * 
	 * @param int    $listId    The ID of the DB/list to remove from
	 * @param string $mail
	 * @param array  $keyFields An associative array of ID fields to match
	 * @return bool
	 */
	public function removeRecipient($listId, $email, $keyFields=array()) {
		if (!preg_match('/^\d+$/', $listId)) {
			$listId = (int)$listId;
		}

		$params = "<RemoveRecipient>
	<LIST_ID>{$listId}</LIST_ID>
	<EMAIL>{$email}</EMAIL>\n";
		foreach ($keyFields as $key => $value) {
			$params .= "\t<COLUMN>\n";
			$params .= "\t\t<NAME>{$key}</NAME>\n";
			$params .= "\t\t<VALUE>{$value}</VALUE>\n";
			$params .= "\t</COLUMN>\n";
		}
		$params .= "</RemoveRecipient>";
		$params = new SimpleXmlElement($params);

		$result = $this->post($params);
		return true;
	}

	/**
	 * Set the session ID used to authenticate connections. Use this method
	 * to set a pre-existing session ID that has not yet expired, in order to
	 * avoid re-authenticating.
	 * 
	 * @param string $sessionId
	 */
	public function setSessionId($sessionId) {
		$this->sessionId = $sessionId;
	}

	/**
	 * Stream an exported file back from Silverpop over HTTP. Will either
	 * stream file content to the specified local filesystem path, or return
	 * the contents as a string. Note that for very large export files, resource
	 * limits for the PHP process might cause returning as a string to generate
	 * an out-of-memory error.
	 * 
	 * @param string $filePath
	 * @param string $output   A file path to write the result (or null to return string)
	 * @return mixed Returns TRUE/FALSE to indicate success writing file, or
	 * 	string content of no output path provided
	 */
	public function streamExportFile($filePath, $output=null) {
		$params = "<";

		// Wrap the request XML in an "envelope" element
		$postParams = http_build_query(array('filePath'=>$filePath));

		$curlHeaders = array(
				'Content-Type: application/x-www-form-urlencoded',
				'Content-Length: '.strlen($postParams),
				);
		// Use an oAuth token if there is one
		if ($accessToken = SilverpopRestConnector::getInstance()->getAccessToken()) {
			$curlHeaders[] = "Authorization: Bearer {$accessToken}";
			$url = $this->baseUrl.'/StreamExportFile';
		} else {
			// No oAuth, use jsessionid to authenticate
			$url = $this->baseUrl."/StreamExportFile;jsessionid={$this->sessionId}";
		}
		$url = str_replace('api.', '', $url);

		$ch = curl_init();
		$curlParams = array(
			CURLOPT_URL            => $url,
			CURLOPT_FOLLOWLOCATION => 1,//true,
			CURLOPT_POST           => 1,//true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_POSTFIELDS     => $postParams,
			CURLOPT_HTTPHEADER     => $curlHeaders,
			);
		if (empty($output)) {
			$curlParams[CURLOPT_RETURNTRANSFER] = 1;
		} else {
			$fh = fopen($output, 'w');
			$curlParams[CURLOPT_FILE] = $fh;
		}
		curl_setopt_array($ch, $curlParams);

		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

	/**
	 * Update a recipient in Silverpop.
	 * 
	 * @param int   $listId      The ID of the recipient's list
	 * @param int   $recipientId The ID of the recipient to update
	 * @param array $fields      An associative array of keys and values to update
	 * @param array $optParams   An associative array of optional parameters
	 * @return SimpleXmlElement
	 * @throws SilverpopConnectorException
	 */
	public function updateRecipient($listId, $recipientId, $fields, $optParams=array()) {
		if (!preg_match('/^\d+$/', $recipientId)) {
			$recipientId = (int)$recipientId;
		}
		if (!preg_match('/^\d+$/', $listId)) {
			$listId = (int)$listId;
		}

		$params = "<UpdateRecipient>
	<RECIPIENT_ID>{$recipientId}</RECIPIENT_ID>
	<LIST_ID>{$listId}</LIST_ID>\n";
		foreach ($optParams as $key => $value) {
			$params .= "\t<{$key}>{$value}</{$key}>\n";
		}
		foreach ($fields as $key => $value) {
			$params .= "\t<COLUMN>\n";
			$params .= "\t\t<NAME>{$key}</NAME>\n";
			$params .= "\t\t<VALUE>{$value}</VALUE>\n";
			$params .= "\t</COLUMN>\n";
		}
		$params .= '</UpdateRecipient>';
		$params = new SimpleXmlElement($params);

		$result = $this->post($params);
		$recipientId = $result->Body->RESULT->RecipientId;
		if (!preg_match('/^\d+$/', $recipientId)) {
			$recipientId = (int)$recipientId;
		}
		return $recipientId;
	}

	/**
	 * //SK 20140130 Fetch data for a recipient in Silverpop. 
	 * 
	 * @param int	$listId	The ID of the DB/Contact list
	 * @param array	$mainFields	An associative array of main fields to match (EMAIL, RECIPIENT_ID, VISITOR_KEY etc)
	 * @param array	$customFields	An associative array of unique/key columns when required
	 * @return SimpleXmlElement
	 * @throws SilverpopConnectorException
	 *
	 * Silverpop Note: Unique key columns must be part of the submission with column names and values. 
	 * Silverpop Note: If more than one contact is found matching the lookup columns, the oldest contact will be returned.
	 */
	public function selectRecipientData ($listId, $mainFields=array(), $customFields=array()) {
		if (!preg_match('/^\d+$/', $listId)) {
			$listId = (int)$listId;
		}

		$params = "<SelectRecipientData>
	<LIST_ID>{$listId}</LIST_ID>\n";
		foreach ($mainFields as $key => $value) { 
		//e.g. 'RETURN_CONTACT_LISTS' => 'true', 'EMAIL' => $email
			$key = strtoupper ($key); //SK 20140203
			$params .= "\t<{$key}>{$value}</{$key}>\n";
		}
		foreach ($customFields as $key => $value) {
			$params .= "\t<COLUMN>\n";
			$params .= "\t\t<NAME>{$key}</NAME>\n";
			$params .= "\t\t<VALUE>{$value}</VALUE>\n";
			$params .= "\t</COLUMN>\n";
		}
		$params .= "</SelectRecipientData>";
		$params = new SimpleXmlElement($params);

		$result =$this->post($params);

		$recipientData = $result->Body->RESULT;
		
		return $recipientData;		
	}	

	//////////////////////////////////////////////////////////////////////////
	// PROTECTED ////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////

	/**
	 * Check the XML response to ensure it contains the required elements
	 * and reports success.
	 * 
	 * @param string $xml
	 * @return SimpleXmlElement
	 * @throws SilverpopConnectorException
	 */
	protected function checkResponse($xml) {
		$response = new SimpleXmlElement($xml);
		if (!isset($response->Body)) {
			throw new SilverpopConnectorException("No <Body> element on response: {$xml}");
		} elseif (!isset($response->Body->RESULT)) {
			throw new SilverpopConnectorException("No <RESULT> element on response body: {$xml}");
		} elseif (!isset($response->Body->RESULT->SUCCESS)) {
			throw new SilverpopConnectorException("No <SUCCESS> element on result: {$xml}");
		} elseif (strtolower($response->Body->RESULT->SUCCESS) != 'true') {
			throw new SilverpopConnectorException('Request failed: '.$response->Body->Fault->FaultString);
		}
		return $response;
	}

	/**
	 * Send a POST request to the API
	 * 
	 * @param SimpleXmlElement $params        Parameters to pass to the requested resource
	 * @param string           $pathExtension Defaults to XML API endpoint
	 *
	 * @return SimpleXmlElement Returns an XML response object
	 * @throws SilverpopConnectorException
	 */
	protected function post($params, $pathExtension='/XMLAPI', $urlParams='') {
		// Wrap the request XML in an "envelope" element
		$envelopeXml = "<Envelope>\n\t<Body>\n";
		$params = $params->asXml();
		$paramLines = explode("\n", $params);
		$paramXml = '';
		for ($i=1; $i<count($paramLines); $i++) {
			$paramXml .= "\t\t{$paramLines[$i]}\n";
		}
		$envelopeXml .= $paramXml;
		$envelopeXml .= "\n\t</Body>\n</Envelope>";
		$xmlParams = http_build_query(array('xml'=>$envelopeXml));

		$curlHeaders = array(
				'Content-Type: application/x-www-form-urlencoded',
				'Content-Length: '.strlen($xmlParams),
				);
		// Use an oAuth token if there is one
		if ($accessToken = SilverpopRestConnector::getInstance()->getAccessToken()) {
			$curlHeaders[] = "Authorization: Bearer {$accessToken}";
			$url = $this->baseUrl.'/XMLAPI';
		} else {
			// No oAuth, use jsessionid to authenticate
			$url = $this->baseUrl."/XMLAPI;jsessionid={$this->sessionId}";
		}

		$ch = curl_init();
		$curlParams = array(
			CURLOPT_URL            => $url,
			CURLOPT_FOLLOWLOCATION => 1,//true,
			CURLOPT_POST           => 1,//true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_POSTFIELDS     => $xmlParams,
			CURLOPT_RETURNTRANSFER => 1,//true,
			CURLOPT_HTTPHEADER     => $curlHeaders,
			);
		curl_setopt_array($ch, $curlParams);

		$result = curl_exec($ch);
		curl_close($ch);
		return $this->checkResponse($result);
	}
}
