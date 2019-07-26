<?php

namespace SilverpopConnector\Tests;

use SilverpopConnector\SilverpopXmlConnector;
use SilverpopConnector\SilverpopRestConnector;
use SilverpopConnector\SilverpopConnector;

class AuthenticateTest extends SilverpopBaseTestClass
{
  /**
   * @var SilverpopXmlConnector
   */
  protected $silverpop;

  public function testAuthenticateXML()
  {
    $container = array();
    $this->setUpMockXMLRequest($container, file_get_contents(__DIR__ . '/Mock/AuthenticateResponse.txt'), FALSE);
    $this->silverPop->authenticate('Donald Duck', 'Quack');
    $this->assertEquals(1, count($container));
    $transaction = reset($container);
    $this->assertEquals('POST', $transaction['request']->getMethod());
    $this->assertEquals(trim(file_get_contents(__DIR__ . '/Mock/AuthenticateRequest.txt', true)), strval($transaction['request']->getBody()));

  }

  public function testAuthenticateRest()
  {
    $container = [];
    $this->silverPop = SilverpopConnector::getInstance('api4.ibmmarketingcloud.com');
    $this->setUpMockRestAuthenticate($container);
    $this->silverPop->authenticate('specialClientID', 'secretterthanasquirrel', 'onasecretmission');

    $this->assertEquals(1, count($container));
    $transaction = reset($container);
    $this->assertEquals('POST', $transaction['request']->getMethod());
    $this->assertEquals('grant_type=refresh_token&client_id=specialClientID&client_secret=secretterthanasquirrel&refresh_token=onasecretmission', (string) $transaction['request']->getBody());
    $this->assertEquals(['application/x-www-form-urlencoded'], $transaction['request']->getHeader('Content-Type'));

    $response = $transaction['response'];
    $this->assertEquals('{"access_token":"a0r1231T2qt-yG03G111oENHRaYSy3M123450xlAQc8wS1","token_type":"bearer","refresh_token":"r-aCk12348BLxkBnUr3Zz_ivg612345ii1aDc11s0CQsS1","expires_in":12222}', (string) $response->getBody());
  }

}
