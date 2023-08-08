<?php

namespace SilverpopConnector;

use Omnimail\Exception\Exception;
use SilverpopConnector\Xml\CreateContactList;
use SilverpopConnector\Xml\ExportList;
use SilverpopConnector\Xml\GetQuery;
use SimpleXmlElement;
use SilverpopConnector\Xml\GetMailingTemplate;
use SilverpopConnector\Xml\GetAggregateTrackingForMailing;
use SilverpopConnector\Xml\CalculateQuery;
use SilverpopConnector\Xml\GetSentMailingsForOrg;
use SilverpopConnector\Xml\AddContactToContactList;
use phpseclib\Net\SFTP;
use GuzzleHttp\Client;

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

  protected $sftpUrl    = null;
  /**
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * Logout from silverpop when the class is destroyed.
   *
   * This prevents us using up api sessions and running out.
   *
   * https://developer.ibm.com/customer-engagement/docs/watson-marketing/ibm-engage-2/watson-campaign-automation-platform/using-oauth/legacy-authentication-method-jsessionid-user-sessions/
   *
   * @throws \SilverpopConnector\SilverpopConnectorException
   */
  public function __destruct() {
    $this->logout();
  }

  /**
   * @return \GuzzleHttp\Client
   */
  public function getClient() {
    if (!$this->client) {
      $this->setClient(new Client([
        // Base URI is used with relative requests
        'base_uri' => $this->baseUrl,
        'timeout' => $this->timeout,
        'allow_redirects' => array('max' => 3),
      ]));
    }
    return $this->client;
  }

  /**
   * @param \GuzzleHttp\Client $client
   */
  public function setClient(\GuzzleHttp\Client $client) {
    $this->client = $client;
  }

  /**
   * @return string
   */
  public function getSftpUrl() {
    if (empty($this->sftpUrl)) {
      return str_replace('https://', '', str_replace('api', 'transfer', $this->baseUrl));
    }
    return $this->sftpUrl;
  }

  /**
   * @param string $sftpUrl
   */
  public function setSftpUrl($sftpUrl) {
    $this->sftpUrl = $sftpUrl;
  }

  protected $dateFormat = null;
  protected $username   = null;
  protected $password   = null;
  protected $sessionId  = null;

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
   * @param string $username
   * @param string $password
   *
   * @throws SilverpopConnectorException
   */
  public function authenticate($username = NULL, $password = NULL) {
    if ($this->sessionId) {
      return;
    }
    $client = $this->getClient();

    $this->username = empty($username) ? $this->username : $username;
    $this->password = empty($password) ? $this->password : $password;

    $params = "<Envelope>
  <Body>
    <Login>
      <USERNAME>{$this->username}</USERNAME>
      <PASSWORD>{$this->password}</PASSWORD>
    </Login>
  </Body>
</Envelope>";
    $response = $client->request('POST', 'XMLAPI', array('form_params' => array('xml' => $params)));
    $result = $this->checkResponse($response->getBody()->getContents());

    $this->sessionId = (string) $result->Body->RESULT->SESSIONID;
  }

  /**
   * Calculate the Current Contacts for a Query
   *
   * This interface supports programmatically calculating the number of
   * contacts for a query. A data job is submitted to calculate the query
   * and GetJobStatus must be used to determine whether the data job is complete.
   *
   * You may only call the Calculate Query data job for a particular query if
   * you have not calculated the query size in the last 12 hours.
   *
   * @param $params
   *  - queryId int ID of the query or list you wish to retrieve.
   *  - email string Email to notify on success (optional).
   *
   * @return array
   */
  public function calculateQuery($params) {
    $template = new CalculateQuery($params);
    $params = $template->getXml();
    $result = $this->post($params);
    return $template->formatResult($result);
  }

  /**
   * Get the criteria used for a query list.
   *
   * @param $params
   *  - listId int ID of the list you wish to retrieve.
   *
   * @return array
   */
  public function getQuery($params) {
    $template = new getQuery($params);
    $params = $template->getXml();
    $result = $this->post($params);
    return $template->formatResult($result);
  }

  /**
   * Download a file from the sftp server.
   *
   * @param string $fileName
   * @param string $destination
   *   Full path of where to save it to.
   *
   * Sample code:
   *
   * $status = $this->silverPop->getJobStatus($this->getJobStatus();
   *   if ($status === 'COMPLETE')) {
   *     $file = $result->downloadFile();
   *   }
   *
   * @return bool
   */
  public function downloadFile($fileName, $destination) {
    $sftp = new SFTP($this->getSftpUrl());
    if (!$sftp->login($this->username, $this->password)) {
      throw new Exception('Login Failed');
    }
    $sftp->get('download/' . $fileName, $destination);
    $sftp->delete('download/' . $fileName);
    fopen($destination . '.complete', 'c');
    return TRUE;
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
    foreach ($columns as $index => $column) {
      if (in_array($column, $columnsToIgnore)) {
        unset($columns[$index]);
      }
    }
    $params = array(
      'listId' => $listId,
      'exportType' => $type,
      'exportFormat' =>$format,
      'listDateFormat' => $this->dateFormat,
      'columns' => $columns,
    );
    if (!empty($startDate)) {
      $params['startTimestamp'] = $startDate;
    }
    if (!empty($endDate)) {
      $params['endTimestamp'] = $endDate;
    }
    $template = new ExportList($params);
    $params = $template->getXml();
    $result = $this->post($params);
    $result = $template->formatResult($result);

    return array(
      'jobId'    => $result->JOB_ID,
      'filePath' => $result->FILE_PATH,
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
   * @param array $fields An array of $key => $value to add to the query, like
   *                      INCLUDE_ALL_LISTS, INCLUDE_TAGS or FOLDER_ID
   *
   * @return array Returns an array of SimpleXmlElement objects, one for each list
   * @throws SilverpopConnectorException
   */
  public function getLists($fields = array()) {
    // Get private lists
    $params = "<GetLists>
  <VISIBILITY>0</VISIBILITY>
  <LIST_TYPE>2</LIST_TYPE>\n";
        foreach ($fields as $key => $value) {
            // e.g. 'INCLUDE_ALL_LISTS' => 'true'
            $key = strtoupper($key); //SK 20140203
            $params .= "\t<{$key}>{$value}</{$key}>\n";
        }
        $params .= "\t</GetLists>\n";
    $params = new SimpleXmlElement($params);
    $result = $this->post($params);
    $lists = array();
    foreach ($result->Body->RESULT->LIST as $list) {
      $lists[] = $list;
    }

    // Get shared lists
    $params = "<GetLists>
  <VISIBILITY>1</VISIBILITY>
  <LIST_TYPE>2</LIST_TYPE>\n";
        foreach ($fields as $key => $value) {
            // e.g. 'INCLUDE_ALL_LISTS' => 'true'
            $key = strtoupper($key); //SK 20140203
            $params .= "\t<{$key}>{$value}</{$key}>\n";
        }
        $params .= "\t</GetLists>\n";
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

    $params = "<GetMailingTemplates>";
    if (!empty($lastModifiedStart)) $params .= "\n\t<LAST_MODIFIED_TIME_START>".date('m/d/Y H:i:s', $lastModifiedStart)."</LAST_MODIFIED_TIME_START>";
    if (!empty($lastModifiedEnd)) $params .= "\n\t<LAST_MODIFIED_TIME_END>".date('m/d/Y H:i:s', $lastModifiedEnd)."</LAST_MODIFIED_TIME_END>";
    $params .= "\n</GetMailingTemplates>";

    $params = new SimpleXmlElement($params);

    $result =$this->post($params);
    $modifiedMailings = array();
    foreach ($result->Body->RESULT->MAILING_TEMPLATE as $mailing) {
      $modifiedMailings[] = $mailing;
    }
    return $modifiedMailings;
  }

  /**
   * Get mailing template.
   *
   * @param array $params
   *
   * @return SimpleXmlElement
   */
  public function getMailingTemplate($params) {
    $template = new GetMailingTemplate($params);
    $xml = $template->getXml();
    $result = $this->post($xml);
    return $template->formatResult($result);
  }

  /**
   * Get aggregate tracking information for a mailing.
   *
   * This includes summary data about the number sent etc.
   *
   * @param array $params
   * @return SimpleXmlElement
   */
  public function getAggregateTrackingForMailing($params) {
    $template = new GetAggregateTrackingForMailing($params);
    $xml = $template->getXml();
    $result = $this->post($xml);
    return $template->formatResult($result);
  }

  /**
   * Create a contact list - CreateContactList.
   *
   * @param array $params
   *
   * @return SimpleXmlElement
   *
   * @throws \SilverpopConnector\SilverpopConnectorException
   */
  public function createContactList($params): SimpleXmlElement {
    $template = new CreateContactList($params);
    $xml = $template->getXml();
    $result = $this->post($xml);
    return $template->formatResult($result);
  }

  /**
   * Adds contact/s to an Acoustic Campaign contact list.
   *
   * @param array $params
   *
   * @see https://developer.goacoustic.com/acoustic-campaign/reference/addcontacttocontactlist
   *
   * @return SimpleXmlElement
   *
   * @throws \SilverpopConnector\SilverpopConnectorException
   */
  public function addContactToContactList($params): bool {
    $template = new AddContactToContactList($params);
    $xml = $template->getXml();
    $result = $this->post($xml);
    return $template->formatResult($result);
  }

  /**
   * Get a list of mailings modified within the specified time range.
   *
   * @param int $dateStart An integer timestamp
   * @param int $dateEnd   An integer timestamp
   * @param string|array  $flags  A single flag or an array of optional flags
   *
   * @return array Returns an array of SimpleXmlElement objects, one for each mailing
   * @throws SilverpopConnectorException
   */
  public function getSentMailingsForOrg($dateStart=0, $dateEnd=0, $flags=null) {

    $params = array();
    if (!empty($dateStart)) {
      $params['startTimestamp'] = $dateStart;
    }
    if (!empty($dateEnd)) {
      $params['endTimestamp'] = $dateEnd;
    }

    //flags: e.g. EXCLUDE_TEST_MAILINGS, SENT, EXCLUDE_ZERO_SENT
    if (!empty($flags)) {
      if (!is_array($flags)) {
        $flags = array($flags);
      }
      //validation: remove anything not a letter/underscore, make uppercase.
      foreach($flags as $i=>$flag) {
        $flag = preg_replace("/[^A-Za-z]/", '', $flag);
        $params[$flag] = TRUE;
      }
    }
    $template = new GetSentMailingsForOrg($params);
    $params = $template->getXml();
    $result = $this->post($params);
    return $template->formatResult($result);
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
   * Performs Silverpop Logout so concurrent requests can take place
   *
   * @throws SilverpopConnectorException
   */
  public function logout() {
    if (!$this->sessionId) {
      return;
    }
    $client = $this->getClient();
    $response = $this->post(new SimpleXmlElement("<Logout/>"));

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
   *   string content of no output path provided
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
   *
   * @return SimpleXmlElement
   * @throws SilverpopConnectorException
   */
  public function updateRecipient($listId, $recipientId, $fields, $optParams = []) {
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
      if ($key === 'snoozeDate') {
        $params .='<SNOOZE_SETTINGS><SNOOZED>true</SNOOZED><RESUME_SEND_DATE>' . $value . '</RESUME_SEND_DATE></SNOOZE_SETTINGS>';
        continue;
      }
      $params .= "\t<{$key}>{$value}</{$key}>\n";
    }

    foreach ($fields as $key => $value) {
      $params .= "\t<COLUMN>\n";
      $params .= "\t\t<NAME>{$key}</NAME>\n";
      $params .= "\t\t<VALUE>{$value}</VALUE>\n";
      $params .= "\t</COLUMN>\n";
    }
    $params .= '</UpdateRecipient>';
    $xml = new SimpleXmlElement($params);

    $result = $this->post($xml);
    $recipientId = $result->Body->RESULT->RecipientId;
    if (!preg_match('/^\d+$/', $recipientId)) {
      $recipientId = (int)$recipientId;
    }
    return $recipientId;
  }

  /**
   * //SK 20140220 RawRecipientDataExport all elements are optional...
   *
   * @param int or array   $mailingId  The ID (or associative array with TYPE => ID) of the mailing(s) - see Notes!
   * @param array  $dates  Optional array of dates with TYPE => DATE. TODO convert to mm/dd/yyyy hh:mm:ss when differently formatted
   * @param string or array  $flags  A single flag or an array of optional flags
   * @param array  $optParams  Associative array of optional params, e.g.:
   *   EXPORT_FORMAT (int), EMAIL (notification e-mail address), <RETURN_MAILING_NAME> (true) <RETURN_SUBJECT>true, RETURN_CRM_CAMPAIGN_ID>true
   * @param array  $listColumns  An associative array of unique/key columns to be included in the exported file
   * @return SimpleXmlElement
   * @throws SilverpopConnectorException
   *
   * Silverpop Note: MailingId - TYPE can be MAILING_ID, REPORT_ID, LIST_ID, CAMPAIGN_ID in varying combinations!!
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

    //SK date validation see http://stackoverflow.com/questions/3720977/preg-match-check-birthday-format-dd-mm-yyyy
    //check for date AND time or append time 00:00:00 when not provided
    //if strpos(space, datestring) date = substr(0,strpos),  time = substr(strpos) else date = datestring - append time
    //list($dd,$mm,$yyyy) = explode('/',$cnt_birthday);
    //if (!checkdate($mm,$dd,$yyyy)) {
        //  //convert to date
    //}

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
        $params .= "\t<{$dateType}>{$date}</{$dateType}>\n";
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
   * @param int  $listId  The ID of the DB/Contact list
   * @param array  $mainFields  An associative array of main fields to match (EMAIL, RECIPIENT_ID, VISITOR_KEY etc)
   * @param array  $customFields  An associative array of unique/key columns when required
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
   * //SK 20140205 Send mailing (Code by RR).
   *
   * @param string  $email  The email address to send the mailing to
   * @param int  $autoresponder  The ID of the Autoresponder
   *
   * @return SimpleXmlElement
   * @throws SilverpopConnectorException
   *
   */
  public function sendMailing($email, $autoresponder) {
    if (!preg_match('/^\d+$/', $autoresponder)) {
      $autoresponder = (int)$autoresponder;
    }

    $params = "<SendMailing>\n";
    $params .= "\t<MailingId>{$autoresponder}</MailingId>\n";
    $params .= "\t<RecipientEmail>{$email}</RecipientEmail>\n";
    $params .= "</SendMailing>";

    $params = new SimpleXmlElement($params);
    $result = $this->post($params);
    return $result->Body->RESULT;
  }

    /**
     * scheduleMailing
     *
     * Schedules an email to the specified list_id ($listId) using the template
     * $templateID. You can optionally include substitutions that will act on
     * the template to fill in dynamic bits of data.
     *
     * ## Example
     *
     * $silvepop->scheduleMailing(123, 456, "Example Mailing with unique name", time() + 60, array(
     *     'SUBSTITUTIONS' => array(
     *          array(
     *              'NAME' => 'FIELD_IN_TEMPLATE',
     *              'VALUE' => "Dynamic value to replace in template",
     *          ),
     *     )
     * ));
     *
     * @param int    $templateId         ID of template upon which to base the mailing.
     * @param int    $listId             ID of database, query, or contact list to send the template-based mailing.
     * @param string $mailingName        Name to assign to the generated mailing.
     * @param int    $scheduledTimestamp When the mailing should be scheduled to send. This must be later than the current timestamp.
     * @param array  $optionalElements   An array of $key => $value, where $key can be one of SUBJECT, FROM_NAME, FROM_ADDRESS, REPLY_TO, SUBSTITUTIONS
     * @param bool   $saveToSharedFolder
     *
     * @return SimpleXmlElement
     *
     * @throws SilverpopConnectorException
     *
     */
    public function scheduleMailing($templateId, $listId, $mailingName, $scheduledTimestamp, $optionalElements = array(), $saveToSharedFolder = 0, $suppressionLists = array()) {
        if (!preg_match('/^\d+$/', $templateId)) {
            $listId = (int) $templateId;
        }

        if (!preg_match('/^\d+$/', $listId)) {
            $listId = (int) $listId;
        }

        $scheduled = date("m/d/Y h:i:s A", $scheduledTimestamp);

        $saveToSharedFolder = $saveToSharedFolder ? '1' : '0';

        $suppressionLists = array_map('intval', $suppressionLists);

        $params = "<ScheduleMailing>
            <TEMPLATE_ID>{$templateId}</TEMPLATE_ID>
            <LIST_ID>{$listId}</LIST_ID>
            <MAILING_NAME>{$mailingName}</MAILING_NAME>
            <SEND_HTML>true</SEND_HTML>
            <SEND_TEXT>true</SEND_TEXT>
            <VISIBILITY>{$saveToSharedFolder}</VISIBILITY>
            <SCHEDULED>{$scheduled}</SCHEDULED>\n";

        foreach ($optionalElements as $key => $value) {
            $params .= "\t<{$key}>";
            $params .= "{$value}";
            $params .= "</{$key}>\n";
        }

        if (count($suppressionLists)) {
            $params .= "\t<SUPPRESSION_LISTS>\n";
            foreach($suppressionLists as $list) {
                $params .= "\t\t<SUPPRESSION_LIST_ID>{$list}</SUPPRESSION_LIST_ID>\n";
            }
            $params .= "\t</SUPPRESSION_LISTS>\n";
        }

        $params .= "</ScheduleMailing>";
        $params = new SimpleXmlElement($params);
        $result = $this->post($params);

        $mailingId = $result->Body->RESULT->MAILING_ID;
        if (!preg_match('/^\d+$/', $mailingId)) {
            $mailingId = (int) $mailingId;
        }

        return $mailingId;
    }

    /**
     * saveMailing
     *
     * Save a new or update an existing email email template to the specified list_id ($listId).
     *
     * ## Example
     *
     * $silverpop->saveMailing(123, 456, "Example Mailing with unique name", time() + 60, array(
     *     'SUBSTITUTIONS' => array(
     *          array(
     *              'NAME' => 'FIELD_IN_TEMPLATE',
     *              'VALUE' => "Dynamic value to replace in template",
     *          ),
     *     )
     * ));
     *
     * @param string   $mailingName        Name to assign to the mailing template.
     * @param string   $mailingSubject     Subject to assign to the mailing template.
     * @param string[] $mailingBodies      Bodies (html, text) to assign to the mailing template.
     * @param string   $mailingFromName    From name to assign to the mailing template.
     * @param string   $mailingFromEmail   From email to assign to the mailing template.
     * @param string   $mailingReplyTo     ReplyTo address to assign to the mailing template.
     * @param int      $listId             ID of database, query, or contact list being used for the mailing template.
     * @param int      $templateId         ID of template to update (null for new).
     * @param bool     $saveToSharedFolder
     * @param int      $trackingLevel      The tracking level for the messages.
     * @param array    $clickThroughs      An array of $key => $value, where $key can be one of ClickThroughName, ClickThroughURL, ClickThroughType
     *
     * @return SimpleXmlElement
     *
     * @throws SilverpopConnectorException
     *
     */
    public function saveMailing($mailingName, $mailingSubject, $mailingBodies, $mailingFromName, $mailingFromEmail, $mailingReplyTo, $listId, $templateId = null, $saveToSharedFolder = 1, $trackingLevel = 4, $clickThroughs = array()) {
        if (!is_null($templateId) && !preg_match('/^\d+$/', $templateId)) {
            $listId = (int) $templateId;
        }

        if (!preg_match('/^\d+$/', $listId)) {
            $listId = (int) $listId;
        }

        $saveToSharedFolder = $saveToSharedFolder ? '1' : '0';

        $params = "<SaveMailing>\n";
        $params .= "<Header>\n";
        $params .= "\t<MailingName><![CDATA[{$mailingName}]]></MailingName>\n";
        $params .= "\t<Subject><![CDATA[{$mailingSubject}]]></Subject>\n";
        $params .= "\t<FromName><![CDATA[{$mailingFromName}]]></FromName>\n";
        $params .= "\t<FromAddress><![CDATA[{$mailingFromEmail}]]></FromAddress>\n";
        $params .= "\t<ReplyTo><![CDATA[{$mailingReplyTo}]]></ReplyTo>\n";
        $params .= "\t<Visibility>{$saveToSharedFolder}</Visibility>\n";
        $params .= "\t<TrackingLevel>{$trackingLevel}</TrackingLevel>\n";
        $params .= "\t<Encoding>6</Encoding>\n"; // 6 = unicode (utf8)
        $params .= "\t<ListID><![CDATA[{$listId}]]></ListID>\n";

        if ($templateId) {
            $params .= "\t<MailingID>{$templateId}</MailingID>\n"; // 6 = unicode (utf8)
        }
        $params .= "</Header>\n";

        $params .= "<MessageBodies>\n";
        if (!empty($mailingBodies['html'])) {
            $params .= "\t<HTMLBody><![CDATA[{$mailingBodies['html']}]]></HTMLBody>\n";
        }
        if (!empty($mailingBodies['text'])) {
            $params .= "\t<TextBody><![CDATA[{$mailingBodies['text']}]]></TextBody>\n";
        }
        $params .= "</MessageBodies>\n";

        if (!empty($clickThroughs)) {
            $params .= "<ClickThroughs>\n";
            foreach ($clickThroughs as $clickThrough) {
                $params .= "\t<ClickThrough>\n";
                foreach ($clickThrough as $key => $value) {
                    if (!is_int($value)) {
                        $value = "<![CDATA[{$value}]]>";
                    }

                    $params .= "\t\t<{$key}>{$value}</$key>\n";
                }
                $params .= "\t</ClickThrough>\n";
            }
            $params .= "</ClickThroughs>\n";
        }

        $params .= "<ForwardToFriend>\n";
        $params .= "\t<ForwardType>0</ForwardType>\n";
        $params .= "</ForwardToFriend>\n";

        $params .= "</SaveMailing>";

        $params = new SimpleXmlElement($params);
        $result = $this->post($params);

        return $result->Body->RESULT;
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

    $response = $this->createXmlObject($xml);

    if (!isset($response->Body)) {
      throw new SilverpopConnectorException("No <Body> element on response: {$xml}");
    } elseif (!isset($response->Body->RESULT)) {
      throw new SilverpopConnectorException("No <RESULT> element on response body: {$xml}");
    } elseif (!isset($response->Body->RESULT->SUCCESS)) {
      throw new SilverpopConnectorException("No <SUCCESS> element on result: {$xml}");
    } elseif (strtolower($response->Body->RESULT->SUCCESS) != 'true') {
      throw new SilverpopConnectorException('Request failed: '. $response->Body->Fault->FaultString, (int) $response->Body->Fault->detail->error->errorid);
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
    $client = $this->getClient();
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
    $xmlParams = array('xml'=>$envelopeXml);

    $curlHeaders = array(
        'Content-Type: application/x-www-form-urlencoded',
        );
    // Use an oAuth token if there is one
    if ($accessToken = SilverpopRestConnector::getInstance()->getAccessToken()) {
      $curlHeaders[] = "Authorization: Bearer {$accessToken}";
      $url = 'XMLAPI';
    } else {
      // No oAuth, use jsessionid to authenticate
      $url = "XMLAPI;jsessionid={$this->sessionId}";
    }
    $response = $client->request('POST', $url, array('form_params' => $xmlParams, 'headers' => $curlHeaders));
    try {
      return $this->checkResponse($response->getBody()->getContents());
    }
    catch (\SilverpopConnector\SilverpopConnectorException $e) {
      if ($e->getCode() !== 145) {
        throw $e;
      }
      $this->sessionId = NULL;
      $this->authenticate();
      $url = "XMLAPI;jsessionid={$this->sessionId}";
      $response = $client->request('POST', $url, array('form_params' => $xmlParams, 'headers' => $curlHeaders));
      return $this->checkResponse($response->getBody()->getContents());
    }

  }

  /**
  * Create an xml object from the xml received.
  *
  * Loading simpleXml can fail too quietly. Next iteration could be to
  * use a different library or to create a specific exception.
  *
  * @param string $xml
  *
  * @return \SimpleXMLElement
  *
  * @throws \SilverpopConnector\SilverpopConnectorException
  */
  protected function createXmlObject($xml) {
    $use_internal_errors = libxml_use_internal_errors(TRUE);
    libxml_clear_errors();

    $response = simplexml_load_string($xml);
    if ($response === FALSE) {
      throw  new \SilverpopConnector\SilverpopConnectorException('invalid xml received: ' . $xml);
    }
    libxml_clear_errors();
    libxml_use_internal_errors($use_internal_errors);
    return $response;
  }

}
