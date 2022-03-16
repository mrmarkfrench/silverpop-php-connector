<?php

namespace SilverpopConnector\Xml;

use SimpleXmlElement;

/**
 * Created by IntelliJ IDEA.
 * User: emcnaughton
 *
 *
 * @see https://developer.goacoustic.com/acoustic-campaign/reference/addcontacttocontactlist.
 */
class AddContactToContactList extends XmlAction {

  /**
   * The ID of the contact list you are adding the new contact.
   *
   * @required
   *
   * @var int
   */
  protected $contactListID;

  /**
   * @return int
   */
  public function getContactListID(): int {
    return $this->contactListID;
  }

  /**
   * @param int $contactListID
   *
   * @return AddContactToContactList
   */
  public function setContactListID(int $contactListID): AddContactToContactList {
    $this->contactListID = $contactListID;
    return $this;
  }

  /**
   * The ID of the contact you are adding to the contact list.
   *
   * @var int
   */
  protected $contactID;

  /**
   * @return int
   */
  public function getContactID(): int {
    return $this->contactID;
  }

  /**
   * @param int $contactID
   *
   * @return AddContactToContactList
   */
  public function setContactID(int $contactID): AddContactToContactList {
    $this->contactID = $contactID;
    return $this;
  }

  /**
   * Column name and value to look up the contact.
   *
   * If contactID is not supplied column name must be.
   *
   * var array
   */
  protected $column;

  /**
   * @return string
   */
  public function getEnvelope(): string {
    return '<AddContactToContactList/>';
  }

  /**
   * @return \SimpleXmlElement
   * @throws \Exception
   */
  public function getXml(): SimpleXmlElement {
    $xmlObject = new SimpleXmlElement($this->getEnvelope());
    $xmlObject->addChild('CONTACT_LIST_ID', $this->getContactListID());
    if ($this->getContactID()) {
      $xmlObject->addChild('CONTACT_ID', $this->getContactID());
    }
    else {
      // Not tested yet.
      // $xmlObject->addChild('COLUMN', $this->column);
      throw new \Exception('Calling AddContactToContactList without contact ID not currently implemented');
    }

    return $xmlObject;
  }

  /**
   * @param \SimpleXmlElement $result
   *
   * @return bool
   */
  public function formatResult($result): bool {
    return 'TRUE' === (string) $result->Body->RESULT->SUCCESS;
  }

}
