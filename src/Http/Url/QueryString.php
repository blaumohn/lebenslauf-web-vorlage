<?php

/*
 * Portiert aus league/uri-interfaces, jeweils auf den RFC-3986-Fall
 * (Separator "&", keine RFC-1738-/Form-Data-Sonderkodierung) reduziert:
 * - Encoder::encodeQueryKeyValue()
 *   Source: https://github.com/thephpleague/uri-interfaces/blob/master/Encoder.php
 * - KeyValuePair\Converter::toPairs()/toValue() (RFC-3986-Zweig)
 *   Source: https://github.com/thephpleague/uri-interfaces/blob/master/KeyValuePair/Converter.php
 * To update: bei neuer Major-Version dieser Dateien Encoding-Regex und Pair-Logik erneut abgleichen.
 *
 * Wird sowohl zur Build-Zeit (MarkdownContentRenderer: freie, im
 * Markdown-Quelltext geschriebene Link-URLs) als auch zur Request-Zeit
 * (LangMiddleware: bestehende Query eines Requests) verwendet. Naives
 * String-Splitten auf "&" wäre hier nicht robust genug (z. B. prozent-
 * kodierte Keys wie "la%6eg", die ein roher Präfixvergleich übersehen würde).
 */

namespace App\Http\Url;

final class QueryString
{
    private const REGEXP_PART_UNRESERVED = 'A-Za-z\d_\-.~';
    private const REGEXP_CHARS_INVALID = '/[\x00-\x1f\x7f]/';
    private const REGEXP_PART_ENCODED = '%(?![A-Fa-f\d]{2})';

    /** @return list<array{0: string, 1: ?string}> */
    public static function parse(string $query): array
    {
        $pairs = [];
        foreach (explode('&', $query) as $pair) {
            [$key, $value] = explode('=', $pair, 2) + [1 => null];
            $pairs[] = [rawurldecode($key), $value === null ? null : rawurldecode($value)];
        }

        return $pairs;
    }

    /** @param list<array{0: string, 1: ?string}> $pairs */
    public static function build(array $pairs): ?string
    {
        if ($pairs === []) {
            return null;
        }

        $parts = [];
        foreach ($pairs as [$key, $value]) {
            $parts[] = $value === null
                ? self::encodeQueryKeyValue($key)
                : self::encodeQueryKeyValue($key) . '=' . self::encodeQueryKeyValue($value);
        }

        return implode('&', $parts);
    }

    /** Ersetzt (oder ergänzt) genau ein Query-Paar, alle anderen Paare bleiben erhalten. */
    public static function withParam(?string $query, string $key, string $value): string
    {
        $pairs = $query === null || $query === '' ? [] : self::parse($query);
        $pairs = array_values(array_filter($pairs, static fn(array $pair): bool => $pair[0] !== $key));
        $pairs[] = [$key, $value];

        return (string) self::build($pairs);
    }

    private static function encodeQueryKeyValue(string $value): string
    {
        static $pattern = '/[^' . self::REGEXP_PART_UNRESERVED . ']+|' . self::REGEXP_PART_ENCODED . '/';
        $encoder = static fn(array $found): string => 1 === preg_match('/[^' . self::REGEXP_PART_UNRESERVED . ']/', rawurldecode($found[0]))
            ? rawurlencode($found[0])
            : $found[0];

        if (preg_match(self::REGEXP_CHARS_INVALID, $value) === 1) {
            return rawurlencode($value);
        }

        return (string) preg_replace_callback($pattern, $encoder, $value);
    }
}
