<?php

namespace SilverpopConnector\Tests;

use PHPUnit_Framework_TestCase;
use  SilverpopConnector\Tests\BaseTestClass;


    class SilverpopBaseTestClass extends BaseTestClass
    {
        public function expectException($exception)
        {
            $this->setExpectedException($exception);
        }
    }

