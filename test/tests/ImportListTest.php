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

class ImportListTest extends SilverpopBaseTestClass {

  public function testImportList() {
    $container = [];

    $this->setUpMockXMLRequest($container, file_get_contents(__DIR__ . '/Mock/ImportListResponse.txt'));
    $response = $this->silverPop->importList('mapping.xml', 'mapping.csv');

    $this->assertEquals(1, count($container));
    $transaction = reset($container);
    $this->assertEquals('POST', $transaction['request']->getMethod());
    $this->assertEquals(trim(file_get_contents(__DIR__ . '/Mock/ImportListRequest.txt', TRUE)), strval($transaction['request']->getBody()));
  }

}
