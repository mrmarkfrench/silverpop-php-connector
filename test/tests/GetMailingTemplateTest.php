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

class GetMailingTemplateTest extends SilverpopBaseTestClass {
  public function testGetMailingTemplate() {
    $container = array();
    $this->setUpMockXMLRequest($container, file_get_contents(__DIR__ . '/Mock/GetMailingTemplateResponse.txt'));
    $response = $this->silverPop->getMailingTemplate(array('mailingID' => 5));

    $this->assertEquals(1, count($container));
    $transaction = reset($container);
    $this->assertEquals('POST', $transaction['request']->getMethod());
    $this->assertEquals(file_get_contents(__DIR__ . '/Mock/GetMailingTemplateRequest.txt', TRUE), strval($transaction['request']->getBody()));

  }
}
