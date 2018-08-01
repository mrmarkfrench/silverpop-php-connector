<?php

namespace SilverpopConnector\Xml;

use SimpleXmlElement;

/**
 * Created by IntelliJ IDEA.
 * User: emcnaughton
 * Date: 5/10/17
 * Time: 8:12 AM
 */
class GetAggregateTrackingForMailing extends XmlAction {

  /**
   * SilverPop mailing ID.
   *
   * @var string
   */
  protected $mailingId;

  /**
   * @var string
   */
  protected $reportId;

  /**
   * @return string
   */
  public function getReportId() {
    return $this->reportId;
  }

  /**
   * @param string $reportId
   */
  public function setReportId($reportId) {
    $this->reportId = $reportId;
  }

  /**
   * @return bool
   */
  public function isTopDomain() {
    return $this->topDomain;
  }

  /**
   * @param bool $topDomain
   */
  public function setTopDomain($topDomain) {
    $this->topDomain = $topDomain;
  }

  /**
   * @return bool
   */
  public function isInboxMonitoring() {
    return $this->inboxMonitoring;
  }

  /**
   * @param bool $inboxMonitoring
   */
  public function setInboxMonitoring($inboxMonitoring) {
    $this->inboxMonitoring = $inboxMonitoring;
  }

  /**
   * @return bool
   */
  public function isPerClick() {
    return $this->perClick;
  }

  /**
   * @param bool $perClick
   */
  public function setPerClick($perClick) {
    $this->perClick = $perClick;
  }

  /**
   * @var bool
   */
  protected $topDomain;

  /**
   * @var bool
   */
  protected $inboxMonitoring;

  /**
   * @var bool
   */
  protected $perClick;

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
    return '<GetAggregateTrackingForMailing/>';
  }

  /**
   * Get the xml pertaining to this request.
   *
   * @return \SimpleXmlElement
   */
  public function getXml() {
    $xmlObject = new SimpleXmlElement($this->getEnvelope());
    $xmlObject->addChild('MAILING_ID', $this->getMailingId());
    $xmlObject->addChild('REPORT_ID', $this->getReportId());
    if ($this->isInboxMonitoring()) {
      $xmlObject->addChild('INBOX_MONITORING', $this->isInboxMonitoring());
    }
    if ($this->isTopDomain()) {
      $xmlObject->addChild('TOP_DOMAIN', isTopDomain());
    }
    if ($this->isPerClick()) {
      $xmlObject->addChild('PER_CLICK', $this->isPerClick());
    }
    return $xmlObject;
  }

  public function formatResult($result) {
    return $result->Body->RESULT->Mailing;
  }
}
