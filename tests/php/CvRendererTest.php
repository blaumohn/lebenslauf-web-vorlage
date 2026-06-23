<?php

declare(strict_types=1);

use App\Http\Cv\CvDataNormalizer;
use App\Http\Cv\CvRenderer;
use App\Http\Cv\CvViewModelBuilder;
use App\Http\Templating\TwigFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class CvRendererTest extends TestCase
{
    public function testEducationWithDegreeOnlyIsRendered(): void
    {
        $data = Yaml::parseFile($this->validFixturePath());
        $normalizer = new CvDataNormalizer('de');
        $builder = new CvViewModelBuilder();
        $view = $builder->build($normalizer->normalize($data));

        $html = $this->renderer()->renderPublic($view, $this->labels(), 'de', '');

        $this->assertStringContainsString(
            '<li>Zertifikat Webentwicklung, BFI Wien</li>',
            $html
        );
    }

    public function testPublicCvUsesGivenDocumentLanguage(): void
    {
        $data = Yaml::parseFile($this->validFixturePath());
        $normalizer = new CvDataNormalizer('en');
        $builder = new CvViewModelBuilder();
        $view = $builder->build($normalizer->normalize($data));

        $html = $this->renderer()->renderPublic($view, $this->labels(), 'en', '');

        $this->assertStringContainsString('<html lang="en">', $html);
    }

    private function renderer(): CvRenderer
    {
        $twig = TwigFactory::create($this->templatesPath());
        TwigFactory::configure($twig, '');
        return new CvRenderer($twig);
    }

    private function templatesPath(): string
    {
        return dirname(__DIR__, 2) . '/src/resources/templates';
    }

    private function validFixturePath(): string
    {
        return dirname(__DIR__, 2) . '/src/resources/fixtures/lebenslauf/daten-demo.yaml';
    }

    private function labels(): array
    {
        return [
            'cv' => [
                'value' => 'Lebenslauf',
                'childLabels' => [
                    'faehigkeiten'    => ['value' => 'Fähigkeiten'],
                    'sprachen'        => ['value' => 'Sprachen'],
                    'interessen'      => ['value' => 'Interessen'],
                    'motivation'      => ['value' => 'Motivation'],
                    'berufserfahrung' => ['value' => 'Berufserfahrung'],
                    'opensource'      => ['value' => 'Open Source'],
                    'vortraege'       => ['value' => 'Vorträge'],
                    'ausbildung'      => ['value' => 'Ausbildung'],
                ],
            ],
        ];
    }
}
