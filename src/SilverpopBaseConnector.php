<?php
require_once __DIR__.'/SilverpopConnectorException.php';

/**
 * This is a basic class for connecting to the Silverpop API
 * @author Mark French, Argyle Social
 */
abstract class SilverpopBaseConnector {
	protected $baseUrl      = null;

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
	public function __construct($baseUrl='http://api.pilot.silverpop.com') {
		$this->setBaseUrl($baseUrl);
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

	//////////////////////////////////////////////////////////////////////////
	// PROTECTED ////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////
}
