<?php

namespace SilverpopConnector;

use SilverpopConnector\SilverpopBaseConnector;
use SilverpopConnector\SilverpopRestConnector;
use SilverpopConnector\SilverpopConnectorException;
use SimpleXmlElement;

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

	protected $baseUrl    = null;
	protected $dateFormat = null;
	protected $username   = null;
	protected $password   = null;
	protected $sessionId  = null;

	// Silverpop date format 
	const SPOP_DATE_FORMAT	= 'm/d/Y H:i:s'; //2 digits with leading zeroes for month/day/hour/min/sec, 4 digit year
	const SPOP_TIME_FORMAT	= 'm/d/Y h:i:s A'; //see SPOP_DATE_FORMAT, hours as 00-12 with leading zeroes, added AM/PM 
	
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
	 * @param bool    $autoreply Automatically trigger auto-responders?
	 * @param integer $createdFrom
	 * @param array   $lists
	 * @return int Returns the RecipientId of the new recipient
	 * @throws SilverpopConnectorException
	 */
	public function addRecipient($listId, $fields, $upsert=false, $autoreply=false, $createdFrom=self::CREATED_FROM_MANUAL, $lists=array()) {
                if (!preg_match('/^\d+$/', $listId)) {
                        $listId = (int)$listId;
                }
                $createdFrom = (int)$createdFrom;
                if (!in_array($createdFrom, array(0,1,2,3))) {
                        throw new SilverpopConnectorException("Unrecognized contact createdFrom value: {$createdFrom}");
                }
		$sendAutoreply = $autoreply ? 'TRUE' : 'FALSE';
                $updateIfFound = $upsert ? 'TRUE' : 'FALSE';
 
                $lists = array_map("intval", $lists);
 
               $params = "<AddRecipient>
        <LIST_ID>{$listId}</LIST_ID>
        <CREATED_FROM>{$createdFrom}</CREATED_FROM>
        <SEND_AUTOREPLY>{$sendAutoreply}</SEND_AUTOREPLY>
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
	 * @param mixed  $flags   	A single flag or array of flags to use, e.g. <ADD_TO_STORED_FILES/> & <USE_CREATED_DATE/>
	 * @param array  $optParams A list of optional parameters as key=>value pairs e.g. <LIST_DATE_FORMAT>dd/MM/yyyy</LIST_DATE_FORMAT>, <EMAIL> etc	 
	 * @param array  $columns   A list of column names to export
	 * @return array An array of ('jobId'=>[int],'filePath'=>[string])
	 */
	public function exportList($listId, $startDate = null, $endDate = null, $type = self::EXPORT_TYPE_ALL, $format = self::EXPORT_FORMAT_CSV, 
		$flags = null, $optParams = array(), $columns = array()) {

		if (!preg_match('/^\d+$/', $listId)) {
			$listId = (int)$listId;
		}
		$type   = urlencode(strtoupper($type));
		$format = urlencode(strtoupper($format));
		
		//flags: e.g. ADD_TO_STORED_FILES, USE_CREATED_DATE
		if (!empty($flags)) { 
			if (!is_array($flags)) { 
				$flags = array($flags);	
			} 
			//validation: remove anything not a letter/underscore, make uppercase.
			foreach($flags as $i=>$flag) {
				$flag = preg_replace("/[^A-Za-z_]/", '', $flag);
				$flags[$i] = strtoupper($flag);
			}
		}
		
		$columnsToIgnore = array(
			'LIST_ID',
			'MAILING_ID',
			);

		$params = "<ExportList>
	<LIST_ID>{$listId}</LIST_ID>
	<EXPORT_TYPE>{$type}</EXPORT_TYPE>
	<EXPORT_FORMAT>{$format}</EXPORT_FORMAT>\n";

		if (!empty($flags)) {
			foreach($flags as $flag) {
			$params .= "\t<{$flag}/>\n";		
			}
		}
		if (!empty($optParams)) {
			foreach ($optParams as $key => $value) {
			$params .= "\t<{$key}>{$value}</{$key}>\n";
			}
		}

		if (!empty($startDate)) {
			$params .= "\t<DATE_START>".date(self::SPOP_DATE_FORMAT, $startDate)."</DATE_START>\n";
		}
		if (!empty($endDate)) {
			$params .= "\t<DATE_END>".date(self::SPOP_DATE_FORMAT, $endDate)."</DATE_END>\n";
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
	* //SK 2014-07-22
	 * Get all information on a data job (errored?, cancelled?, waiting, running or completed).
	 * CHECK - does the jobParam array need to be included in the returned value regardless of what the result is?
	 * 
	 * @param int $jobId
	 * @return array - associative array with jobId, jobStatus, jobDescription, including jobParams with PARAMETERS as NAME => VALUE
	 */
	public function getJobStatusInfo($jobId) {
		if (!preg_match('/^\d+$/', $jobId)) {
			$jobId = (int)$jobId;
		}

		$params = "<GetJobStatus>\n\t<JOB_ID>{$jobId}</JOB_ID>\n</GetJobStatus>";
		$params = new SimpleXmlElement($params);
		$result = $this->post($params);

		//get info 
		$jobInfo = array(
			'jobId'    => (string)$result->Body->RESULT->JOB_ID,
			'jobStatus' => (string)$result->Body->RESULT->JOB_STATUS,
			'jobDescription' => (string)$result->Body->RESULT->JOB_DESCRIPTION,
			);
		
		if (!empty($result->Body->RESULT->PARAMETERS)) {
			//get params
			$jobParams = array();
			foreach ($result->Body->RESULT->PARAMETERS->PARAMETER as $param) {
				$name = (string)$param->NAME;
				$value = (string)$param->VALUE;
				$jobParams[$name] = $value;
			}
			$jobInfo['jobParams'] = $jobParams;
		}
		return $jobInfo; 
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
	 * Get a list of mailings modified within the specified time range.
	 * 
	 * @param int $lastModifiedStart An integer timestamp
	 * @param int $lastModifiedEnd   An integer timestamp
	 * 
	 * @return array Returns an array of SimpleXmlElement objects, one for each mailing
	 * @throws SilverpopConnectorException
	 */
	public function getMailingTemplates($lastModifiedStart=0, $lastModifiedEnd=0) { 

		$params = "<GetMailingTemplates>\n";
		$params .= "\t<VISIBILITY>1</VISIBILITY>\n"; //0 private, 1 shared
		if (!empty($lastModifiedStart)) $params .= "\t<LAST_MODIFIED_TIME_START>".date(self::SPOP_DATE_FORMAT, $lastModifiedStart)."</LAST_MODIFIED_TIME_START>\n"; 
		if (!empty($lastModifiedEnd)) $params .= "\t<LAST_MODIFIED_TIME_END>".date(self::SPOP_DATE_FORMAT, $lastModifiedEnd)."</LAST_MODIFIED_TIME_END>\n"; 
		$params .= "</GetMailingTemplates>";

		$params = new SimpleXmlElement($params);

		$result =$this->post($params);
		$modifiedMailings = array();
		foreach ($result->Body->RESULT->MAILING_TEMPLATE as $mailing) {
			$modifiedMailings[] = $mailing;
		}
		return $modifiedMailings;
	}
	/**
	 * Get a listing of mailings sent for an organization for a specified date range and provides metrics for those mailings
	 * 
	 * @param int $dateStart An integer timestamp - required!
	 * @param int $dateEnd   An integer timestamp - required!
	 * @param mixed	$flags	A single flag or an array of optional flags	
	 * 
	 * @return array Returns an array of SimpleXmlElement objects, one for each mailing
	 * @throws SilverpopConnectorException
	 */
	public function getAggregateTrackingForOrg($dateStart=0, $dateEnd=0, $flags=null) { 
		//flags: e.g. EXCLUDE_TEST_MAILINGS, SENT, SCHEDULED
		if (!empty($flags)) { 
			if (!is_array($flags)) { 
				$flags = array($flags);	
			} 
			//validation: remove anything not a letter/underscore, make uppercase.
			foreach($flags as $i=>$flag) {
				$flag = preg_replace("/[^A-Za-z_]/", '', $flag);
				$flags[$i] = strtoupper($flag);
			}
		}
		$now = date(self::SPOP_DATE_FORMAT);
		if (!empty($dateStart)) { $spopStartDate = date(self::SPOP_DATE_FORMAT, $dateStart); }
		else { $spopStartDate = date(self::SPOP_DATE_FORMAT, strtotime($now." -1year")); }
		if (!empty($dateEnd)) { $spopEndDate = date(self::SPOP_DATE_FORMAT, $dateEnd); }
		else { $spopEndDate = date(self::SPOP_DATE_FORMAT, strtotime($now)); }


		$params = "<GetAggregateTrackingForOrg>\n";
		//Request fails if dates not provided
		$params .= "\t<DATE_START>".$spopStartDate."</DATE_START>\n"; 
		$params .= "\t<DATE_END>".$spopEndDate."</DATE_END>\n"; 
		
		if (!empty($flags)) { 
			foreach($flags as $flag) {
				$params .= "\t<{$flag} />\n";
			}
		}
		
		$params .= "</GetAggregateTrackingForOrg>";

		$params = new SimpleXmlElement($params);
		$result =$this->post($params);

		$sentMailings = array(); $topDomainStats = array();
		foreach ($result->Body->RESULT->Mailing as $mailing) {
			$sentMailings[] = $mailing;
		}
		foreach ($result->Body->RESULT->TopDomains as $topdomain) {
			$topDomainStats[] = $topdomain;
		}
		
		//$result_array = $sentMailings;
		$result_array = array($sentMailings, $topDomainStats);
		
		return $result_array;
	}

	/**
	 * Get a list of mailings modified within the specified time range.
	 * 
	 * @param int $dateStart An integer timestamp
	 * @param int $dateEnd   An integer timestamp
	 * @param mixed	$flags	A single flag or an array of optional flags	
	 * 
	 * @return array Returns an array of SimpleXmlElement objects, one for each mailing
	 * @throws SilverpopConnectorException
	 */
	public function getSentMailingsForOrg($dateStart=0, $dateEnd=0, $flags=null) { 

		//flags: e.g. EXCLUDE_TEST_MAILINGS, SENT, EXCLUDE_ZERO_SENT
		if (!empty($flags)) { 
			if (!is_array($flags)) { 
				$flags = array($flags);	
			} 
			//validation: remove anything not a letter/underscore, make uppercase.
			foreach($flags as $i=>$flag) {
				$flag = preg_replace("/[^A-Za-z_]/", '', $flag);
				$flags[$i] = strtoupper($flag);
			}
		}

		$params = "<GetSentMailingsForOrg>\n";
		if (!empty($dateStart)) $params .= "\t<DATE_START>".date(self::SPOP_DATE_FORMAT, $dateStart)."</DATE_START>\n"; 
		if (!empty($dateEnd)) $params .= "\t<DATE_END>".date(self::SPOP_DATE_FORMAT, $dateEnd)."</DATE_END>\n"; 
		
		if (!empty($flags)) { 
			foreach($flags as $flag) {
				$params .= "\t<{$flag} />\n";
			}
		}
		
		$params .= "</GetSentMailingsForOrg>";

		$params = new SimpleXmlElement($params);

		$result =$this->post($params);
		$sentMailings = array();
		foreach ($result->Body->RESULT->Mailing as $mailing) {
			$sentMailings[] = $mailing;
		}
		return $sentMailings;
	}

	/**
	 * Get a list of recipients modified within the specified time range.
	 * 
	 * @param int $listId
	 * @param int $lastModifiedStart An integer timestamp
	 * @param int $lastModifiedEnd   An integer timestamp
	 * 
	 * @return array Returns an array of SimpleXmlElement objects, one for each recipient
	 * @throws SilverpopConnectorException
	 */
	public function getModifiedRecipients($listId, $lastModifiedStart, $lastModifiedEnd) {
		if (!preg_match('/^\d+$/', $listId)) {
			$listId = (int)$listId;
		}

		$params = "<GetModifiedRecipients>
	<INSERTS_ONLY>false</INSERTS_ONLY>
	<CONTACT_TYPE>Contact</CONTACT_TYPE>
	<LIST_ID>{$listId}</LIST_ID>
	<INSERTS_ONLY>false</INSERTS_ONLY>
	<LAST_MODIFIED_TIME_START>".date(self::SPOP_DATE_FORMAT, $lastModifiedStart).'</LAST_MODIFIED_TIME_START>
	<LAST_MODIFIED_TIME_END>'.date(self::SPOP_DATE_FORMAT, $lastModifiedEnd).'</LAST_MODIFIED_TIME_END>
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
	 * Performs Silverpop Logout so concurrent requests can take place
	 * 
	 * @throws SilverpopConnectorException
	 */
	public function logout() {
		$params = "<Envelope>\n\t<Body>\n\t\t<Logout/>\n\t</Body></Envelope>";

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

		$this->sessionId = null;
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
	 * @param array $columns     An associative array of keys and values to update
	 * @param array $optParams   An associative array of optional parameters
	 * @return SimpleXmlElement
	 * @throws SilverpopConnectorException
	 */
	public function updateRecipient($listId, $recipientId, $columns, $optParams=array()) {
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
		foreach ($columns as $key => $value) {
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
	 * //SK 20140220 RawRecipientDataExport all elements are optional...
	 * 
	 * @param mixed	$mailingId	The ID (int or associative array with TYPE => ID) of the mailing(s) - see Notes!
	 * @param array	$dates	Optional array of dates with TYPE => DATE. TODO convert to mm/dd/yyyy hh:mm:ss when differently formatted	
	 * @param mixed	$flags	A single flag or an array of optional flags, e.g. MOVE_TO_FTP
	 * @param array	$optParams	Associative array of optional params, e.g.: 
	 * 	EXPORT_FORMAT (int), EMAIL (notification e-mail address), <RETURN_MAILING_NAME> (true) <RETURN_SUBJECT>true, <RETURN_CRM_CAMPAIGN_ID>true 
	 * @param array	$listColumns	An associative array of unique/key columns to be included in the exported file
	 * @return SimpleXmlElement
	 * @throws SilverpopConnectorException
	 *
	 * Silverpop Note: MailingId - TYPE can be MAILING_ID, REPORT_ID, LIST_ID, CAMPAIGN_ID in varying combinations
	 * Silverpop Note: Export format default csv, Encoding that of the Org
	 * see also https://github.com/Boardroom/smart-popup/blob/master/test_sp_export_api.php
	 */
	public function rawRecipientDataExport($mailingId=null, $idType="MAILING_ID", $dates=array(), $flags=null, $optParams=array(), $listColumns=array()) {
		
		$mailings = null;
		if (!empty($mailingId)) {
			if (!is_array($mailingId)) { 
				if (!preg_match('/^\d+$/', $mailingId)) {
					$mailingId = (int)$mailingId;
				}
				//$mailings = array("MAILING_ID" => $mailingId);
				$mailings = array($idType => $mailingId);	
			} else { 
				//mailingId is an array
				//TODO validation - make sure IDs are numbers, also check TYPE?
				$mailings = $mailingId;	
			}
		}

		if (!empty($flags)) {
			if (!is_array($flags)) { 
				$flags = array($flags);	
			} 
			//validation: remove anything not a letter/underscore, make uppercase.
			foreach($flags as $i=>$flag) {
				$flag = preg_replace("/[^A-Za-z_]/", '', $flag);
				$flags[$i] = strtoupper($flag);
			}
		}

		$params = "<RawRecipientDataExport>";
		if (!empty($dates)) { 
			foreach($dates as $dateType => $date) {
				$params .= "\t<{$dateType}>".date(self::SPOP_DATE_FORMAT, $date)."</{$dateType}>\n";
			}
		}
		if (!empty($mailings)) { 
			foreach($mailings as $idType => $mailingId) {
			$params .= "\t<MAILING>\n";
				$params .= "\t\t<{$idType}>{$mailingId}</{$idType}>\n";
			$params .= "\t</MAILING>\n";
			}
		}
		if (!empty($flags)) { 
			foreach($flags as $flag) {
				$params .= "\t<{$flag} />\n";
			}
		}
		if (!empty($optParams)) {
			foreach ($optParams as $key => $value) {
				$params .= "\t<{$key}>{$value}</{$key}>\n";
			}
		}
		if (!empty($listColumns)) {
			foreach ($listColumns as $key => $value) {
				$params .= "\t<COLUMN>\n";
				$params .= "\t\t<NAME>{$key}</NAME>\n";
				$params .= "\t\t<VALUE>{$value}</VALUE>\n";
				$params .= "\t</COLUMN>\n";
			}
		}
		$params .= '</RawRecipientDataExport>';

		$params = new SimpleXmlElement($params);

		$result = $this->post($params);

		$jobId= null; $filePath=null;
		if (!empty($result->Body->RESULT->MAILING)){
			$jobId = $result->Body->RESULT->MAILING->JOB_ID;
			if (!preg_match('/^\d+$/', $jobId)) {
				$jobId = (int)$jobId;
			}
			$filePath = $result->Body->RESULT->MAILING->FILE_PATH;
		}
		$msg = $result;
		$result['msg'] = $msg;
		$result['jobId'] = $jobId;
		$result['filePath'] = $filePath;
	
		return $result;

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
	
	/**
	 * //SK 20140704 doubleOptIn -  DoubleOptInRecipient. 
	 * the recipient needs to be already added to the database (addrecipient)
	 * returns a new recipient ID (!= addrecipient id)
	 * Silverpop API documentation Winter 14 p.34
	 * 
	 * @param int	$listId	The ID of the Double Opt in DB
	 * @param string	$email	The email address of the recipient
	 * @param bool	$autoReply	Default 'true' to trigger Autoresponder
	 * @param array	$columns	Optional - Key column(s) as name => value pairs to find recipient
	 *
	 * @return SimpleXmlElement
	 * @throws SilverpopConnectorException
	 *
	 */
	public function doubleOptIn($listId, $email, $autoReply = true, $columns = array()) {
		if (!preg_match('/^\d+$/', $listId)) {
			$listId = (int)$listId;
		}
		$sendAutoreply = $autoReply ? 'TRUE' : 'FALSE';
		
		$params = "<DoubleOptInRecipient>\n";
		$params .= "\t<LIST_ID>{$listId}</LIST_ID>\n";
		$params .= "\t<SEND_AUTOREPLY>{$sendAutoreply}</SEND_AUTOREPLY>\n";

		$params .= "\t<COLUMN>\n\t\t<NAME>EMAIL</NAME>\n";		
		$params .= "\t\t<VALUE>{$email}</VALUE>\n\t</COLUMN>\n";

		if (!empty($columns)) { 	
			foreach ($columns as $name => $value) {	
		$params .= "\t<COLUMN>\n";				
		$params .= "\t\t<NAME>{$name}</NAME>\n";				
		$params .= "\t\t<VALUE>{$value}</VALUE>\n";
		$params .= "\t</COLUMN>\n";				
			}
		}
		$params .= "</DoubleOptInRecipient>";

		$params = new SimpleXmlElement($params);

		$result = $this->post($params);
		$recipientId = $result->Body->RESULT->RecipientId;
		if (!preg_match('/^\d+$/', $recipientId)) {
			$recipientId = (int)$recipientId;
		}
		return $recipientId;
	}

	/**
	 * //SK 20140205 Send mailing (Code by RR), updated 2014-07-21. 
	 * Silverpop API documentation v Winter 14 is WRONG 
	 * request needs <NAME> and <VALUE> in uppercase
	 * 
	 * @param string	$email	The email address to send the mailing to
	 * @param int	$autoresponder	The ID of the Autoresponder
	 * @param array	$columns	Optional - columns as name => value pairs to find recipient
	 *
	 * @return SimpleXmlElement
	 * @throws SilverpopConnectorException
	 *
	 */
	public function sendMailing($email, $autoresponder, $columns = array()) {
		if (!preg_match('/^\d+$/', $autoresponder)) {
			$autoresponder = (int)$autoresponder;
		}

		$params = "<SendMailing>\n";
		$params .= "\t<MailingId>{$autoresponder}</MailingId>\n";
		$params .= "\t<RecipientEmail>{$email}</RecipientEmail>\n";

		if (!empty($columns)) { 				
		$params .= "\t<COLUMNS>\n";				
			foreach ($columns as $name => $value) {				
		$params .= "\t\t<COLUMN>\n";				
		$params .= "\t\t\t<NAME>{$name}</NAME>\n";				
		$params .= "\t\t\t<VALUE>{$value}</VALUE>\n";
		$params .= "\t\t</COLUMN>\n";	
			}					
		$params .= "\t</COLUMNS>\n";		
		}
		$params .= "</SendMailing>";

		$params = new SimpleXmlElement($params);
		$result = $this->post($params);
		return $result->Body->RESULT;
	}
	/**
	 * //SK 20140717 CreateContactList 
	 * Create a new contact list associated with an existing database.
	 * 
	 * @param integer $databaseId The ID of the database to associate the new list with
	 * @param string  $listName   The name of the new Contact List
	 * @param integer $folderId   Optional - ID of the folder to store the list in
	 * @param string  $folderPath Optional - path of the folder (MainFolder/SubFolder)
	 * @return int Returns the ID of the new Contact List - CONTACT_LIST_ID
	 * @throws SilverpopConnectorException
	 */
	public function createContactList($databaseId, $listName, $folderId=0, $folderPath=null) {
        if (!preg_match('/^\d+$/', $databaseId)) {
        	$databaseId = (int)$databaseId;
        }
        if (!preg_match('/^\d+$/', $folderId)) {
        	$folderId = (int)$folderId;
        }
        // $listName
        // VISIBILITY 0 private, 1 shared
 
        $params = "<CreateContactList>
        <DATABASE_ID>{$databaseId}</DATABASE_ID>
        <CONTACT_LIST_NAME>{$listName}</CONTACT_LIST_NAME>
        <VISIBILITY>1</VISIBILITY>\n";
		
		if (!empty($folderId)) {
			$params .= "\t<PARENT_FOLDER_ID>{$folderId}</PARENT_FOLDER_ID>\n";
		}
		if (!empty($folderPath)) {
			$params .= "\t<PARENT_FOLDER_PATH>{$folderPath}</PARENT_FOLDER_PATH>\n";
		}
	
		$params .= "</CreateContactList>";

		$params = new SimpleXmlElement($params);
		$result = $this->post($params);
		$contactListId = $result->Body->RESULT->CONTACT_LIST_ID;
		if (!preg_match('/^\d+$/', $contactListId)) {
			$contactListId = (int)$contactListId;
		}
		return $contactListId;
	}
	/**
	 * //SK 20140717 ImportList 
	 * Updating a database with existing data and mapping files.
	 * 
	 * @param string $mapFile  Filename of xml mapping file in the SFTP upload folder of API user
	 * @param string $dataFile Filename of the csv file in the SFTP upload folder of API user
	 * @param string $email    Optional - notification e-mail address
	 * @param string $enc      Optional - encoding of the source/data file 'UTF-8' or 'ISO-8859-1', default: Org Setting
	 * @return int Returns data job ID 
	 * @throws SilverpopConnectorException
	 */
	public function importList($mapFile, $dataFile, $email=null, $enc=0) {
        
        $params = "<ImportList>
        <MAP_FILE>{$mapFile}</MAP_FILE>
        <SOURCE_FILE>{$dataFile}</SOURCE_FILE>\n";
		
		if (!empty($email)) {
			$params .= "\t<EMAIL>{$email}</EMAIL>\n";
		}
		if (!empty($enc)) {
			$params .= "\t<FILE_ENCODING>{$enc}</FILE_ENCODING>\n";
		}
	
		$params .= "</ImportList>";

		$params = new SimpleXmlElement($params);
		$result = $this->post($params);
		$jobId = $result->Body->RESULT->JOB_ID;
		if (!preg_match('/^\d+$/', $jobId)) {
			$jobId = (int)$jobId;
		}
		return $jobId;
	}
	/**
	 * //SK 20140721 ScheduleMailing 
	 * Schedule a mailing with a mailing template ID and contact source ID. 
	 * To stay consistent, the time a mailing is scheduled should be passed as a UNIX timestamp (and is converted as per SPOP format)
	 * When <SCHEDULED> is omitted, invalid or in the past, the mailing is scheduled as per the UI default: 1 hour from the current time
	 * Use 'false' (explicit comparison) to send immediately.
	 * When a parent folder is specified to store the sent mailing in (see optParams), this will be created when it doesn't exist
	 * 
	 * @param int    $templateId  ID of the mailing template to send
	 * @param int    $listId      ID of contact list, query or database to send to
	 * @param string $mailingName Name to assign to the sent mailing
	 * @param string $bodyType  Type of mailing to send - HTML, AOL, TEXT, ALL or MIXED, all is HTML+AOL+TEXT, default mixed (HTML + TEXT)
	 * @param int    $scheduledTS Timestamp of when to schedule the mailing, defaults to 1hr from now (same as UI), use false for immediate send
	 * @param array  $optParams A list of optional parameters as key=>value pairs e.g. <SUBJECT>, <FROM_NAME>, <PARENT_FOLDER_PATH> etc		 
	 * @param mixed  $suppressions  Optional - A single suppression list ID or array of IDs		
	 * @param array  $substitutions Optional - An associative array of $name=>$value pairs for substitutions 
	 * @return int Returns scheduled mailing ID 
	 * @throws SilverpopConnectorException
	 */
	public function scheduleMailing($templateId, $listId, $mailingName, $bodyType="MIXED", $scheduledTS=null, $optParams=array(), $suppressions=null, $substitutions=array()) {
        if (!preg_match('/^\d+$/', $templateId)) {
        	$templateId = (int)$templateId;
        }
        if (!preg_match('/^\d+$/', $listId)) {
        	$listId = (int)$listId;
        }
        $params = "<ScheduleMailing>
        <TEMPLATE_ID>{$templateId}</TEMPLATE_ID> 
        <LIST_ID>{$listId}</LIST_ID>
        <MAILING_NAME>{$mailingName}</MAILING_NAME>
		<VISIBILITY>1</VISIBILITY>\n";  //0 private, 1 shared
		
		//if (!empty($bodyType)) { 		
		//validation: remove anything not a letter/underscore, make uppercase.
		$bodyType = preg_replace("/[^A-Za-z_]/", '', $bodyType); 
			
			if ($bodyType == "HTML") {
		$params .= "\t<SEND_HTML/>\n";
			} elseif ($bodyType == "TEXT") {
		$params .= "\t<SEND_TEXT/>\n";	
			} elseif ($bodyType == "AOL") { 
		//developer guide 88 - May 2013 
		$params .= "\t<SEND_AOL/>\n";
			} elseif ($bodyType == "ALL") { 
		$params .= "\t<SEND_HTML/>\n";
		$params .= "\t<SEND_AOL/>\n";
		$params .= "\t<SEND_TEXT/>\n";			
			} else {		
		//catch all - MIXED default		
		$params .= "\t<SEND_HTML/>\n";	
		$params .= "\t<SEND_TEXT/>\n";			
			}
		//} else { }

		//	<SCHEDULED>MM/DD/YYYY HH:MM:SS AMPM</SCHEDULED> = const self::SPOP_TIME_FORMAT
		//	e.g. 	<SCHEDULED>12/31/2014 11:00:00 PM</SCHEDULED>
		$now = date(self::SPOP_TIME_FORMAT);
		$scheduleUI = strtotime($now.' +1 hour'); 
		//if (is_null($scheduledTS)) { //no value or null
		
		if ($scheduledTS !== false) {
			if (empty($scheduledTS)) { $scheduledTS  = $scheduleUI; } //invalid value, set to default UI schedule
			if ($scheduledTS < strtotime($now)) { $scheduledTS  = $scheduleUI; }  //before now, set to default UI schedule
		}
		if (!empty($scheduledTS)) { 
		$params .= "\t<SCHEDULED>".date(self::SPOP_TIME_FORMAT, $scheduledTS)."</SCHEDULED>\n";
		}

		//optParams as in doc ALL uppercase
		if (!empty($optParams)) { 
			foreach ($optParams as $key => $value) {
		//validation: remove anything not a letter/underscore, make uppercase.
			$key = preg_replace("/[^A-Za-z_]/", '', $key); 
		$params .= "\t<{$key}>{$value}</{$key}>\n";

				if ($key == "PARENT_FOLDER_PATH") { 
		$params .= "\t<CREATE_PARENT_FOLDER/>\n";	//create folder if it doesn't exist	
				}
			}
		}

		if (!empty($suppressions)) {
		$params .= "\t<SUPPRESSION_LISTS>\n";
			
			if (!is_array($suppressions)) { 
				$suppressions = array($suppressions);	
			} 

			foreach ($suppressions as $suppressionListId) {
		//validation: make a number		
		if (!preg_match('/^\d+$/', $suppressionListId)) {
			$suppressionListId = (int)$suppressionListId;
		}				
		$params .= "\t\t<SUPPRESSION_LIST_ID>{$suppressionListId}</SUPPRESSION_LIST_ID>\n";	
			}
		$params .= "\t</SUPPRESSION_LISTS>\n";
		}
	
		if (!empty($substitutions)) {
		$params .= "\t<SUBSTITUTIONS>\n";
			foreach ($substitutions as $name => $value) {				
		$params .= "\t\t<SUBSTITUTION>\n";				
		$params .= "\t\t\t<NAME>{$name}</NAME>\n";				
		$params .= "\t\t\t<VALUE>{$value}</VALUE>\n";
		$params .= "\t\t</SUBSTITUTION>\n";	
			}
			$params .= "\t</SUBSTITUTIONS>\n";
		}
	
		$params .= "</ScheduleMailing>";

		$params = new SimpleXmlElement($params);
		$result = $this->post($params);
		$mailingId = $result->Body->RESULT->MAILING_ID;
		if (!preg_match('/^\d+$/', $mailingId)) {
			$mailingId = (int)$mailingId;
		}
		return $mailingId;
	}
	/**
	* //SK 2014-05-05
	 * Change the value of an existing column
	 * 
	 * @param integer $istId   	query, database or contactlist ID
	 * @param string   $name	column name 
	 * @param string   $value 	new value for all contacts
	 * @param integer  $action 	0 = reset to null or 0, 1 = update - sest value, 2 = increment (??)
	 * @param string   $email 	e-mail address to notify when completed
	 * @return int Returns the jobId for the Engage Background Job created to set column values. 
	 * @throws SilverpopConnectorException
	 *
	 * //TODO?? define types of $action as constants?
	 * //Summer guide p 103 
	 */
	public function setColumnValue($listId, $name, $value='', $action=1, $email='') {
                if (!preg_match('/^\d+$/', $listId)) {
                        $listId = (int)$listId;
                }
                $action = (int)$action;
                if (!in_array($action, array(0,1,2))) {
                        throw new SilverpopConnectorException("Unrecognized action value: {$action}");
                }
		
               $params = "<SetColumnValue>
        <LIST_ID>{$listId}</LIST_ID>
        <COLUMN_NAME>{$name}</COLUMN_NAME>
        <COLUMN_VALUE>{$value}</COLUMN_VALUE>
        <ACTION>{$action}</ACTION>\n";
		if (!empty($email)) {
			$params .= "\t<EMAIL>{$email}</EMAIL>\n";
		}
		$params .= "</SetColumnValue>";
		$params = new SimpleXmlElement($params);
		$result = $this->post($params);
		$jobId = $result->Body->RESULT->JOB_ID;
		if (!preg_match('/^\d+$/', $jobId)) {
			$jobId = (int)$jobId;
		}
		return $jobId;
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
		// according to Silverpop's API docs, XML response should always be
		// UTF-8
		if (mb_check_encoding($xml, 'UTF-8') === false) {
				$xml = utf8_encode($xml);
		}

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
	 * //RJR 20141104 Calculate Query 
	 * Recalculate a query.
	 * 
	 * @param string $queryId  The ID number of the Query to be calculated
	 * @param string $email    Optional - notification e-mail address
	 * @return int Returns data job ID 
	 * @throws SilverpopConnectorException
	 */
	public function calculateQuery($queryId, $email=null) {
        
        $params = "<CalculateQuery>
        <QUERY_ID>{$queryId}</QUERY_ID>\n";
		
		if (!empty($email)) {
			$params .= "\t<EMAIL>{$email}</EMAIL>\n";
		}
	
		$params .= "</CalculateQuery>";

		$params = new SimpleXmlElement($params);
		$result = $this->post($params);
		$jobId = $result->Body->RESULT->JOB_ID;
		if (!preg_match('/^\d+$/', $jobId)) {
			$jobId = (int)$jobId;
		}
		return $jobId;
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
