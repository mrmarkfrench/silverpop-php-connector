<?php

namespace SilverpopConnector;

use SilverpopConnector\SilverpopConnectorException;
use SilverpopConnector\SilverpopRestConnector;
use SilverpopConnector\SilverpopXmlConnector;

/**
 * This is a basic class for connecting to the Silverpop API. It is able
 * to communicate with both the legacy XML API and the newer REST API.
 * For any given request, it will attempt to use the REST API if it is
 * available (that is, the requested resource is defined in the REST API
 * and a REST session is available). If not, it will fall back on the XML
 * API.
 *
 * If you would prefer to force a request to be handled by one specific
 * version of the API, you should use the SilverpopRestConnector or
 * SilverpopXmlConnector class to handle your request directly.
 *
 * @author Mark French, Argyle Social
 */
class SilverpopConnector {
  protected static $instance = null;
  protected SilverpopRestConnector $restConnector;
  protected SilverpopXmlConnector $xmlConnector;

  protected $baseUrl      = null;
  protected $dateFormat   = null;
  protected $timeout      = null;
  protected ?string $username;
  protected ?string $password;
  protected ?string $clientId;
  protected ?string $clientSecret;
  protected ?string $refreshToken;
  protected ?string $accessToken;
  protected array $postHeaders = [];
  protected array $curlOptions = [];
  /**
   * Construct a connector object. If you will be authenticating with only a
   * single set of credentials, it is recommended that you use the singleton
   * getInstance() method instead. Use this constructor if you require
   * multiple connector objects for more than one set of credentials.
   *
   * @param string $baseUrl The base API URL for all requests.
   * @param string $dateFormat Passed through to API requests to specify output format
   * @param float $timeout Timeout in seconds for API requests
   */
  public function __construct(string $baseUrl = 'http://api.pilot.silverpop.com', $dateFormat='MM/dd/yyyy', $timeout=10.0) {
    $this->restConnector = SilverpopRestConnector::getInstance();
    $this->xmlConnector  = SilverpopXmlConnector::getInstance();
    $this->setBaseUrl($baseUrl);
    $this->setDateFormat($dateFormat);
    $this->setTimeout($timeout);
  }

  /**
   * Passthrough to shunt requests to the appropriate connector object.
   * If a matching method is available through the REST API, it will be
   * utilitzed. If not, the XML API will be attempted instead.
   *
   * @param string $method
   * @param array  $args
   * @return mixed
   */
  public function __call($method, $args) {
    if (isset($this->restConnector)) {
      if ($this->restConnector->methodAvailable($method)) {
        return call_user_func_array(array($this->restConnector, $method), $args);
      }
    }
    if (isset($this->xmlConnector)) {
      return call_user_func_array(array($this->xmlConnector, $method), $args);
    }
    throw new SilverpopConnectorException("No authenticated connector available for call to {$method}. You must authenticate before calling API resource endpoints.");
  }

  /**
   * Get a singleton instance of the connector. If you will be
   * authenticating with only a single set of credentials, fetching a
   * singleton may be simpler for your code to manage than creating your
   * own instance object which you are required to manage by hand. If,
   * however, you need multiple connectors in order to connect with
   * different sets of credentials, you should call the constructor to
   * obtain individual SilverpopConnector objects.
   *
   * Note that this method is implemented with "static" not "self", so
   * if you extend the connector to add your own functionality, you can
   * continue to use the singleton provided by this method by calling
   * YourChildClassName::getInstance(), but you will need to provide a
   * "protected static $instance=null;" property in your child class
   * for this method to reference.
   *
   * @param string $baseUrl The base API URL for all requests.
   * @param string $dateFormat Passed through to API requests to specify output format
   * @param float $timeout Timeout in seconds for API requests
   *
   * @return SilverpopConnector
   */
  public static function getInstance($baseUrl='http://api.pilot.silverpop.com', $dateFormat='MM/dd/yyyy', $timeout=10.0) {
    if (static::$instance == null) {
      static::$instance = new static($baseUrl, $dateFormat, $timeout);
    }
    return static::$instance;
  }

  /**
   * Perform Silverpop authentication. If three arguments are supplied, will
   * be treated as a REST authentication. If only two arugments are supplied,
   * will instead be treated as XML authentication.
   */
  public function authenticate() {
    $method = (func_num_args()==3) ? 'authenticateRest' : 'authenticateXml';
    return call_user_func_array(array($this, $method), func_get_args());
  }

  /**
   * Performs Silverpop authentication using the supplied REST credentials,
   * or with the cached credentials if none are supplied. Any new credentials
   * will be cached for the next request.
   *
   * @param string $clientId
   * @param string $clientSecret
   * @param string $refreshToken
   *
   * @throws SilverpopConnectorException
   */
  public function authenticateRest($clientId=null, $clientSecret=null, $refreshToken=null) {
    $this->clientId     = empty($clientId)     ? $this->clientId     : $clientId;
    $this->clientSecret = empty($clientSecret) ? $this->clientSecret : $clientSecret;
    $this->refreshToken = empty($refreshToken) ? $this->refreshToken : $refreshToken;

    $this->restConnector = SilverpopRestConnector::getInstance();
    $this->restConnector->authenticate(
      $this->clientId,
      $this->clientSecret,
      $this->refreshToken);
  }

  /**
   * Performs Silverpop authentication using the supplied REST credentials,
   * or with the cached credentials if none are supplied. Any new credentials
   * will be cached for the next request.
   *
   * @param string $clientId
   * @param string $clientSecret
   * @param string $refreshToken
   *
   * @throws SilverpopConnectorException
   */
  public function authenticateXml($username=null, $password=null) {
    $this->username = empty($username) ? $this->username : $username;
    $this->password = empty($password) ? $this->password : $password;
    $this->xmlConnector = SilverpopXmlConnector::getInstance();
    $this->xmlConnector->authenticate(
      $this->username,
      $this->password);
  }

  public function setClientId(?string $clientId): self{
    $this->restConnector->setClientId($clientId);
    $this->xmlConnector->setClientId($clientId);
    return $this;
  }

  public function setClientSecret(?string $clientSecret): self{
    $this->restConnector->setClientSecret($clientSecret);
    $this->xmlConnector->setClientSecret($clientSecret);
    return $this;
  }

  public function setRefreshToken(?string $refreshToken): self{
    $this->restConnector->setRefreshToken($refreshToken);
    $this->xmlConnector->setRefreshToken($refreshToken);
    $this->refreshToken = $refreshToken;
    return $this;
  }

  public function setUsername(?string $username): SilverpopConnector {
    $this->restConnector->setUsername($username);
    $this->xmlConnector->setUsername($username);
    $this->username = $username;
    return $this;
  }

  public function setPassword(?string $password): SilverpopConnector {
    $this->restConnector->setPassword($password);
    $this->xmlConnector->setPassword($password);
    $this->password = $password;
    return $this;
  }

  /**
   * Set the base URL for API access.
   *
   * @param string $baseUrl
   */
  public function setBaseUrl($baseUrl) {
    // The URL needs a protocol, and SSL is preferred
    if (substr($baseUrl, 0, 4) != 'http') {
      $protocol = (stripos($baseUrl, 'api.pilot')===false) ? 'https' : 'http';
      $baseUrl = "{$protocol}://{$baseUrl}";
    }
    $this->baseUrl = $baseUrl;
    $this->restConnector->setBaseUrl($baseUrl);
    $this->xmlConnector->setBaseUrl($baseUrl);
  }

  /**
   * Set the date format.
   *
   * @param string $dateFormat
   */
  public function setDateFormat($dateFormat) {
    $this->dateFormat = $dateFormat;
    $this->restConnector->setDateFormat($dateFormat);
    $this->xmlConnector->setDateFormat($dateFormat);
  }

  /**
   * Set the timeout.
   *
   * @param float $timeout
   */
  public function setTimeout($timeout) {
    $this->timeout = $timeout;
    $this->restConnector->setTimeout($timeout);
    $this->xmlConnector->setTimeout($timeout);
  }

  /**
   * Set headers.
   *
   * @param array $headers
   */
  public function setPostHeaders(array $headers) {
    $this->postHeaders = $headers;
    $this->restConnector->setPostHeaders($headers);
    $this->xmlConnector->setPostHeaders($headers);
  }

  /**
   * Set curl options.
   *
   * @param array $curlOptions
   */
  public function setCurlOptions(array $curlOptions) {
    $this->curlOptions = $curlOptions;
    $this->restConnector->setCurlOptions($curlOptions);
    $this->xmlConnector->setCurlOptions($curlOptions);
  }

}
