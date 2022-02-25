<?php

namespace SilverpopConnector\Xml;

use SimpleXmlElement;

/**
 * Created by IntelliJ IDEA.
 * User: emcnaughton
 *
 * @see https://developer.goacoustic.com/acoustic-campaign/reference/createcontactlist
 */
class CreateContactList extends XmlAction {

  /**
   * The name for the created contact list.
   *
   * @required
   *
   * @var string
   */
  protected $contactListName;

  /**
   * Defines the visibility of the created contact list: 0 (private) or 1 (shared).
   *
   * @var int
   */
  protected $visibility;

  /**
   * The associated database ID for the contact list.
   *
   * @var int
   */
  protected $databaseID;

  /**
   * Parent folder path.
   *
   * Specifies the contact list folder path of the contact list folder where you want the contact list. The specified folder must exist in the contact list structure and you must have access to the folder.
   *
   * @var string
   */
  protected $parentFolderPath;

  /**
   * PARENT_FOLDER_ID.
   *
   * Specifies the contact list folder ID where you want the contact list.
   * The specified folder must exist in the contact list structure and you must
   * have access to the folder.
   *
   * @var string
   */
  protected $parentFolderID;

  /**
   * @return string
   */
  public function getContactListName(): string {
    return $this->contactListName;
  }

  /**
   * @param string $contactListName
   *
   * @return CreateContactList
   */
  public function setContactListName(string $contactListName): CreateContactList {
    $this->contactListName = $contactListName;
    return $this;
  }

  /**
   * @return int
   */
  public function getVisibility(): int {
    return $this->visibility;
  }

  /**
   * @param int $visibility
   *
   * @return CreateContactList
   */
  public function setVisibility(int $visibility): CreateContactList {
    $this->visibility = $visibility;
    return $this;
  }

  /**
   * @return int
   */
  public function getDatabaseID(): int {
    return $this->databaseID;
  }

  /**
   * @param int $databaseID
   *
   * @return CreateContactList
   */
  public function setDatabaseID(int $databaseID): CreateContactList {
    $this->databaseID = $databaseID;
    return $this;
  }

  /**
   * @return null|string
   */
  public function getParentFolderPath(): ?string {
    return $this->parentFolderPath;
  }

  /**
   * @param null|string $parentFolderPath
   *
   * @return self
   */
  public function setParentFolderPath(?string $parentFolderPath): CreateContactList {
    $this->parentFolderPath = $parentFolderPath;
    return $this;
  }

  /**
   * @return string
   */
  public function getParentFolderID(): string {
    return $this->parentFolderID;
  }

  /**
   * @param null|string $parentFolderID
   *
   * @return CreateContactList
   */
  public function setParentFolderID(?string $parentFolderID): CreateContactList {
    $this->parentFolderID = $parentFolderID;
    return $this;
  }

  /**
   * @param int $createParentFolder
   *
   * @return CreateContactList
   */
  public function setCreateParentFolder(int $createParentFolder): CreateContactList {
    $this->createParentFolder = $createParentFolder;
    return $this;
  }

  /**
   * Should the parent folder be created.
   *
   * If the specified PARENT_FOLDER_PATH doesn’t exist, then the system creates that folder.
   * However, if you have a folder limit assigned at the org level, then the contact list is created from your root folder/.
   *
   * @var bool
   */
  protected $createParentFolder;

  /**
   * @return bool
   */
  public function isCreateParentFolder(): bool {
    return (bool) $this->createParentFolder;
  }

  /**
   * @return string
   */
  public function getEnvelope(): string {
    return '<CreateContactList/>';
  }

  /**
   * @return \SimpleXmlElement
   * @throws \Exception
   */
  public function getXml(): SimpleXmlElement {
    $xmlObject = new SimpleXmlElement($this->getEnvelope());
    $xmlObject->addChild('DATABASE_ID', $this->getDatabaseID());
    $xmlObject->addChild('CONTACT_LIST_NAME', $this->getContactListName());
    $xmlObject->addChild('VISIBILITY', $this->getVisibility());
    $xmlObject->addChild('PARENT_FOLDER_PATH', $this->getParentFolderPath());
    if ($this->isCreateParentFolder()) {
      $xmlObject->addChild('CREATE_PARENT_FOLDER');
    }
    return $xmlObject;
  }

  /**
   * @param \SimpleXmlElement $result
   */
  public function formatResult($result) {
    return $result->Body->RESULT;
  }
}
