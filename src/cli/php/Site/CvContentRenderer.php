<?php

namespace App\Cli\Site;

use App\Cli\ConfigValues;
use App\Http\Cv\CvDataNormalizer;
use App\Http\SchemaValidator;
use App\Http\Cv\CvViewModelBuilder;
use App\Cli\Site\LabelService;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

final class CvContentRenderer extends BaseContentRenderer
{
    public const CV_SCHEMA = 'lebenslauf.schema.json';

    private \App\Http\SiteHtmlCache $htmlCache;
    private SchemaValidator $validator;
    private Environment $twig;
    private CvViewModelBuilder $viewBuilder;
    private string $labelsPath;

    public function __construct(ConfigValues $config, string $rootPath)
    {
        parent::__construct($config, $rootPath);
        $this->htmlCache = $this->buildStorage();
        $this->validator = $this->buildValidator();
        $this->twig = $this->buildTwig();
        $this->viewBuilder = new CvViewModelBuilder();
        $this->labelsPath = Path::join($rootPath, 'src', 'resources', 'build', 'labels.json');
    }

    public function sectionKey(): ?string
    {
        return 'lebenslauf';
    }

    public function schemaName(): string
    {
        return self::CV_SCHEMA;
    }

    public function render(OutputInterface $output): void
    {
        $targets = $this->resolveTargets();
        $jsonPath = Path::join($this->rootPath, 'var', 'tmp', 'lebenslauf.json');
        $this->ensureDir(dirname($jsonPath));

        foreach ($targets as $target) {
            $this->renderTarget($target, $jsonPath, $output);
        }
    }

    public function validateContent(OutputInterface $output): bool
    {
        $dataPath = $this->dataPath();
        if (!is_dir($dataPath)) {
            $output->writeln("CV: Verzeichnis fehlt ({$dataPath}) — übersprungen.");
            return true;
        }
        $valid = true;
        foreach ($this->collectTargets($dataPath) as $target) {
            $yamlPath = $target['yaml'];
            $entry = basename($yamlPath);
            if (!$this->validateYamlFile($yamlPath, self::CV_SCHEMA, "CV: {$entry}", $output)) {
                $valid = false;
            }
        }
        return $valid;
    }

    private function resolveTargets(): array
    {
        $dataPath = $this->dataPath();
        if (!is_dir($dataPath)) {
            throw new \RuntimeException("CV-Datenverzeichnis nicht gefunden: {$dataPath}");
        }
        $targets = $this->collectTargets($dataPath);
        if ($targets === []) {
            throw new \RuntimeException("Keine daten-*.yaml-Dateien gefunden in: {$dataPath}");
        }
        return $targets;
    }

    private function collectTargets(string $dataPath): array
    {
        $entries = scandir($dataPath);
        if ($entries === false) {
            return [];
        }
        $targets = [];
        foreach ($entries as $entry) {
            if (!is_string($entry) || !preg_match('/^daten[-.](.+)\.yaml$/i', $entry, $m)) {
                continue;
            }
            $targets[] = ['profile' => $m[1], 'yaml' => Path::join($dataPath, $entry)];
        }
        return $targets;
    }

    private function renderTarget(array $target, string $jsonPath, OutputInterface $output): void
    {
        $profile = $target['profile'];
        $yamlPath = $target['yaml'];
        if (!is_file($yamlPath)) {
            throw new \RuntimeException("YAML nicht gefunden: {$yamlPath}");
        }
        $this->yamlToJson($yamlPath, $jsonPath);
        $decoded = $this->loadJson($jsonPath);
        $this->validate($decoded['raw'], $output);
        $langs = $this->resolveLangs();
        foreach ($langs as $lang) {
            $this->renderForLang($profile, $lang, $decoded['data'], $output);
        }
        $output->writeln("CV build completed: {$profile} ({$yamlPath})");
    }

    private function yamlToJson(string $yamlPath, string $jsonPath): void
    {
        try {
            $data = Yaml::parseFile($yamlPath);
        } catch (ParseException $e) {
            throw new \RuntimeException("YAML-Fehler: {$yamlPath}", 0, $e);
        }
        if (!is_array($data)) {
            throw new \RuntimeException("YAML-Inhalt ist keine Map: {$yamlPath}");
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($jsonPath, $json) === false) {
            throw new \RuntimeException("JSON-Schreiben fehlgeschlagen: {$jsonPath}");
        }
    }

    private function loadJson(string $jsonPath): array
    {
        $content = (string) file_get_contents($jsonPath);
        $raw = json_decode($content);
        $data = json_decode($content, true);
        if (!is_array($data) || $raw === null) {
            throw new \RuntimeException("Ungültiges JSON: {$jsonPath}");
        }
        return ['raw' => $raw, 'data' => $data];
    }

    private function validate(mixed $rawData, OutputInterface $output): void
    {
        $errors = $this->validator->validate($rawData);
        if ($errors === []) {
            return;
        }
        $output->writeln('<error>Schema-Validierung fehlgeschlagen:</error>');
        foreach ($errors as $error) {
            $output->writeln("- {$error}");
        }
        throw new \RuntimeException('Schema-Validierung fehlgeschlagen.');
    }

    private function renderForLang(string $profile, string $lang, array $data, OutputInterface $output): void
    {
        $cvFooter = $this->loadCvFooter($lang);
        $labels = LabelService::fromJsonFile($this->labelsPath, $lang)->all();
        $normalized = (new CvDataNormalizer($lang))->normalize($data);
        $this->savePrivate($profile, $lang, $normalized, $labels, $cvFooter);
        $this->renderPublicIfDefault($profile, $lang, $normalized, $labels, $cvFooter, $output);
        $output->writeln("Privates CV gerendert: Profil {$profile} ({$lang}).");
    }

    private function loadSiteHeader(string $lang): string
    {
        $html = $this->htmlCache->getHeaderFragmentForLang($lang);
        if ($html === null) {
            throw new \RuntimeException("Site-Header-Fragment nicht gefunden für Sprache: {$lang}. SiteHeaderRenderer muss zuerst laufen.");
        }
        return $html;
    }

    private function loadCvFooter(string $lang): string
    {
        $html = $this->htmlCache->getCvFooterFragmentForLang($lang);
        if ($html === null) {
            throw new \RuntimeException("CV-Footer-Fragment nicht gefunden für Sprache: {$lang}. SiteFooterRenderer muss zuerst laufen.");
        }
        return $html;
    }

    private function savePrivate(string $profile, string $lang, array $normalized, array $labels, string $cvFooter): void
    {
        $view = $this->viewBuilder->build($normalized);
        $html = $this->renderPrivate($view, $labels, $lang, $cvFooter);
        $this->htmlCache->savePrivateHtmlForLang($profile, $html, $lang);
    }

    private function renderPublicIfDefault(string $profile, string $lang, array $normalized, array $labels, string $cvFooter, OutputInterface $output): void
    {
        if (!$this->isDefaultProfile($profile)) {
            return;
        }
        $siteHeader = $this->loadSiteHeader($lang);
        $siteNameKurz = $this->loadSiteNameKurz();
        $view = $this->viewBuilder->build($normalized);
        $html = $this->renderPublic($view, $labels, $lang, $siteHeader, $siteNameKurz, $cvFooter);
        $this->htmlCache->savePublicHtmlForLang($html, $lang);
        $output->writeln("Öffentliches CV gerendert: Profil {$profile} ({$lang}).");
    }

    public function renderPrivate(array $data, array $labels, string $lang, string $cvFooter): string
    {
        return $this->twig->render('cv_private.html.twig', $this->baseVars($data, $labels, $lang) + [
            'cv_footer' => $cvFooter,
        ]);
    }

    public function renderPublic(array $data, array $labels, string $lang, string $siteHeader, string $siteNameKurz, string $cvFooter): string
    {
        return $this->twig->render('cv_public.html.twig', $this->baseVars($data, $labels, $lang) + [
            'site_header' => $siteHeader,
            'site_name_kurz' => $siteNameKurz,
            'cv_footer' => $cvFooter,
        ]);
    }

    private function baseVars(array $data, array $labels, string $lang): array
    {
        return [
            'cv' => $data,
            'etiketten' => $labels['cv']['childLabels'],
            'lang' => $this->normalizeLang($lang),
            'title' => $labels['cv']['value'],
        ];
    }

    private function normalizeLang(string $lang): string
    {
        $lang = strtolower(trim($lang));
        return $lang === '' ? 'de' : $lang;
    }

    private function isDefaultProfile(string $profile): bool
    {
        $public = trim($this->config->get('LEBENSLAUF_PUBLIC_PROFILE'));
        return strcasecmp($profile, $public === '' ? 'default' : $public) === 0;
    }

    private function dataPath(): string
    {
        return Path::join($this->resolveContentBase(), 'lebenslauf');
    }

    private function buildValidator(): SchemaValidator
    {
        $schema = Path::join($this->rootPath, 'src', 'resources', 'build', 'schemas', self::CV_SCHEMA);
        return new SchemaValidator($schema);
    }

    private function ensureDir(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}
