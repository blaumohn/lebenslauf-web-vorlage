<?php

declare(strict_types=1);

use App\Http\Lang\LanguageProjection;
use PHPUnit\Framework\TestCase;

final class LanguageProjectionTest extends TestCase
{
    public function testRejectsBlankLanguage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sprache darf nicht leer sein.');

        new LanguageProjection('   ', ['de', 'es', 'pt']);
    }

    public function testKeepsUniversalString(): void
    {
        $data = (new LanguageProjection('pt', ['de', 'es', 'pt']))->apply([
            'titel' => 'Open Source',
        ]);

        $this->assertSame('Open Source', $data['titel']);
    }

    public function testUsesExactTranslationBeforeDefault(): void
    {
        $data = (new LanguageProjection('es', ['de', 'es', 'pt']))->apply([
            'titel' => [
                'es' => 'Título español',
                'default' => 'Allgemeiner Titel',
            ],
        ]);

        $this->assertSame('Título español', $data['titel']);
    }

    public function testUsesExplicitDefaultForMissingTranslation(): void
    {
        $data = (new LanguageProjection('pt', ['de', 'es', 'pt']))->apply([
            'titel' => [
                'default' => 'Allgemeiner Titel',
            ],
        ]);

        $this->assertSame('Allgemeiner Titel', $data['titel']);
    }

    public function testRejectsMissingTranslationWithoutDefault(): void
    {
        $languageProjection = new LanguageProjection('pt', ['de', 'es', 'pt']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Übersetzung für Sprache pt fehlt.');

        $languageProjection->apply([
            'titel' => ['de' => 'Deutscher Titel'],
        ]);
    }
}
