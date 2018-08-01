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
   * Get start date in the format format "mm/dd/yyyy hh:mm:ss"
   * @return string
   */
  public function getDateStart() {
    if ($this->startTimestamp) {
      return date('m/d/Y H:i:s', $this->startTimestamp);
    }
    return $this->dateStart;
  }

  /**
   * @param string $dateStart
   */
  public function setDateStart($dateStart) {
    $this->dateStart = $dateStart;
  }

  /**
   * @return string
   */
  public function getStartTimestamp() {
    return $this->startTimestamp;
  }

  /**
   * @param string $startTimestamp
   */
  public function setStartTimestamp($startTimestamp) {
    $this->startTimestamp = $startTimestamp;
  }

  /**
   * @return string
   */
  public function getEndTimestamp() {
    return $this->endTimestamp;
  }

  /**
   * @param string $endTimestamp
   */
  public function setEndTimestamp($endTimestamp) {
    $this->endTimestamp = $endTimestamp;
  }

  /**
   * @return string
   */
  public function getDateEnd() {
    if ($this->endTimestamp) {
      return date('m/d/Y H:i:s', $this->endTimestamp);
    }
    return $this->dateEnd;
  }

  /**
   * @param string $dateEnd
   */
  public function setDateEnd($dateEnd) {
    $this->dateEnd = $dateEnd;
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
