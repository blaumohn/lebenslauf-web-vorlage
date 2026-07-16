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
        $this->assertStringNotContainsString((string) $this->contactData()['name'], $html);
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

        $contact = $normalizer->normalize($this->contactData());
        $html = $this->renderer()->renderPrivate($view, $contact, $this->labels(), 'de', '');

        $this->assertStringContainsString((string) $this->contactData()['name'], $html);
    }

    public function testMultiplePositionsRenderAsOneCompanyGroup(): void
    {
        $data = Yaml::parseFile($this->validFixturePath());
        $normalizer = new CvDataNormalizer('de');
        $builder = new CvViewModelBuilder();
        $view = $builder->build($normalizer->normalize($data));

        $html = $this->renderer()->renderPublic($view, $this->labels(), 'de', '', '', '');

        $this->assertSame(1, substr_count($html, 'Tech Solutions GmbH'));
        $this->assertStringContainsString(
            'class="employment-group"',
            $html
        );
        $this->assertStringContainsString('Frontend Developer', $html);
        $this->assertStringContainsString('Junior Frontend Developer', $html);
    }

    public function testStationRendersWithoutCompanyHeading(): void
    {
        $data = Yaml::parseFile($this->validFixturePath());
        array_unshift($data['berufserfahrung'], [
            'station' => 'Elternzeit & Weiterbildung',
            'ort' => 'Wien',
            'zeitraum' => '2024-2025',
            'beschreibung' => 'Betreuung und Weiterbildung.',
        ]);

        $html = $this->renderPublicCv($data);

        $this->assertStringContainsString('Elternzeit &amp; Weiterbildung', $html);
        $this->assertStringContainsString('2024-2025', $html);
        $this->assertStringContainsString('Wien', $html);
        $this->assertStringContainsString('Betreuung und Weiterbildung.', $html);
        $this->assertStringContainsString('entry-station"', $html);
        $this->assertStringContainsString('class="entry-description"', $html);
    }

    public function testEmploymentPointsRenderAsOneSemanticList(): void
    {
        $html = $this->renderPublicCv(Yaml::parseFile($this->validFixturePath()));

        $this->assertMatchesRegularExpression(
            '/<ul class="entry-points">\\s*'
            . '<li class="entry-point">(?:(?!<\\/li>)[\\s\\S])*?'
            . '<p class="entry-point-text">/',
            $html
        );
        $this->assertStringNotContainsString('entry-list', $html);
    }

    public function testKnowledgeTagsAreTranslatedAndNeedNoRating(): void
    {
        $data = Yaml::parseFile($this->validFixturePath());
        $data['kenntnisse'] = [$data['kenntnisse'][0]];
        $normalizer = new CvDataNormalizer('en');
        $builder = new CvViewModelBuilder();
        $view = $builder->build($normalizer->normalize($data));

        $html = $this->renderer()->renderPublic($view, $this->labels(), 'en', '', '', '');

        $this->assertStringContainsString('Reactive components', $html);
        $this->assertStringNotContainsString('dot-scale', $html);
    }

    public function testKnowledgeLabelIsShownWhenEnabled(): void
    {
        $data = Yaml::parseFile($this->validFixturePath());
        $data['zeige_kenntnisse_label'] = true;

        $html = $this->renderPublicCv($data);

        $this->assertStringContainsString('<h2 class="section-title">Kenntnisse</h2>', $html);
    }

    public function testKnowledgeLabelIsHiddenWhenDisabled(): void
    {
        $data = Yaml::parseFile($this->validFixturePath());
        $data['zeige_kenntnisse_label'] = false;

        $html = $this->renderPublicCv($data);

        $this->assertStringNotContainsString('<h2 class="section-title">Kenntnisse</h2>', $html);
    }

    public function testKnowledgeWithMaximumAtMostTenUsesDots(): void
    {
        $data = Yaml::parseFile($this->validFixturePath());

        $html = $this->renderPublicCv($data);

        $this->assertStringContainsString('class="dot-scale" role="img" aria-label="4/5"', $html);
        $this->assertStringNotContainsString('class="skill-value"', $html);
    }

    public function testKnowledgeWithMaximumAboveTenUsesNumbersInsteadOfDots(): void
    {
        $data = Yaml::parseFile($this->validFixturePath());
        $data['kenntnisse'][1]['wert'] = 12;
        $data['kenntnisse'][2]['wert'] = 15;

        $html = $this->renderPublicCv($data);

        $this->assertStringContainsString('<div class="skill-value">12 / 15</div>', $html);
        $this->assertStringContainsString('<div class="skill-value">15 / 15</div>', $html);
        $this->assertStringNotContainsString('dot-scale', $html);
    }

    private function renderPublicCv(array $data): string
    {
        $normalizer = new CvDataNormalizer('en');
        $builder = new CvViewModelBuilder();
        $view = $builder->build($normalizer->normalize($data));

        return $this->renderer()->renderPublic($view, $this->labels(), 'en', '', '', '');
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

    private function contactData(): array
    {
        return Yaml::parseFile(
            dirname(__DIR__, 2) . '/src/resources/fixtures/lebenslauf/kontaktdaten.yaml'
        );
    }

    private function labels(): array
    {
        return [
            'cv' => [
                'value' => 'Lebenslauf',
                'childLabels' => [
                    'kenntnisse'      => ['value' => 'Kenntnisse'],
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
