<?php

namespace Tests\Http\Lang;

use App\Http\Lang\LangResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LangResolverTest extends TestCase
{
    private LangResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new LangResolver();
    }

    #[DataProvider('provideFromHeaderData')]
    public function testFromHeader(string $header, array $supported, ?string $expected): void
    {
        $this->assertSame($expected, $this->resolver->fromHeader($header, $supported));
    }

    public static function provideFromHeaderData(): array
    {
        return [
            'exakter Treffer' => [
                'de', ['de', 'es'], 'de',
            ],
            'Region wird aufgelöst' => [
                'de-DE', ['de', 'es'], 'de',
            ],
            'Qualitätswerte bestimmen Reihenfolge' => [
                'en;q=0.9,es;q=0.8,de;q=0.7', ['de', 'es'], 'es',
            ],
            'erste bevorzugte Sprache ohne q' => [
                'de-AT,de;q=0.9,en;q=0.8', ['de', 'es'], 'de',
            ],
            'kein Treffer gibt null zurück' => [
                'en-US,en;q=0.9', ['de', 'es'], null,
            ],
            'leerer Header gibt null zurück' => [
                '', ['de', 'es'], null,
            ],
            'leere Unterstützungsliste gibt null zurück' => [
                'de', [], null,
            ],
            'zh-Hans wird korrekt aufgelöst' => [
                'zh-Hans-TW', ['zh', 'de'], 'zh',
            ],
            'Groß-/Kleinschreibung des Headers irrelevant' => [
                'DE', ['de', 'es'], 'de',
            ],
        ];
    }

    public function testPreferredOverHeaderWhenBothPresent(): void
    {
        // ?lang= wird in der Middleware geprüft — LangResolver selbst kennt nur den Header
        $result = $this->resolver->fromHeader('en', ['de', 'es']);
        $this->assertNull($result);
    }
}
