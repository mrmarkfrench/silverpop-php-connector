<?php
require_once __DIR__.'/SilverpopConnectorException.php';

/**
 * This is a basic class for connecting to the Silverpop API
 * @author Mark French, Argyle Social
 */
class SilverpopConnector {
	protected $baseUrl      = null;
	protected $clientId     = null;
	protected $clientSecret = null;
	protected $refreshToken = null;
	protected $accessToken  = null;

	///////////////////////////////////////////////////////////////////////////
	// MAGIC /////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////

	public function __construct($baseUrl='https://pilot.silverpop.com/') {
		$this->baseUrl = $baseUrl;
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
		
		$url = $this->baseUrl.'oauth/token';
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
		var_dump($set, $curlParams);

		$resultStr = curl_exec($ch);
		curl_close($ch);
		$result = json_decode($resultStr, true);

		if (empty($result['access_token'])) {
			$msg = empty($result['error_code']) ? $resultStr : $result['error_description'];
			throw new SilverpopConnectorException($msg);
		}

		$this->accessToken = $result['access_token'];
	}

	//////////////////////////////////////////////////////////////////////////
	// PROTECTED ////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////

	/**
	 * Send a POST request to the API
	 * 
	 * @param string $resource The URI for the requested resource (will be prefixed by baseUrl)
	 * @param array  $params   Parameters to pass to the requested resource
	 * @return string Returns JSON-encoded data
	 * @throws SilverpopConnectorException
	 */
	protected function post($resource, $params = array()) {
		// Attempt to authenticate using cached credentials if not connected
		if (empty($this->accessToken)) {
			$this->authenticate();
		}

		$requestHeaders = array(
			'Authorization' => "Bearer {$this->accessToken}",
			);

		$url = $this->baseUrl.$resource;
		$ch = curl_init($url);

		$curlParams = array(
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_POST           => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_POSTFIELDS     => $params,
			CURLOPT_RETURNTRANSFER => true,
			);
		curl_setopt_array($ch, $curlParams);

		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}
}
