<?php

namespace SilverpopConnector\Xml;

/**
 * Created by IntelliJ IDEA.
 * User: emcnaughton
 * Date: 5/10/17
 * Time: 8:23 AM
 *
 * This class is the basis for the xml-generating classes. Xml generating classes should
 * offer all available fields as properties, formatted into camel case with no hyphens.
 *
 * Additional helpers may be offered - e.g for date fields offer timestamp instead for
 * convenience. Use names like startTimestamp for these properties.
 */
abstract class XmlAction {

	public function __construct($params) {
		foreach ($params as $key => $value) {
			if (method_exists($this, 'set' . $key)) {
				$this->{'set' . $key}($value);
			}
		}
	}

	/**
	 * Format the result.
	 *
	 * @param \SimpleXmlElement $result
	 *
	 * @return array
	 */
	abstract public function formatResult($result);

	abstract public function getEnvelope();

	abstract public function getXml();
}
