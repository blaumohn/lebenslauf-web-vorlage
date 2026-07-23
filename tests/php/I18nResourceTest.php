<?php

declare(strict_types=1);

use App\Cli\Site\LabelCatalog;
use App\Http\Contact\ContactContent;
use App\Http\Cv\CvContent;
use App\Http\Lang\I18nResourceInterface;
use PHPUnit\Framework\TestCase;

final class I18nResourceTest extends TestCase
{
    public function testLabelCatalogProjectsForLanguage(): void
    {
        $resource = new LabelCatalog([
            'cv' => ['value' => ['de' => 'Lebenslauf', 'es' => 'Currículum']],
        ]);

        self::assertInstanceOf(I18nResourceInterface::class, $resource);
        self::assertSame('Currículum', $resource->forLanguage('es')['cv']['value']);
    }

    public function testCvContentProjectsForLanguage(): void
    {
        $resource = new CvContent([
            'titel' => ['de' => 'Entwickler', 'es' => 'Desarrollador'],
        ], ['de', 'es']);

        self::assertInstanceOf(I18nResourceInterface::class, $resource);
        self::assertSame('Desarrollador', $resource->forLanguage('es')['titel']);
    }

    public function testContactContentProjectsForLanguage(): void
    {
        $resource = new ContactContent([
            'intro' => ['de' => 'Schreiben Sie mir.', 'es' => 'Escríbame.'],
        ], ['de', 'es']);

        self::assertInstanceOf(I18nResourceInterface::class, $resource);
        self::assertSame('Escríbame.', $resource->forLanguage('es')['intro']);
    }
}
