<?php

namespace SilverpopConnector;

use SilverpopConnector\SilverpopBaseConnector;
use SilverpopConnector\SilverpopConnectorException;
use GuzzleHttp\Client;

/**
 * This is a basic class for connecting to the Silverpop API
 * @author Mark French, Argyle Social
 */
class SilverpopRestConnector extends SilverpopBaseConnector {
  protected static $instance = null;

  protected $retrievalParameters = [];

  /**
   * @var \GuzzleHttp\Client
   */
  protected $client;

  public $container = [];

  /**
   * @var \GuzzleHttp\Psr7\Request
   */
  public $request;

  /**
   * @param \GuzzleHttp\Client $client
   */
  public function setClient(\GuzzleHttp\Client $client) {
    $this->client = $client;
  }

  ///////////////////////////////////////////////////////////////////////////
  // PUBLIC ////////////////////////////////////////////////////////////////
  /////////////////////////////////////////////////////////////////////////

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
  public function authenticate($clientId=null, $clientSecret=null, $refreshToken=null) {
    $this->clientId     = empty($clientId)     ? $this->clientId     : $clientId;
    $this->clientSecret = empty($clientSecret) ? $this->clientSecret : $clientSecret;
    $this->refreshToken = empty($refreshToken) ? $this->refreshToken : $refreshToken;
    $this->getClient();
  }

  /**
   * Create a Universal Behavior event.
   *
   * @param int    $typeCode   The event type ID
   * @param string $timestamp  The time of the event (Use the date('Y-m-d\TH:i:s.000P') format)
   * @param array  $attributes An array of event attributes
   *
   * @throws InvalidArgumentException
   * @throws SilverpopConnectorException
   */
  public function createEvent($typeCode, $timestamp, $attributes) {
    if (empty($typeCode) || !is_numeric($typeCode)) {
      throw new InvalidArgumentException("The provided event type code '{$typeCode}' is either missing or not a number.");
    }
    if (date('Y-m-d\TH:i:s.000P', strtotime($timestamp)) != $timestamp) {
      throw new InvalidArgumentException("The provided timestamp '{$timestamp}' does not match the required format: ".date('Y-m-d\TH:i:s.000P'));
    }
    if (!is_array($attributes) || empty($attributes)) {
      throw new InvalidArgumentException("The 'attributes' supplied for the event are either empty or not an array.");
    }

    $xmlStyleAttributes = array();
    foreach ($attributes as $key => $value) {
      $xmlStyleAttributes[] = array(
        'name'  => $key,
        'value' => $value,
        );
    }

    $event = array(
      'eventTypeCode'  => $typeCode,
      'eventTimestamp' => $timestamp,
      'attributes'     => $xmlStyleAttributes,
      );

    $events = array('events'=>array($event));
    $result = $this->post('rest/events/submission', $events);
    return $result;
  }

  /**
   * Send a POST request to the API
   *
   * @param string $resource The URI for the requested resource (will be prefixed by baseUrl)
   * @param array  $params   Parameters to pass to the requested resource
   *
   * @return string Returns JSON-encoded data
   * @throws SilverpopConnectorException
   */
  protected function post($resource, $params=array()) {
    $client = $this->getClient(); // returns a Guzzle client with base_uri + OAuth
    $resource = ltrim($resource, '/'); // use relative path if base_uri is set on the client
    $response = $client->post($resource, [
      'json' => $params,                  // <-- pass array; Guzzle encodes to JSON
      'allow_redirects' => ['max' => 3],
      'connect_timeout' => (float) $this->timeout,
      'headers' => [
        'Accept' => 'application/json',
      ],
    ]);

    $result = (string) $response->getBody();
    return $result;
  }

  /**
   * Acoustic Rest request.
   *
   * This request calls a rest url as documented at https://api-campaign-us-4.goacoustic.com/restdoc/#!/databases
   * It is a skinny layer that calls the relevant url.
   * e.g the url is likely to look like
   *
   * /databases/{databaseId}/contacts/{contactid}/messages
   * In this example databases is the category, databaseId is the identifier
   * and the path will be ['contact', $contactId, 'messages']
   *
   * @param int|string $identifier
   *   The database ID or other, rest call specific, identifier.
   * @param string $category
   * @param array $path
   *
   * @return array
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \SilverpopConnector\SilverpopConnectorException
   */
  public function restGet($identifier, string $category, array $path) {
    $client = $this->getClient();
    $response = $client->request('GET',
      $this->baseUrl . '/rest/' . $category . '/' . $identifier . '/' . implode('/', $path));
    $content = $response->getBody()->getContents();
    return json_decode($content, 1);
  }

  /**
   * GDPR data request.
   *
   * @param $params array containing:
   *  - database_id
   *  - data -  Array of data to access gdpr information for. e.g
   *   [['Email', 'email@example.com'],['Email', 'another@example.com']]
   *
   * @return array
   */
  public function gdpr_access($params) {
    return $this->postCsvData('rest/databases/' . $params['database_id'] . '/gdpr_access', $params);
  }

  /**
   * GDPR Erasure.
   *
   * https://developer.ibm.com/customer-engagement/tutorials/performing-gdpr-right-erasure-wca-apis/
   *
   * @param $params array containing:
   *  - database_id
   *  - data -  Array of data to access gdpr information for. e.g
   *   [['Email', 'email@example.com'],['Email', 'another@example.com']]
   *  - retry_delay - optional integer for how long to wait between attempts to check for a response, default 1.
   *
   * @return array
   */
  public function gdpr_erasure($params) {
    return $this->postCsvData('rest/databases/' . $params['database_id'] . '/gdpr_erasure', $params);
  }

  /**
   * Send a POST request to the API
   *
   * @param string $resource The URI for the requested resource (will be prefixed by baseUrl)
   * @param array  $params   Parameters to pass to the requested resource
   *
   * @return array
   * @throws SilverpopConnectorException
   */
  protected function postCSVData($resource, $params = []) {
    $client = $this->getClient();
    if (isset($params['retrieval_parameters']['fetch_url'])) {
      $fetchUrl = $params['retrieval_parameters']['fetch_url'];
    }
    else {
      $fetchUrl = $this->requestEraseJob($resource, $params, $client, []);
    }

    for ($x = 0; $x <= 5; $x++) {
      $response = $client->request('GET',
        $fetchUrl);
      $content = $response->getBody()->getContents();
      $body = json_decode($content, 1);
      if (!isset($body['data']['status'])) {
        // We have retrieved it.
        break;
      }
      else {
        $body['data']['database_id'] = $params['database_id'];
        $body['data']['fetch_url'] = $fetchUrl;
      }
      if (!isset($params['retry_delay']) || $params['retry_delay'] > 0) {
        sleep($params['retry_delay'] ?? 1);
      }

    }

    return $body;

  }

  /**
   * Send out an erase request.
   *
   * @param $resource
   * @param $params
   * @param $client
   * @param $headers
   *
   * @return array
   */
  protected function requestEraseJob($resource, $params, $client, $headers) {
    // If we have a data array then we create the csv in memory & send. Intended for lower volume
    if (isset($params['data'])) {
      $filePath = 'php://memory';
      $body = fopen($filePath, 'w+');
      foreach ($params['data'] as $row) {
        fputcsv($body, $row);
      }
      rewind($body);
    }
    else {
      $body = fopen($params['csv'], 'r');
    }

    $response = $client->request('POST',
      $this->baseUrl . '/' . $resource, [
        'headers' => array_merge($headers, ['content-type' => 'text/csv']),
        'filename' => 'upload.csv',
        'body' => $body,
      ]);

    $content = $response->getBody()->getContents();
    $contentArray = json_decode($content, TRUE);
    return $contentArray['data']['location'];
  }

}
