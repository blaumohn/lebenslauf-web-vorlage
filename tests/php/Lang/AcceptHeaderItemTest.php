<?php

/*
 * Ported from symfony/http-foundation Tests/AcceptHeaderItemTest.php.
 * Source: https://github.com/symfony/http-foundation/blob/main/Tests/AcceptHeaderItemTest.php
 * To update: copy the source file and change the namespace/use statements below.
 */

namespace Tests\Http\Lang;

use App\Http\Lang\AcceptHeaderItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AcceptHeaderItemTest extends TestCase
{
    #[DataProvider('provideFromStringData')]
    public function testFromString($string, $value, array $attributes)
    {
        $item = AcceptHeaderItem::fromString($string);
        $this->assertEquals($value, $item->getValue());
        $this->assertEquals($attributes, $item->getAttributes());
    }

    public static function provideFromStringData()
    {
        return [
            [
                'text/html',
                'text/html', [],
            ],
            [
                '"this;should,not=matter"',
                'this;should,not=matter', [],
            ],
            [
                "text/plain; charset=utf-8;param=\"this;should,not=matter\";\tfootnotes=true",
                'text/plain', ['charset' => 'utf-8', 'param' => 'this;should,not=matter', 'footnotes' => 'true'],
            ],
            [
                '"this;should,not=matter";charset=utf-8',
                'this;should,not=matter', ['charset' => 'utf-8'],
            ],
        ];
    }

    #[DataProvider('provideToStringData')]
    public function testToString($value, array $attributes, $string)
    {
        $item = new AcceptHeaderItem($value, $attributes);
        $this->assertEquals($string, (string) $item);
    }

    public static function provideToStringData()
    {
        return [
            [
                'text/html', [],
                'text/html',
            ],
            [
                'text/plain', ['charset' => 'utf-8', 'param' => 'this;should,not=matter', 'footnotes' => 'true'],
                'text/plain; charset=utf-8; param="this;should,not=matter"; footnotes=true',
            ],
        ];
    }

    public function testValue()
    {
        $item = new AcceptHeaderItem('value', []);
        $this->assertEquals('value', $item->getValue());

        $item->setValue('new value');
        $this->assertEquals('new value', $item->getValue());
    }

    public function testQuality()
    {
        $item = new AcceptHeaderItem('value', []);
        $this->assertEquals(1.0, $item->getQuality());

        $item->setQuality(0.5);
        $this->assertEquals(0.5, $item->getQuality());

        $item->setAttribute('q', '0.75');
        $this->assertEquals(0.75, $item->getQuality());
        $this->assertFalse($item->hasAttribute('q'));
    }

    public function testAttribute()
    {
        $item = new AcceptHeaderItem('value', []);
        $this->assertEquals([], $item->getAttributes());
        $this->assertFalse($item->hasAttribute('test'));
        $this->assertNull($item->getAttribute('test'));
        $this->assertEquals('default', $item->getAttribute('test', 'default'));

        $item->setAttribute('test', 'value');
        $this->assertEquals(['test' => 'value'], $item->getAttributes());
        $this->assertTrue($item->hasAttribute('test'));
        $this->assertEquals('value', $item->getAttribute('test'));
        $this->assertEquals('value', $item->getAttribute('test', 'default'));
    }
}
