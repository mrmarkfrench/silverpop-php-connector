<?php

namespace SilverpopConnector;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use SilverpopConnectorException;

/**
 * This is a basic class for connecting to the Silverpop API
 * @author Mark French, Argyle Social
 */
abstract class SilverpopBaseConnector {
  protected $baseUrl      = null;
  protected $dateFormat   = null;
  protected $timeout      = null;
  // Authentication data
  protected $clientId;
  protected $clientSecret;
  protected $refreshToken;
  protected $accessToken;
  protected $accessTokenExpires;
  private array $container = [];

  /**
   * @param mixed $refreshToken
   * @return SilverpopBaseConnector
   */
  public function setRefreshToken(?string $refreshToken) {
    $this->refreshToken = $refreshToken;
    return $this;
  }

  public function setClientId(?string $clientId): self{
    $this->clientId = $clientId;
    return $this;
  }

  public function setClientSecret(?string $clientSecret): self {
    $this->clientSecret = $clientSecret;
    return $this;
  }

  ///////////////////////////////////////////////////////////////////////////
  // MAGIC /////////////////////////////////////////////////////////////////
  /////////////////////////////////////////////////////////////////////////

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
  public function __construct($baseUrl='http://api.pilot.silverpop.com', $dateFormat='MM/dd/yyyy', $timeout=10.0) {
    $this->setBaseUrl($baseUrl);
    $this->setDateFormat($dateFormat);
    $this->setTimeout($timeout);
  }

  //////////////////////////////////////////////////////////////////////////
  // STATIC ///////////////////////////////////////////////////////////////
  ////////////////////////////////////////////////////////////////////////

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
   * @return SilverpopConnector
   */
  public static function getInstance() {
    if (static::$instance == null) {
      static::$instance = new static();
    }
    return static::$instance;
  }

  ///////////////////////////////////////////////////////////////////////////
  // PUBLIC ////////////////////////////////////////////////////////////////
  /////////////////////////////////////////////////////////////////////////

  /**
   * Performs Silverpop authentication using the supplied credentials,
   * or with the cached credentials if none are supplied. Any new credentials
   * will be cached for the next request.
   *
   * @throws SilverpopConnectorException
   */
  abstract public function authenticate();

  /**
   * Set the base URL used for API requests.
   *
   * @param string $baseUrl
   */
  public function setBaseUrl($baseUrl) {
    if (substr($baseUrl, -1) == '/') {
      $baseUrl = substr($baseUrl, 0, -1);
    }
    $this->baseUrl = $baseUrl;
  }

  /**
   * Set the date format.
   *
   * @param string $dateFormat
   */
  public function setDateFormat($dateFormat) {
    $this->dateFormat = $dateFormat;
  }

  /**
   * Set the timeout.
   *
   * @param float $timeout
   */
  public function setTimeout($timeout) {
    $this->timeout = $timeout;
  }

  /**
   * @return \GuzzleHttp\Client
   */
  public function getClient(): Client {
    if ($this->client) {
      return $this->client;
    }
    $stack = HandlerStack::create();
    // Accessible from tests...
    static $silverpopGuzzleContainer = Null;
    $silverpopGuzzleContainer = &$this->container;
    $tokenProvider = new TokenProvider($this->clientId, $this->clientSecret, $this->refreshToken, $this->baseUrl);
    $history = Middleware::history($this->container);
    $stack->push($history);
    // add Authorization header to every request
    $stack->push(Middleware::mapRequest(function (RequestInterface $req) use ($tokenProvider) {
      return $req->withHeader('Authorization', 'Bearer ' . $tokenProvider->get());
    }));

    // retry once on 401 after refreshing token
    $stack->push(Middleware::retry(function ($retries, $req, $res) use ($tokenProvider) {
      if ($retries >= 1) {
        return false;
      }
      if ($res && $res->getStatusCode() === 401) {
        $tokenProvider->refresh();
        return true;
      }
      return false;
    }));

    $this->setClient(new \GuzzleHttp\Client([
      'base_uri' => rtrim($this->baseUrl, '/') . '/',
      'timeout' => $this->timeout,
      'allow_redirects' => ['max' => 3],
      'handler' => $stack,
    ]));
    return $this->client;
  }

}
