<?php

namespace SilverpopConnector\Tests;
require_once 'SilverpopBaseTestClass.php';
use SilverpopConnector\SilverpopConnector;
use SilverpopConnector\SilverpopRestConnector;
use GuzzleHttp\Client;
use SilverpopConnector\Tests\BaseTestClass;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;

class RestGetTest extends SilverpopBaseTestClass {

  protected $acoustic;

  public function testRestGetRequest() {
    $baseUrl = 'https://api-campaign-us-4.goacoustic.com';
    $databaseID = 123456;
    $connector = SilverpopRestConnector::getInstance();
    $connector->setBaseUrl($baseUrl);
    $container = [];
    $mock = $this->getMockHandler([
      trim(file_get_contents(__DIR__ . '/Mock/RestAuthenticateResponse.txt')),
      trim(file_get_contents(__DIR__ . '/Mock/RestGetResponse.txt')),
    ]);
    $this->addMockHistoryCollectorToRestConnector($container, $baseUrl, $mock);
    $connector->authenticate('specialClientID', 'secretterthanasquirrel', 'onasecretmission');

    $result = $connector->restGet($databaseID, 'databases', [
      'eventtypemappings'
    ]);
    $getRequest = $container[1]['request'];
    $this->assertEquals('https://api-campaign-us-4.goacoustic.com/rest/databases/' . $databaseID . '/eventtypemappings',  (string) $getRequest->getUri());
    $this->assertEquals('SMS - Interacted With a SMS Program', $result['data'][0]['eventType']['name']);
 }

}
