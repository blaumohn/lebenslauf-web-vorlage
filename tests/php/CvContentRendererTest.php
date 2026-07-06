<?php

declare(strict_types=1);

use App\Cli\ConfigValues;
use App\Cli\Site\CvContentRenderer;
use App\Http\Cv\CvDataNormalizer;
use App\Http\Cv\CvViewModelBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class CvContentRendererTest extends TestCase
{
    public function testEducationWithDegreeOnlyIsRendered(): void
    {
        $data = Yaml::parseFile($this->validFixturePath());
        $normalizer = new CvDataNormalizer('de');
        $builder = new CvViewModelBuilder();
        $view = $builder->build($normalizer->normalize($data));

        $html = $this->renderer()->renderPublic($view, $this->labels(), 'de', '', '', '');

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

        $html = $this->renderer()->renderPublic($view, $this->labels(), 'en', '', '', '');

        $this->assertStringContainsString('<html lang="en">', $html);
    }

    public function testPublicCvRendersSiteNameKurzInsteadOfCvName(): void
    {
        $data = Yaml::parseFile($this->validFixturePath());
        $normalizer = new CvDataNormalizer('de');
        $builder = new CvViewModelBuilder();
        $view = $builder->build($normalizer->normalize($data));

        $html = $this->renderer()->renderPublic($view, $this->labels(), 'de', '', 'Site-Kurzname', '');

        $this->assertStringContainsString('Site-Kurzname', $html);
        $this->assertStringNotContainsString((string) $data['kopfdaten']['name'], $html);
    }

    public function testPublicCvRendersSystemNoticeWhenGiven(): void
    {
        $data = Yaml::parseFile($this->validFixturePath());
        $normalizer = new CvDataNormalizer('de');
        $builder = new CvViewModelBuilder();
        $view = $builder->build($normalizer->normalize($data));

        $html = $this->renderer()->renderPublic($view, $this->labels(), 'de', '', '', '', 'Diese Freigabe ist abgelaufen.');

        $this->assertStringContainsString('Diese Freigabe ist abgelaufen.', $html);
    }

    public function testPublicCvOmitsSystemNoticeByDefault(): void
    {
        $data = Yaml::parseFile($this->validFixturePath());
        $normalizer = new CvDataNormalizer('de');
        $builder = new CvViewModelBuilder();
        $view = $builder->build($normalizer->normalize($data));

        $html = $this->renderer()->renderPublic($view, $this->labels(), 'de', '', '', '');

        $this->assertStringNotContainsString('system-notice', $html);
    }

    public function testPrivateCvRendersFullCvName(): void
    {
        $data = Yaml::parseFile($this->validFixturePath());
        $normalizer = new CvDataNormalizer('de');
        $builder = new CvViewModelBuilder();
        $view = $builder->build($normalizer->normalize($data));

        $html = $this->renderer()->renderPrivate($view, $this->labels(), 'de', '');

        $this->assertStringContainsString((string) $data['kopfdaten']['name'], $html);
    }

    private function renderer(): CvContentRenderer
    {
        return new CvContentRenderer(new ConfigValues(['APP_BASE_PATH' => '/']), $this->projectRoot());
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
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
