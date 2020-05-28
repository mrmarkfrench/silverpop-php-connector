<?php

namespace SilverpopConnector\Tests;

use SilverpopConnector\SilverpopConnector;
use SilverpopConnector\SilverpopXmlConnector;
use GuzzleHttp\Client;
use SilverpopConnector\Tests\BaseTestClass;
use SilverpopConnector\Tests\SilverpopBaseTestClass;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;

require_once __DIR__ . '/SilverpopBaseTestClass.php';
require_once __DIR__ . '/BaseTestClass.php';
class GetQueryTest extends SilverpopBaseTestClass {
  public function testGetQuery() {
    $container = [];
    $this->setUpMockXMLRequest($container, file_get_contents(__DIR__ . '/Mock/GetQueryResponse.txt'));
    $response = $this->silverPop->getQuery(['listId' => 1234]);
    $this->assertEquals(1, count($container));
    $transaction = reset($container);
    $this->assertEquals('POST', $transaction['request']->getMethod());
    $this->assertEquals('xml=' . urlencode(file_get_contents(__DIR__ . '/Mock/GetQueryRequest.txt', TRUE)), strval($transaction['request']->getBody()));

  }
  
}
