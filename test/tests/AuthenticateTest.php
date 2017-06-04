<?php

namespace SilverpopConnector\Tests;

use SilverpopConnector\SilverpopXmlConnector;

class AuthenticateTest extends SilverpopBaseTestClass
{
	/**
	 * @var SilverpopXmlConnector
	 */
	protected $silverpop;

	public function testAuthenticate()
	{
		$container = array();
		$this->setUpMockRequest($container, file_get_contents(__DIR__ . '/Mock/AuthenticateResponse.txt'), FALSE);
		$this->silverPop->authenticate('Donald Duck', 'Quack');

		$this->assertEquals(1, count($container));
		$transaction = reset($container);
		$this->assertEquals('POST', $transaction['request']->getMethod());
		$this->assertEquals(file_get_contents(__DIR__ . '/Mock/AuthenticateRequest.txt', true), strval($transaction['request']->getBody()));

	}

}
