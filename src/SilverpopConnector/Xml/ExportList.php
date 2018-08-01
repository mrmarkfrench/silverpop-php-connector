<?php

namespace SilverpopConnector\Xml;

use SimpleXmlElement;

/**
 * Created by IntelliJ IDEA.
 * User: emcnaughton
 * Date: 5/10/17
 * Time: 8:12 AM
 */
class ExportList extends XmlAction {

  /**
   * Unique identifier for the database, query, or contact list Engage is exporting.
   *
   * @var int
   */
  protected $listId;

  /**
   * Get Silverpop List ID.
   *
   * @return int
   */
  public function getListId() {
    return $this->listId;
  }

  /**
   * Set Silverpop List ID.
   *
   * @param int $listId
   */
  public function setListId($listId) {
    $this->listId = $listId;
  }

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
   * Specifies which contacts to export.
   *
   * Valid values are
   * - ALL – export entire database. System columns will not be exported by default.
   * - OPT_IN – export only currently opted-in contacts
   * - OPT_OUT – export only currently opted-out contacts.
   * - UNDELIVERABLE – export only contacts who are currently marked as undeliverable.
   *
   * @var string ALL|OPT_IN|OPT_OUT|UNDELIVERABLE
   */
  protected $exportType = 'ALL';

  /**
   * Specifies the format (file type) for the exported data.
   *
   * Valid values are:
   * - CSV – create a comma-separated values file
   * - TAB – create a tab-separated values file
   * - PIPE – create a pipe-separated values file
   *
   * @var string CSV|TAB|PIPE
   */
  protected $exportFormat = 'CSV';

  /**
   * Use the ADD_TO_STORED_FILES parameter to write the output to the Stored Files folder within Engage.
   *
   * If you omit the ADD_TO_STORED_FILES parameter, Engage will move exported files to the
   * download directory of the user's FTP space.
   *
   * @var bool
   */
  protected $addToStoredFiles = true;

  /**
   * Used to specify the date format of the date fields in your exported file.
   * if date format differs from "mm/dd/yyyy" (month, day, and year can be in any order you choose).
   * Valid values for Month are:
   * - mm (e.g. 01)
   * - m (e.g. 1)
   * - mon (e.g. Jan)
   * - month (e.g. January)
   * Valid values for Day are:
   * - dd (e.g. 02)
   * - d (e.g. 2)
   * Valid values for Year are:
   * - yyyy (e.g. 1999)
   * yy (e.g. 99)
   * Separators may be up to two characters in length and can consist of periods, commas,
   * question marks, spaces, and forward slashes (/).
   * Examples:
   * - If dates in your file are formatted as "Jan 2, 1975" your LIST_DATE_FORMAT would be "mon d, yyyy".
   * If dates in your file are formatted as "1975/09/02" your LIST_DATE_FORMAT would be "yyyy/mm/dd".
   *
   * @var string
   */
  protected $listDateFormat = 'yyyy-mm-dd';

  /**
   * @var array
   */
  protected $columns = array();

  /**
   * @return array
   */
  public function getColumns() {
    return $this->columns;
  }

  /**
   * @param array $columns
   */
  public function setColumns($columns) {
    $this->columns = $columns;
  }

  /**
   * @return string
   */
  public function getListDateFormat() {
    return $this->listDateFormat;
  }

  /**
   * @param string $listDateFormat
   */
  public function setListDateFormat($listDateFormat) {
    $this->listDateFormat = $listDateFormat;
  }

  /**
   * @return bool
   */
  public function isAddToStoredFiles() {
    return $this->addToStoredFiles;
  }

  /**
   * @param bool $addToStoredFiles
   */
  public function setAddToStoredFiles($addToStoredFiles) {
    $this->addToStoredFiles = $addToStoredFiles;
  }

  /**
   * @return string
   */
  public function getExportFormat() {
    return $this->exportFormat;
  }

  /**
   * @param string $exportFormat
   */
  public function setExportFormat($exportFormat) {
    $this->exportFormat = $exportFormat;
  }

  /**
   * @return string
   */
  public function getExportType() {
    return $this->exportType;
  }

  /**
   * @param string $exportType
   */
  public function setExportType($exportType) {
    $this->exportType = $exportType;
  }

  public function getEnvelope() {
    return '<ExportList/>';
  }

  public function getXml() {
    $xmlObject = new SimpleXmlElement($this->getEnvelope());
    $xmlObject->addChild('LIST_ID', $this->getListId());
    $xmlObject->addChild('EXPORT_TYPE', $this->getExportType());
    $xmlObject->addChild('EXPORT_FORMAT', $this->getExportFormat());
    $xmlObject->addChild('LIST_DATE_FORMAT', $this->getListDateFormat());
    if ($this->isAddToStoredFiles()) {
      $xmlObject->addChild('ADD_TO_STORED_FILES');
    }
    if ($this->getStartTimestamp()) {
      $xmlObject->addChild('DATE_START', date('m/d/Y H:i:s', $this->getStartTimestamp()));
    }
    if ($this->getEndTimestamp()) {
      $xmlObject->addChild('DATE_END', date('m/d/Y H:i:s', $this->getEndTimestamp()));
    }
    if (!empty($this->getColumns())) {
      $columnsXml = $xmlObject->addChild('EXPORT_COLUMNS');
      foreach ($this->getColumns() as $column) {
        $columnsXml->addChild('COLUMN', $column);
      }

    }
    return $xmlObject;
  }

  public function formatResult($result) {
    return $result->Body->RESULT;
  }
}
