<?php

namespace SilverpopConnector\Tests;

use PHPUnit_Framework_TestCase;
use PHPUnit\Framework\TestCase;
use Guzzle\Tests\GuzzleTestCase;

if (class_exists('\PHPUnit_Framework_TestCase') && stripos(phpversion(), '5') === 0) {
    class BaseTestClass extends GuzzleTestCase
    {
        public function expectException($exception)
        {
            $this->setExpectedException($exception);
        }
    }
} elseif (class_exists('\PHPUnit\Framework\TestCase')) {
    class BaseTestClass extends GuzzleTestCase
    {
        public function expectException($exception)
        {
            if (is_callable('parent::expectException')) {
                parent::expectException($exception);
            } else {
                $this->setExpectedException($exception);
            }
        }
    }
}
