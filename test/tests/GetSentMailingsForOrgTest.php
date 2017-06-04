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

class GetSentMailingsForOrgTest extends SilverpopBaseTestClass {
	public function testGetMailingTemplate() {
		$container = array();
		$this->setUpMockRequest($container, file_get_contents(__DIR__ . '/Mock/GetSentMailingsForOrgResponse.txt'));
		$response = $this->silverPop->getSentMailingsForOrg(1495982075, 1496586875, array('SHARED', 'EXCLUDE_TEST_MAILINGS', 'EXCLUDE_ZERO_SENT', 'SENT', 'SENDING'));

		$this->assertEquals(1, count($container));
		$transaction = reset($container);
		$this->assertEquals('POST', $transaction['request']->getMethod());
		$this->assertEquals(file_get_contents(__DIR__ . '/Mock/GetSentMailingsForOrgRequest.txt', TRUE), strval($transaction['request']->getBody()));

		$this->assertEquals(6047, (int) $response[0]->NumSent);

	}
}
