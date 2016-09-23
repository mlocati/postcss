<?php

namespace PostCSS\Tests;

use PostCSS\Vendor;

class VendorTest extends \PHPUnit_Framework_TestCase
{
    public function testReturnsPrefix()
    {
        $this->assertSame('-moz-', Vendor::prefix('-moz-color'));
        $this->assertSame('', Vendor::prefix('color'));
    }

    public function testReturnsUnprefixedVersion()
    {
        $this->assertSame('color', Vendor::unprefixed('-moz-color'));
        $this->assertSame('color', Vendor::unprefixed('color'));
    }
}
