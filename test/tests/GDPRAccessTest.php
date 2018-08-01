<?php

namespace SilverpopConnector\Tests;

use SilverpopConnector\SilverpopConnector;
use SilverpopConnector\SilverpopRestConnector;
use GuzzleHttp\Client;
use SilverpopConnector\Tests\BaseTestClass;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;

class GDPRAccessTest extends SilverpopBaseTestClass {

  public function testGDPRAccessRequest() {
    $baseUrl = 'api4.ibmmarketingcloud.com';
    //SilverpopConnector::getInstance($credentials['silverpop']['baseUrl']);
    $this->silverpop = SilverpopRestConnector::getInstance();
    $this->silverpop->setBaseUrl($baseUrl);
    $mock = $this->getMockHandler([
      trim(file_get_contents(__DIR__ . '/Mock/RestAuthenticateResponse.txt')),
      trim(file_get_contents(__DIR__ . '/Mock/RestGDPRAccess1.txt')),
      trim(file_get_contents(__DIR__ . '/Mock/RestGDPRAccess2.txt')),
      trim(file_get_contents(__DIR__ . '/Mock/RestGDPRAccess3.txt')),
    ]);
    $container = [];
    $this->addMockHistoryCollectorToRestConnector($container, $baseUrl, $mock);
    $this->silverPop->authenticate('specialClientID', 'secretterthanasquirrel', 'onasecretmission');

    $result = $this->silverpop->gdpr_access([
      'data' => [['Email', 'email@example.com']],
      'database_id' => '123456',
    ]);
    foreach ($container as $contains) {
      $response[] = (string) $contains['response']->getBody();
    }
    $this->assertEquals(123456, $result['data']['databaseId']);
    $this->assertEquals('email@example.com', $result['data']['contacts'][0]['gdprIdentifiers'][0]['value']);
 }

}
