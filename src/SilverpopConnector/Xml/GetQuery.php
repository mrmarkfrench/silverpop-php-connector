<?php

namespace SilverpopConnector\Xml;

use SimpleXmlElement;

/**
 * Class to retrieve a query.
 */
class GetQuery extends XmlAction {

  /**
   * SilverPop List ID.
   *
   * @var string
   */
  protected $listId;

  /**
   * Get Silverpop Query ID.
   *
   * @return mixed
   */
  public function getListId() {
    return $this->listId;
  }

  /**
   * Set Silverpop Query ID.
   *
   * @param mixed $listId
   */
  public function setListId($listId) {
    $this->listId = $listId;
  }

  /**
   * Get the xml header element.
   *
   * @return string
   */
  public function getEnvelope() {
    return '<GetQuery/>';
  }

  /**
   * Get the xml pertaining to this request.
   *
   * @return \SimpleXmlElement
   */
  public function getXml() {
    $xmlObject = new SimpleXmlElement($this->getEnvelope());
    $xmlObject->addChild('LIST_ID', $this->getListId());
    return $xmlObject;
  }

  /**
   * Format the result.
   *
   * @param \SimpleXmlElement $result
   *
   * @return array
   */
  public function formatResult($result) {
    return $result->Body->RESULT;
  }
}
