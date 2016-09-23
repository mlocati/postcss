<?php

namespace PostCSS\Tests;

use PostCSS\Comment;
use PostCSS\Parser;

class CommentTest extends \PHPUnit_Framework_TestCase
{
    public function testToStringInsertDefaultSpaces()
    {
        $comment = new Comment(['text' => 'hi']);
        $this->assertSame('/* hi */', (string) $comment);
    }

    public function testToStringClonesSpacesFromAnotherComment()
    {
        $root = Parser::parse('a{} /*hello*/');
        $comment = new Comment(['text' => 'world']);
        $root->append($comment);
        $this->assertSame('a{} /*hello*/ /*world*/', (string) $root);
    }
}
