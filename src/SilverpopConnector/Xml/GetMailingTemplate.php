<?php

namespace SilverpopConnector\Xml;

use SimpleXmlElement;

/**
 * Created by IntelliJ IDEA.
 * User: emcnaughton
 * Date: 5/10/17
 * Time: 8:12 AM
 */
class GetMailingTemplate extends XmlAction {

  /**
   * SilverPop mailing ID.
   *
   * @var string
   */
  protected $mailingId;

  /**
   * Get Silverpop Mailing ID.
   *
   * @return mixed
   */
  public function getMailingId() {
    return $this->mailingId;
  }

  /**
   * Set Silverpop Mailing ID.
   *
   * @param mixed $mailingId
   */
  public function setMailingId($mailingId) {
    $this->mailingId = $mailingId;
  }

  public function getEnvelope() {
    return '<PreviewMailing/>';
  }

  public function getXml() {
    $xmlObject = new SimpleXmlElement($this->getEnvelope());
    $xmlObject->addChild('MailingId', $this->getMailingId());
    return $xmlObject;
  }

  public function formatResult($result) {
    return $result->Body->RESULT;
  }
}
