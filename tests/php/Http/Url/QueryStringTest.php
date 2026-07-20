<?php

namespace Tests\Http\Url;

use App\Http\Url\QueryString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Datensätze aus league/uri-interfaces QueryStringTest (parserProvider/buildProvider),
 * auf die RFC-3986-Fälle (PHP_QUERY_RFC3986, Separator "&") reduziert.
 * Source: https://github.com/thephpleague/uri-interfaces/blob/master/QueryStringTest.php
 */
class QueryStringTest extends TestCase
{
    #[DataProvider('provideParseData')]
    public function testParse(string $query, array $expected): void
    {
        self::assertSame($expected, QueryString::parse($query));
    }

    public static function provideParseData(): array
    {
        return [
            'empty string' => ['', [['', null]]],
            'identische Schlüssel' => ['a=1&a=2', [['a', '1'], ['a', '2']]],
            'kein Wert' => ['a&b', [['a', null], ['b', null]]],
            'leerer Wert' => ['a=&b=', [['a', ''], ['b', '']]],
            'php array' => ['a[]=1&a[]=2', [['a[]', '1'], ['a[]', '2']]],
            'Punkt bleibt erhalten' => ['a.b=3', [['a.b', '3']]],
            'dekodiert' => ['a%20b=c%20d', [['a b', 'c d']]],
            'kein Key-Stripping' => ['a=&b', [['a', ''], ['b', null]]],
            'kein Value-Stripping' => ['a=b=', [['a', 'b=']]],
            'nur Key' => ['a', [['a', null]]],
            'falsy 1' => ['0', [['0', null]]],
            'falsy 2' => ['0=', [['0', '']]],
            'falsy 3' => ['a=0', [['a', '0']]],
            'numerischer Key' => ['42=l33t', [['42', 'l33t']]],
        ];
    }

    #[DataProvider('provideBuildData')]
    public function testBuild(array $pairs, ?string $expected): void
    {
        self::assertSame($expected, QueryString::build($pairs));
    }

    public static function provideBuildData(): array
    {
        return [
            'leere Liste' => [[], null],
            'kein Wert' => [[['a', null], ['b', null]], 'a&b'],
            'leerer Wert' => [[['a', ''], ['b', '1.3']], 'a=&b=1.3'],
            'php array' => [[['a[]', '1%a6'], ['a[]', '2']], 'a%5B%5D=1%25a6&a%5B%5D=2'],
            'Unicode' => [[['page', '😓']], 'page=%F0%9F%98%93'],
            'bereits kodierter Wert wird doppelt kodiert' => [[['action', 'v%61lue']], 'action=v%2561lue'],
            'Punkt bleibt erhalten' => [[['a.b', '3']], 'a.b=3'],
            'kein Value-Stripping' => [[['a', 'b=']], 'a=b%3D'],
            'nur Key' => [[['a', null]], 'a'],
            'falsy' => [[['0', '0']], '0=0'],
            'Leerzeichen als %20, nicht als +' => [[['toto', 'foo+bar toto']], 'toto=foo%2Bbar%20toto'],
            'URI als Wert' => [
                [['url', 'https://uri.thephpleague.com/?module=home#do with space']],
                'url=https%3A%2F%2Furi.thephpleague.com%2F%3Fmodule%3Dhome%23do%20with%20space',
            ],
        ];
    }

    public function testWithParamAppendsWhenQueryIsEmpty(): void
    {
        self::assertSame('lang=de', QueryString::withParam('', 'lang', 'de'));
        self::assertSame('lang=de', QueryString::withParam(null, 'lang', 'de'));
    }

    public function testWithParamAppendsAndKeepsExistingPairs(): void
    {
        self::assertSame('token=abc123&lang=de', QueryString::withParam('token=abc123', 'lang', 'de'));
    }

    public function testWithParamReplacesExistingValueInPlace(): void
    {
        self::assertSame('token=abc123&lang=de', QueryString::withParam('token=abc123&lang=fr', 'lang', 'de'));
    }

    public function testWithParamEncodesKeyAndValue(): void
    {
        self::assertSame('lang=de%2Ffr', QueryString::withParam('', 'lang', 'de/fr'));
    }

    /**
     * RFC 3986 §2.3 (unreserved): ALPHA / DIGIT / "-" / "." / "_" / "~"
     * Diese Zeichen dürfen in einer Query-Komponente unkodiert bleiben.
     */
    #[DataProvider('provideUnreservedCharacters')]
    public function testUnreservedCharactersStayUnencoded(string $char): void
    {
        self::assertSame('a' . $char . 'b=1', QueryString::build([['a' . $char . 'b', '1']]));
    }

    public static function provideUnreservedCharacters(): array
    {
        $chars = array_merge(
            range('A', 'Z'),
            range('a', 'z'),
            range('0', '9'),
            ['-', '.', '_', '~']
        );
        return array_combine($chars, array_map(static fn(string $c): array => [$c], $chars));
    }

    /**
     * RFC 3986 §2.2 (reserved): gen-delims = ":" / "/" / "?" / "#" / "[" / "]" / "@"
     * und sub-delims = "!" / "$" / "&" / "'" / "(" / ")" / "*" / "+" / "," / ";" / "=".
     * Innerhalb eines Query-Key/Value-Paars sind diese Zeichen mehrdeutig
     * (Konflikt mit den Trennzeichen "&"/"=") und müssen prozent-kodiert werden.
     */
    #[DataProvider('provideReservedCharacters')]
    public function testReservedCharactersAreEncoded(string $char, string $expectedEncoded): void
    {
        self::assertSame('a=' . $expectedEncoded, QueryString::build([['a', $char]]));
    }

    public static function provideReservedCharacters(): array
    {
        return [
            'gen-delim :' => [':', '%3A'],
            'gen-delim /' => ['/', '%2F'],
            'gen-delim ?' => ['?', '%3F'],
            'gen-delim #' => ['#', '%23'],
            'gen-delim [' => ['[', '%5B'],
            'gen-delim ]' => [']', '%5D'],
            'gen-delim @' => ['@', '%40'],
            'sub-delim !' => ['!', '%21'],
            'sub-delim $' => ['$', '%24'],
            'sub-delim &' => ['&', '%26'],
            "sub-delim '" => ["'", '%27'],
            'sub-delim (' => ['(', '%28'],
            'sub-delim )' => [')', '%29'],
            'sub-delim *' => ['*', '%2A'],
            'sub-delim +' => ['+', '%2B'],
            'sub-delim ,' => [',', '%2C'],
            'sub-delim ;' => [';', '%3B'],
            'sub-delim =' => ['=', '%3D'],
        ];
    }
}
