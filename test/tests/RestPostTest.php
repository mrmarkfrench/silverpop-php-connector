<?php

namespace SilverpopConnector\Tests;
require_once 'SilverpopBaseTestClass.php';
use SilverpopConnector\SilverpopRestConnector;

class RestPostTest extends SilverpopBaseTestClass {

  public function testRestPostRequest() {
    $baseUrl = 'https://api-campaign-us-4.goacoustic.com';
    $programID = 'af111b1f-bc8b-4edc-a743-408594312f99';
    $connector = SilverpopRestConnector::getInstance();
    $connector->setBaseUrl($baseUrl);
    $container = [];
    // Authenticating does not send a request - the token is added by the
    // middleware on the client, which the mock client here replaces.
    $mock = $this->getMockHandler(['{"status": "success"}']);
    $this->addMockHistoryCollectorToRestConnector($container, $baseUrl, $mock);
    $connector->authenticate('specialClientID', 'secretterthanasquirrel', 'onasecretmission');

    $result = $connector->restPost($programID, 'channels/sms/programs', ['virtualmo'], [
      'phoneNumber' => '14155552671',
    ]);
    $postRequest = $container[0]['request'];
    $this->assertEquals('POST', $postRequest->getMethod());
    $this->assertEquals('https://api-campaign-us-4.goacoustic.com/rest/channels/sms/programs/' . $programID . '/virtualmo', (string) $postRequest->getUri());
    $this->assertEquals('{"phoneNumber":"14155552671"}', (string) $postRequest->getBody());
    $this->assertEquals('success', $result['status']);
  }

}