<?php
require_once __DIR__.'/SilverpopConnectorException.php';

/**
 * This is a basic class for connecting to the Silverpop API
 * @author Mark French, Argyle Social
 */
class SilverpopConnector {
	protected static $instance = null;

	protected $baseUrl      = null;
	protected $clientId     = null;
	protected $clientSecret = null;
	protected $refreshToken = null;
	protected $accessToken  = null;

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
	 * @return SilverpopConnector
	 */
	public function __construct($baseUrl='http://api.pilot.silverpop.com/') {
		$this->baseUrl = $baseUrl;
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

		$params = array(
			'grant_type'    => 'refresh_token',
			'client_id'     => $this->clientId,
			'client_secret' => $this->clientSecret,
			'refresh_token' => $this->refreshToken,
			);
		
		$ch = curl_init();

		$curlParams = array(
			CURLOPT_URL            => 'https://pilot.silverpop.com/oauth/token',
			CURLOPT_FOLLOWLOCATION => 1,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST           => 1,
			CURLOPT_POSTFIELDS     => http_build_query($params),
			);
		$set = curl_setopt_array($ch, $curlParams);

		$resultStr = curl_exec($ch);
		curl_close($ch);
		$result = json_decode($resultStr, true);

		if (empty($result['access_token'])) {
			$msg = empty($result['error_code']) ? $resultStr : $result['error_description'];
			throw new SilverpopConnectorException($msg);
		}

		$this->accessToken = $result['access_token'];
	}

	/**
	 * Create a Universal Behavior event.
	 * 
	 * @param int    $typeCode   The event type ID
	 * @param string $timestamp  The time of the event (Use the date('c') format)
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
			throw new InvalidArgumentException("The provided timestamp '{$timestamp}' does not match the required format: ".date('c'));
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

		$eventsStr = json_encode(array('events'=>array($event)));
		$result = $this->post('rest/events/submission', $eventsStr);
		var_dump($result);
	}

	//////////////////////////////////////////////////////////////////////////
	// PROTECTED ////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////

	/**
	 * Send a POST request to the API
	 * 
	 * @param string $resource The URI for the requested resource (will be prefixed by baseUrl)
	 * @param string $params   JSON-encoded parameters to pass to the requested resource
	 *
	 * @return string Returns JSON-encoded data
	 * @throws SilverpopConnectorException
	 */
	protected function post($resource, $params = array()) {
		// Attempt to authenticate using cached credentials if not connected
		if (empty($this->accessToken)) {
			$this->authenticate();
		}

		$url = $this->baseUrl.$resource;
		$ch = curl_init();
		$curlParams = array(
			CURLOPT_URL            => $url,
			CURLOPT_FOLLOWLOCATION => 1,//true,
			CURLOPT_POST           => 1,//true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_POSTFIELDS     => $params,
			CURLOPT_RETURNTRANSFER => 1,//true,
			CURLOPT_HTTPHEADER     => array(
				'Content-Type: application/json',
				'Content-Length: '.strlen($params),
				"Authorization: Bearer {$this->accessToken}",
				),
			);
		var_dump($curlParams);
		curl_setopt_array($ch, $curlParams);

		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}
}
