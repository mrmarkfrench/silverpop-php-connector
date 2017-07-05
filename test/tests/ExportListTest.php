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

class ExportListTest extends SilverpopBaseTestClass {
	public function testExportList() {
		$container = array();

    $this->setUpMockRequest($container, file_get_contents(__DIR__ . '/Mock/ExportListResponse.txt'));
		$response = $this->silverPop->exportList(18176618);

		$this->assertEquals(1, count($container));
		$transaction = reset($container);
		$this->assertEquals('POST', $transaction['request']->getMethod());
		$this->assertEquals(file_get_contents(__DIR__ . '/Mock/ExportListRequest.txt', TRUE), strval($transaction['request']->getBody()));

		$this->assertEquals(101719657, (int) $response['jobId']);
		$this->assertEquals('/download/20170509_noCID - All - Jul 5 2017 12-53-25 AM.CSV', (string) $response['filePath']);
	}

	/**
	 *
	 */
	public function testExportListDifferentParameters() {
		$container = array();
		$this->setUpMockRequest($container, file_get_contents(__DIR__ . '/Mock/ExportListResponse.txt'));
		$this->silverPop->exportList(18176618, strtotime('2017-04-05'), strtotime('2017-05-05 17:23:23'), 'OPT_IN', 'TAB', array('ContactID'));
		$transaction = reset($container);
		$this->assertEquals(file_get_contents(__DIR__ . '/Mock/ExportListRequest2.txt', TRUE), strval($transaction['request']->getBody()));
	}
}
