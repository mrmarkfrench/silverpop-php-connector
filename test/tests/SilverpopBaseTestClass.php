<?php

namespace SilverpopConnector\Tests;

use SilverpopConnector\Tests\BaseTestClass;
use SilverpopConnector\SilverpopConnector;
use SilverpopConnector\SilverpopRestConnector;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;

class SilverpopBaseTestClass extends BaseTestClass {

  /**
   * @var SilverpopXmlConnector
   */
  protected $silverpop;

  /**
   * Set up a mock request, specifying the body of the response.
   *
   * @param string $body
   *   Body to be returned from the http request.
   *
   * @param array $container
   *   Reference to array to store Request history in.
   * @param bool $authenticateFirst
   * @return array $container
   */
  protected function setUpMockRequest(&$container, $body, $authenticateFirst = TRUE) {
    $this->silverPop = SilverpopConnector::getInstance();
    $history = Middleware::history($container);

    if ($authenticateFirst) {
      $mock = new MockHandler([
        new Response(200, [], file_get_contents(__DIR__ . '/Mock/AuthenticateResponse.txt')),
        new Response(200, [], $body),
      ]);
    }
    else {
      $mock = new MockHandler([
        new Response(200, [], $body),
      ]);
    }
    $handler = HandlerStack::create($mock);
    // Add the history middleware to the handler stack.
    $handler->push($history);
    $client = new Client(array('handler' => $handler));
    $this->silverPop->setClient($client);

    if ($authenticateFirst) {
      $this->silverPop->authenticate('Donald Duck', 'Quack');
      unset($container[0]);
    }
  }

  /**
   * Set up a mock request, specifying the body of the response.
   *
   * @param string $body
   *   Body to be returned from the http request.
   *
   * @param array $container
   *   Reference to array to store Request history in.
   * @param bool $authenticateFirst
   * @return array $container
   */
  protected function setUpMockRestAuthenticate(&$container) {
    $this->silverPop = SilverpopRestConnector::getInstance();

    $mock = new MockHandler([
      new Response(200, [], trim(file_get_contents(__DIR__ . '/Mock/RestAuthenticateResponse.txt'))),
    ]);

    $history = Middleware::history($container);
    $handler = HandlerStack::create($mock);
    // Add the history middleware to the handler stack.
    $handler->push($history);
    $client = new Client(array('handler' => $handler));
    $this->silverPop->setClient($client);
  }

}

