<?php

namespace SilverpopConnector\Xml;

use SimpleXmlElement;

/**
 * Created by IntelliJ IDEA.
 * User: emcnaughton
 * Date: 5/10/17
 * Time: 8:12 AM
 */
class GetSentMailingsForOrg extends XmlAction {

  /**
   * @var string Start date.
   *
   * Starting Date in the format "mm/dd/yyyy hh:mm:ss"
   */
  protected $dateStart;

  /**
   * @var string
   *
   * Timestamp for start date. This will be used if set, else $dateStart is used.
   */
  protected $startTimestamp;

  /**
   * @var string
   *
   * Timestamp for end date. This will be used if set, else $dateEnd is used.
   */
  protected $endTimestamp;

  /**
   * @var string End date.
   *
   * Ending Date in the format "mm/dd/yyyy hh:mm:ss"
   */
  protected $dateEnd;

  /**
   * @var bool
   *
   * Optional parameter to retrieve private mailings. If the API does not receive a Private
   * or Shared parameter, Engage will return both private and shared mailings.
   */
  protected $private;

  /**
   * @var bool
   *
   * Optional parameter to retrieve shared mailings.
   */
  protected $shared;

  /**
   * @var bool
   *
   * Optional Mailing Type parameter to retrieve scheduled mailings. If the API
   * does not receive a mailing type, Engage will return mailings of all types.
   * Engage uses the various mailing type parameters to limit the list to only
   * the specified types.
   */
  protected $scheduled;

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
   * @return bool
   */
  public function isPrivate() {
    return $this->private;
  }

  /**
   * @param bool $private
   */
  public function setPrivate($private) {
    $this->private = $private;
  }

  /**
   * @return bool
   */
  public function isShared() {
    return $this->shared;
  }

  /**
   * @param bool $shared
   */
  public function setShared($shared) {
    $this->shared = $shared;
  }

  /**
   * @return bool
   */
  public function isScheduled() {
    return $this->scheduled;
  }

  /**
   * @param bool $scheduled
   */
  public function setScheduled($scheduled) {
    $this->scheduled = $scheduled;
  }

  /**
   * @return bool
   */
  public function isSent() {
    return $this->sent;
  }

  /**
   * @param bool $sent
   */
  public function setSent($sent) {
    $this->sent = $sent;
  }

  /**
   * @return bool
   */
  public function isSending() {
    return $this->sending;
  }

  /**
   * @param bool $sending
   */
  public function setSending($sending) {
    $this->sending = $sending;
  }

  /**
   * @return bool
   */
  public function isOptinConfirmation() {
    return $this->optinConfirmation;
  }

  /**
   * @param bool $optinConfirmation
   */
  public function setOptinConfirmation($optinConfirmation) {
    $this->optinConfirmation = $optinConfirmation;
  }

  /**
   * @return bool
   */
  public function isProfileConfirmation() {
    return $this->profileConfirmation;
  }

  /**
   * @param bool $profileConfirmation
   */
  public function setProfileConfirmation($profileConfirmation) {
    $this->profileConfirmation = $profileConfirmation;
  }

  /**
   * @return bool
   */
  public function isAutomated() {
    return $this->automated;
  }

  /**
   * @param bool $automated
   */
  public function setAutomated($automated) {
    $this->automated = $automated;
  }

  /**
   * @return bool
   */
  public function isCampaignActive() {
    return $this->campaignActive;
  }

  /**
   * @param bool $campaignActive
   */
  public function setCampaignActive($campaignActive) {
    $this->campaignActive = $campaignActive;
  }

  /**
   * @return bool
   */
  public function isCampaignCompleted() {
    return $this->campaignCompleted;
  }

  /**
   * @param bool $campaignCompleted
   */
  public function setCampaignCompleted($campaignCompleted) {
    $this->campaignCompleted = $campaignCompleted;
  }

  /**
   * @return bool
   */
  public function isCampaignCancelled() {
    return $this->campaignCancelled;
  }

  /**
   * @param bool $campaignCancelled
   */
  public function setCampaignCancelled($campaignCancelled) {
    $this->campaignCancelled = $campaignCancelled;
  }

  /**
   * @return bool
   */
  public function isCampaignScrapeTemplate() {
    return $this->campaignScrapeTemplate;
  }

  /**
   * @param bool $campaignScrapeTemplate
   */
  public function setCampaignScrapeTemplate($campaignScrapeTemplate) {
    $this->campaignScrapeTemplate = $campaignScrapeTemplate;
  }

  /**
   * @return bool
   */
  public function isIncludeTags() {
    return $this->includeTags;
  }

  /**
   * @param bool $includeTags
   */
  public function setIncludeTags($includeTags) {
    $this->includeTags = $includeTags;
  }

  /**
   * @return bool
   */
  public function isExcludeZeroSent() {
    return $this->excludeZeroSent;
  }

  /**
   * @param bool $excludeZeroSent
   */
  public function setExcludeZeroSent($excludeZeroSent) {
    $this->excludeZeroSent = $excludeZeroSent;
  }

  /**
   * @return bool
   */
  public function isMailingCountOnly() {
    return $this->mailingCountOnly;
  }

  /**
   * @param bool $mailingCountOnly
   */
  public function setMailingCountOnly($mailingCountOnly) {
    $this->mailingCountOnly = $mailingCountOnly;
  }

  /**
   * @return bool
   */
  public function isExcludeTestMailings() {
    return $this->excludeTestMailings;
  }

  /**
   * @param bool $excludeTestMailings
   */
  public function setExcludeTestMailings($excludeTestMailings) {
    $this->excludeTestMailings = $excludeTestMailings;
  }

  /**
   * @var bool
   *
   * Optional Mailing Type parameter to retrieve sent mailings.
   */
  protected $sent;

  /**
   * @var bool
   *
   * Optional Mailing Type parameter to retrieve mailings in the process of sending.
   * The SCHEDULED parameter will also include mailings in SENDING status.
   */
  protected $sending;

  /**
   * @var bool
   *
   * Optional Mailing Type parameter to retrieve mailings cancelled after
   * some were sent.
   */
  protected $sentCancelled;

  /**
   * @return bool
   */
  public function isSentCancelled(): bool {
    return (bool) $this->sentCancelled;
  }

  /**
   * Set whether mails that started and were then cancelled should be retrieved.
   * @param bool $sentCancelled
   *
   * @return GetSentMailingsForOrg
   */
  public function setSentCancelled(bool $sentCancelled): GetSentMailingsForOrg {
    $this->sentCancelled = $sentCancelled;
    return $this;
  }

  /**
   * @var bool
   *
   * Optional Mailing Type parameter to retrieve Opt-In Autoresponder mailings.
   */
  protected $optinConfirmation;

  /**
   * @var bool
   *
   * Optional Mailing Type parameter to retrieve Edit Profile Autoresponder mailings.
   */
  protected $profileConfirmation;

  /**
   * @var bool
   *
   * Optional Mailing Type parameter to retrieve Custom Autoresponder mailings.
   */
  protected $automated;

  /**
   * @var bool
   *
   * Optional Mailing Type parameter to retrieve active Groups of Automated Messages.
   */
  protected $campaignActive;

  /**
   * @var bool
   *
   * Optional Mailing Type parameter to retrieve completed Groups of Automated Messages.
   */
  protected $campaignCompleted;

  /**
   * @var bool
   *
   * Optional Mailing Type parameter to retrieve canceled Groups of Automated Messages.
   */
  protected $campaignCancelled;

  /**
   * @var bool
   *
   * Optional Mailing Type parameter to retrieve mailings that use content retrieval.
   */
  protected $campaignScrapeTemplate;

  /**
   * @var bool
   *
   * Optional parameter to return all Tags associated with the Sent mailing.
   */
  protected $includeTags;

  /**
   * @var bool
   *
   * Optional parameter to exclude mailings with no contacts.
   */
  protected $excludeZeroSent;

  /**
   * @var bool
   *
   * Optional parameter to return only the count of sent mailings for a specific date range.
   */
  protected $mailingCountOnly;

  /**
   * @var bool
   *
   * Optional parameter requesting to exclude Test Mailings.
   * If you do not provide this element, Engage will include all Test Mailings.
   */
  protected $excludeTestMailings;

  /**
   * Get the outer layer of the xml request.
   *
   * @return string
   */
  public function getEnvelope() {
    return '<GetSentMailingsForOrg/>';
  }

  public function getXml() {
    $xmlObject = new SimpleXmlElement($this->getEnvelope());
    foreach ($this->getFields() as $key => $value) {
      if ($this->isBooleanField($key)) {
        $xmlObject->addChild($key);
      }
      else {
        $xmlObject->addChild($key, $value);
      }
    }
    return $xmlObject;
  }

  /**
   * Get the fields to convert to xml.
   * @return array
   */
  protected function getFields() {
    $fields = array_merge(array(
      'DATE_START' => (string) $this->getDateStart(),
      'DATE_END' => (string) $this->getDateEnd(),
      ), $this->getBooleanFields()
    );
    foreach ($fields as $index => $field) {
      if (empty($field)) {
        unset ($fields[$index]);
      }
    }
    return $fields;
  }

  /**
   * Get boolean fields.
   *
   * We want to generate self-closed xml tags for these, e.g <SHARED/>.
   *
   * @return array
   */
  protected function getBooleanFields() {
    return array(
      'PRIVATE' => (bool) $this->isPrivate(),
      'SHARED' => (bool) $this->isShared(),
      'EXCLUDE_TEST_MAILINGS' => (bool) $this->isExcludeTestMailings(),
      'EXCLUDE_ZERO_SENT' => (bool) $this->isExcludeZeroSent(),
      'SENT' => (bool) $this->isSent(),
      'SENDING' => (bool) $this->isSending(),
      'SCHEDULED' => (bool) $this->isScheduled(),
      'SENT_CANCELLED' => (bool) $this->isSentCancelled(),
      'OPTIN_CONFIRMATION' => (bool) $this->isOptinConfirmation(),
      'PROFILE_CONFIRMATION' => (bool) $this->isProfileConfirmation(),
      'CAMPAIGN_ACTIVE' => (bool) $this->isCampaignActive(),
      'CAMPAIGN_COMPLETED' => (bool) $this->isCampaignCancelled(),
      'CAMPAIGN_SCRAPE_TEMPLATE' => (bool) $this->isCampaignScrapeTemplate(),
      'INCLUDE_TAGS' => (bool) $this->isIncludeTags(),
      'AUTOMATED' => (bool) $this->isAutomated(),
      'IS_MAILING_COUNT_ONLY' => (bool) $this->isMailingCountOnly(),
    );
  }

  /**
   * Is the field a boolean field.
   *
   * @param string $field
   * @return bool
   */
  protected function isBooleanField($field) {
    if (in_array($field, array_keys($this->getBooleanFields()))) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Format result to an array.
   *
   * @param \SimpleXmlElement $result
   *
   * @return array
   */
  public function formatResult($result) {
    $sentMailings = array();
    foreach ($result->Body->RESULT->Mailing as $mailing) {
      $sentMailings[] = $mailing;
    }
    return $sentMailings;
  }
}
