<?php

namespace SilverpopConnector\Tests;

use  SilverpopConnector\SilverpopConnector;
use SilverpopConnector\SilverpopXmlConnector;
use GuzzleHttp\Client;

class AuthenticateTest extends BaseTestClass
{
    public function testAuthenticate()
    {
       $silverPop = SilverpopConnector::getInstance();
       $client = new Client();
       /* @var SilverpopXmlConnector $silverpop */
       $silverPop->setClient($client);
    }
}
