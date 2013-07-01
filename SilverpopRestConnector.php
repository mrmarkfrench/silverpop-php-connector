<?php
require_once __DIR__.'/SilverpopBaseConnector.php';
require_once __DIR__.'/SilverpopConnectorException.php';

/**
 * This is a basic class for connecting to the Silverpop API
 * @author Mark French, Argyle Social
 */
class SilverpopRestConnector extends SilverpopBaseConnector {
	protected static $instance = null;

	// Authentication data
	protected $baseUrl            = null;
	protected $clientId           = null;
	protected $clientSecret       = null;
	protected $refreshToken       = null;
	protected $accessToken        = null;
	protected $accessTokenExpires = null;

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
		
		$url = $this->baseUrl.'/oauth/token';
		$ch = curl_init();

		$curlParams = array(
			CURLOPT_URL            => $url,
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
		$this->accessTokenExpires = time() + $result['expires_in'];
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
	 * Get the currently set access token from the connector. If none exists,
	 * or the existing token has expired, an authentication request will be
	 * attempted on your behalf using cached credentials. Will return either an
	 * access token or NULL, if none was available and authentication failed
	 * (due to either bad or missing cached credentials).
	 * 
	 * @return string
	 */
	public function getAccessToken() {
		if (empty($this->accessToken) || $this->tokenExpired()) {
			try {
				$this->authenticate();
			} catch (SilverpopConnectorException $sce) {}
		}
		return $this->accessToken;
	}

	/**
	 * Get the value of when the current access token will expire. If there is
	 * no current access token, or if an expiry was never set for it, this
	 * value will be NULL.
	 * 
	 * @return int Returns a UNIX timestamp
	 */
	public function getAccessTokenExpiry() {
		return $this->accessTokenExpires;
	}

	/**
	 * Checks if the specified method exists on this class and that we are
	 * authenticated to call it.
	 * 
	 * @param string $method
	 * @return bool
	 */
	public function methodAvailable($method) {
		return (!empty($this->accessToken) && method_exists($this, $method));
	}

	/**
	 * Set the access token used to authenticate connections. Use this method
	 * to set a pre-existing access token that has not yet expired, in order to
	 * avoid re-authenticating.
	 * 
	 * @param string $accessToken
	 * @param int    $expiry      Timestamp for when the token expires
	 */
	public function setAccessToken($accessToken, $expiry=null) {
		if (!empty($expiry) && !is_int($expiry)) {
			$expiry = strtotime($expiry);
		}
		$this->accessToken        = $accessToken;
		$this->accessTokenExpires = $expiry;
	}

	/**
	 * Sets the parameters to perform an authentication, but does not make the
	 * request. Use this method to provide the necessary authentication
	 * information if you are supplying a pre-existing access token and want to
	 * make certain a new one can be generated if it expires.
	 * 
	 * @param string $clientId
	 * @param string $clientSecret
	 * @param string $refreshToken
	 */
	public function setAuthParams($clientId, $clientSecret, $refreshToken) {
		$this->clientId     = $clientId;
		$this->clientSecret = $clientSecret;
		$this->refreshToken = $refreshToken;
	}

	//////////////////////////////////////////////////////////////////////////
	// PROTECTED ////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////

	/**
	 * Is the current access token expired? If it is expired (or will expire
	 * within the next 5 seconds), we should request a new token rather than
	 * using the current one.
	 * 
	 * @return bool
	 */
	protected function tokenExpired() {
		// If no expiry was specified, assume the token is still good
		if (empty($this->accessTokenExpires)) {
			return false;
		}
		// Allow a few seconds of buffer to account for communication delay.
		return $this->accessTokenExpires <= (time()+5);
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
		// Get the token, and attempt to re-authenticate if needed.
		$accessToken = $this->getAccessToken();
		if (empty($accessToken)) {
			throw new SilverpopConnectorException('Unable to authenticate request.');
		}

		$url = $this->baseUrl.'/'.$resource;
		$ch = curl_init();
		$params = json_encode($params);
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
				"Authorization: Bearer {$accessToken}",
				),
			);
		curl_setopt_array($ch, $curlParams);

		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}
}
