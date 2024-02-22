<?php

namespace cstuder\PhpSkeleton;

use PHPUnit\Framework\TestCase;

/**
 * XXX Replace me
 * 
 * @package cstuder\PhpSkeleton
 */
class HelloTest extends TestCase
{
    public function testGetGreeting()
    {
        $hello = new \cstuder\PhpSkeleton\Hello();

        $this->assertSame('Hello World!', $hello->getGreeting());
    }
}
