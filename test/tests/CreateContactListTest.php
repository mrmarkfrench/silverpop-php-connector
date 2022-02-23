<?php

namespace SilverpopConnector\Tests;

use SilverpopConnector\SilverpopConnector;
use SilverpopConnector\SilverpopXmlConnector;
use GuzzleHttp\Client;
use SilverpopConnector\Tests\BaseTestClass;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;

class CreateContactListTest extends SilverpopBaseTestClass {

  /**
   * Test creating a list.
   *
   * @see https://developer.goacoustic.com/acoustic-campaign/reference/createcontactlist
   */
  public function testCreateContactListTest(): void {
    $container = [];
    $this->setUpMockXMLRequest($container, file_get_contents(__DIR__ . '/Mock/CreateContactListResponse.txt'));
    $this->silverPop->createContactList([
      'databaseID' => 12345678,
      'ContactListName' => 'test-group',
      'visibility' => 1,
    ]);

    $this->assertCount(1, $container);
    $transaction = reset($container);
    $this->assertEquals('POST', $transaction['request']->getMethod());
    $this->assertEquals(trim(file_get_contents(__DIR__ . '/Mock/CreateContactListRequest.txt', TRUE)), (string) $transaction['request']->getBody());

  }
}
