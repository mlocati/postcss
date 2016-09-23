<?php

namespace PostCSS\Tests;

use PostCSS\Parser;
use PostCSS\Stringifier;

class StringifyTest extends Helpers\CasesTest
{
    public function providerTestStringifies()
    {
        $r = [];
        foreach (static::getParseCases() as $id => $data) {
            if ($id !== 'bom') {
                $r[] = [$id.'.css', $data['contents']['css']];
            }
        }

        return $r;
    }

    /**
     * @dataProvider providerTestStringifies
     */
    public function testStringifies($name, $css)
    {
        $root = Parser::parse($css);
        $result = '';
        $stringifier = new Stringifier(
            function ($i) use (&$result) {
                $result .= $i;
            }
        );
        $stringifier->stringify($root);
        $this->assertSame($css, $result);
    }
}
