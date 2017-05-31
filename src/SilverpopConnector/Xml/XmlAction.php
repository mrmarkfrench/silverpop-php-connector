<?php

namespace SilverpopConnector\Xml;

/**
 * Created by IntelliJ IDEA.
 * User: emcnaughton
 * Date: 5/10/17
 * Time: 8:23 AM
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

}
