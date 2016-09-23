<?php

namespace PostCSS\Tests;

use PostCSS\Declaration;
use PostCSS\Rule;
use PostCSS\Parser;

class DeclarationTest extends \PHPUnit_Framework_TestCase
{
    public function testInitializesWithProperties()
    {
        $decl = new Declaration(['prop' => 'color', 'value' => 'black']);
        $this->assertSame('color', $decl->prop);
        $this->assertSame('black', $decl->value);
    }

    public function testReturnsBooleanImportant()
    {
        $decl = new Declaration(['prop' => 'color', 'value' => 'black']);
        $decl->important = true;
        $this->assertSame('color: black !important', (string) $decl);
    }

    public function testInsertsDefaultSpaces()
    {
        $decl = new Declaration(['prop' => 'color', 'value' => 'black']);
        $rule = new Rule(['selector' => 'a']);
        $rule->append($decl);
        $this->assertSame("a {\n    color: black\n}", (string) $rule);
    }

    public function testClonesSpacesFromAnotherDeclaration()
    {
        $root = Parser::parse('a{color:black}');
        $decl = new Declaration(['prop' => 'margin', 'value' => '0']);
        $root->first->append($decl);
        $this->assertSame('a{color:black;margin:0}', (string) $root);
    }
}
