<?php

namespace SilverpopConnector\Xml;

use SimpleXmlElement;

/**
 * Created by IntelliJ IDEA.
 * User: emcnaughton
 * Date: 5/10/17
 * Time: 8:12 AM
 */
class CalculateQuery extends XmlAction {

  /**
   * SilverPop Query ID.
   *
   * @var string
   */
  protected $queryId;

  /**
   * Email to send notification to.
   *
   * @var string
   */
  protected $email;

  /**
   * Get email to notify on completion.
   *
   * @return string
   */
  public function getEmail() {
    return $this->email;
  }

  /**
   * Set email to notify on completion.
   *
   * @param string $email
   */
  public function setEmail($email) {
    $this->email = $email;
  }

  /**
   * Get Silverpop Query ID.
   *
   * @return mixed
   */
  public function getQueryId() {
    return $this->queryId;
  }

  /**
   * Set Silverpop Query ID.
   *
   * @param mixed $queryId
   */
  public function setQueryId($queryId) {
    $this->queryId = $queryId;
  }

  /**
   * Get the xml header element.
   *
   * @return string
   */
  public function getEnvelope() {
    return '<CalculateQuery/>';
  }

  /**
   * Get the xml pertaining to this request.
   *
   * @return \SimpleXmlElement
   */
  public function getXml() {
    $xmlObject = new SimpleXmlElement($this->getEnvelope());
    $xmlObject->addChild('QUERY_ID', $this->getQueryId());
    $xmlObject->addChild('EMAIL', $this->getEmail());
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
    return $result->Body->RESULT->Mailing;
  }
}
