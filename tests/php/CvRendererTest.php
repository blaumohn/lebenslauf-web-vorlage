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

        $html = $this->renderer()->renderPublic($view, $this->labels());

        $this->assertStringContainsString(
            '<li>Zertifikat Webentwicklung, BFI Wien</li>',
            $html
        );
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
        return dirname(__DIR__, 2) . '/src/resources/fixtures/lebenslauf/daten-gueltig.yaml';
    }

    private function labels(): array
    {
        return [
            '_' => 'Lebenslauf',
            'faehigkeiten' => ['_' => 'Fähigkeiten'],
            'sprachen' => ['_' => 'Sprachen'],
            'interessen' => ['_' => 'Interessen'],
            'motivation' => ['_' => 'Motivation'],
            'berufserfahrung' => ['_' => 'Berufserfahrung'],
            'opensource' => ['_' => 'Open Source'],
            'vortraege' => ['_' => 'Vorträge'],
            'ausbildung' => ['_' => 'Ausbildung'],
        ];
    }
}
