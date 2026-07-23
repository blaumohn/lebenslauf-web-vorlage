<?php

namespace App\Cli\Site;

use App\Cli\ConfigValues;
use App\Http\Contact\ContactContent;
use App\Http\Cv\CvContent;
use App\Http\Cv\CvViewModelBuilder;
use App\Http\SchemaValidator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

final class CvContentRenderer extends BaseContentRenderer
{
    public const CV_SCHEMA = 'lebenslauf.schema.json';
    public const CONTACT_SCHEMA = 'kontaktdaten.schema.json';

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

    public function schemaNames(): array
    {
        return [self::CV_SCHEMA, self::CONTACT_SCHEMA];
    }

    public function render(OutputInterface $output): void
    {
        $labelCatalog = LabelCatalog::fromJsonFile($this->labelsPath);
        $contactContent = new ContactContent(
            $this->loadContactData(),
            $labelCatalog->languages()
        );
        foreach ($this->resolveTargets() as $target) {
            $this->renderTarget($target, $contactContent, $labelCatalog, $output);
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
        $targets = $this->collectTargets($dataPath);
        foreach ($targets as $target) {
            $yamlPath = $target['yaml'];
            $entry = basename($yamlPath);
            if (!$this->validateYamlFile($yamlPath, self::CV_SCHEMA, "CV: {$entry}", $output)) {
                $valid = false;
            }
        }
        if ($targets !== [] && $valid && !$this->validateContactData($output)) {
            $valid = false;
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
        $targets = array_map($this->withParsedData(...), $targets);
        $this->assertAtMostOnePublicProfile($targets);
        return $targets;
    }

    private function loadContactData(): array
    {
        $path = $this->contactDataPath();
        if (!is_file($path)) {
            throw new \RuntimeException("Kontaktdaten-YAML nicht gefunden: {$path}");
        }

        return $this->parseYamlFile($path);
    }

    private function withParsedData(array $target): array
    {
        return $target + ['data' => $this->parseYamlFile($target['yaml'])];
    }

    /** @return array<string, mixed> */
    private function parseYamlFile(string $yamlPath): array
    {
        if (!is_file($yamlPath)) {
            throw new \RuntimeException("YAML nicht gefunden: {$yamlPath}");
        }
        try {
            $data = Yaml::parseFile($yamlPath);
        } catch (ParseException $e) {
            throw new \RuntimeException("YAML-Fehler: {$yamlPath}", 0, $e);
        }
        if (!is_array($data)) {
            throw new \RuntimeException("YAML-Inhalt ist keine Map: {$yamlPath}");
        }
        return $data;
    }

    private function assertAtMostOnePublicProfile(array $targets): void
    {
        $publicProfiles = [];
        foreach ($targets as $target) {
            if (($target['data']['oeffentlich'] ?? false) === true) {
                $publicProfiles[] = $target['profile'];
            }
        }
        if (count($publicProfiles) > 1) {
            throw new \RuntimeException(
                'Mehr als ein Profil als öffentlich markiert (oeffentlich: true): ' . implode(', ', $publicProfiles)
            );
        }
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

    private function renderTarget(
        array $target,
        ContactContent $contactContent,
        LabelCatalog $labelCatalog,
        OutputInterface $output
    ): void {
        $profile = $target['profile'];
        $yamlPath = $target['yaml'];
        $data = $target['data'];
        $this->validate($this->toValidatorTree($data), $output);
        $isPublic = ($data['oeffentlich'] ?? false) === true;
        $cvContent = new CvContent($data, $labelCatalog->languages());
        $langs = $this->resolveLangs();
        foreach ($langs as $lang) {
            $this->renderForLang(
                $profile,
                $lang,
                $cvContent,
                $contactContent,
                $labelCatalog,
                $isPublic,
                $output
            );
        }
        $output->writeln("CV build completed: {$profile} ({$yamlPath})");
    }

    /** @param array<string, mixed> $data */
    private function toValidatorTree(array $data): mixed
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('CV-Daten konnten nicht serialisiert werden.');
        }
        return json_decode($json);
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

    private function renderForLang(
        string $profile,
        string $lang,
        CvContent $cvContent,
        ContactContent $contactContent,
        LabelCatalog $labelCatalog,
        bool $isPublic,
        OutputInterface $output
    ): void {
        $cvFooter = $this->loadCvFooter($lang);
        $labelsForLang = $labelCatalog->forLanguage($lang);
        $cvForLang = $cvContent->forLanguage($lang);
        $contactForLang = $contactContent->forLanguage($lang);
        $this->savePrivate(
            $profile,
            $lang,
            $cvForLang,
            $contactForLang,
            $labelsForLang,
            $cvFooter
        );
        if ($isPublic) {
            $this->renderPublicProfile(
                $profile,
                $lang,
                $cvForLang,
                $labelsForLang,
                $cvFooter,
                $output
            );
        }
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

    private function savePrivate(
        string $profile,
        string $lang,
        array $cvForLang,
        array $contactForLang,
        array $labelsForLang,
        string $cvFooter
    ): void {
        $view = $this->viewBuilder->build($cvForLang);
        $html = $this->renderPrivate($view, $contactForLang, $labelsForLang, $lang, $cvFooter);
        $this->htmlCache->savePrivateHtmlForLang($profile, $html, $lang);
    }

    private function renderPublicProfile(
        string $profile,
        string $lang,
        array $cvForLang,
        array $labelsForLang,
        string $cvFooter,
        OutputInterface $output
    ): void {
        $siteHeader = $this->loadSiteHeader($lang);
        $siteNameKurz = $this->loadSiteNameKurz();
        $view = $this->viewBuilder->build($cvForLang);

        $html = $this->renderPublic(
            $view,
            $labelsForLang,
            $lang,
            $siteHeader,
            $siteNameKurz,
            $cvFooter
        );
        $this->htmlCache->savePublicHtmlForLang($html, $lang);

        $output->writeln("Öffentliches CV gerendert: Profil {$profile} ({$lang}).");
    }

    public function renderPrivate(array $data, array $contact, array $labels, string $lang, string $cvFooter): string
    {
        return $this->twig->render('cv_private.html.twig', $this->baseVars($data, $labels, $lang) + [
            'kontaktdaten' => $contact,
            'cv_footer' => $cvFooter,
        ]);
    }

    public function renderPublic(
        array $data,
        array $labels,
        string $lang,
        string $siteHeader,
        string $siteNameKurz,
        string $cvFooter
    ): string {
        return $this->twig->render('cv_public.html.twig', $this->baseVars($data, $labels, $lang) + [
            'kontaktdaten' => [],
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

    private function dataPath(): string
    {
        return Path::join($this->resolveContentBase(), 'lebenslauf');
    }

    private function contactDataPath(): string
    {
        return Path::join($this->dataPath(), 'kontaktdaten.yaml');
    }

    private function validateContactData(OutputInterface $output): bool
    {
        $path = $this->contactDataPath();
        if (!is_file($path)) {
            $output->writeln("<error>Kontaktdaten: YAML fehlt ({$path}).</error>");
            return false;
        }

        return $this->validateYamlFile($path, self::CONTACT_SCHEMA, 'Kontaktdaten', $output);
    }

    private function buildValidator(): SchemaValidator
    {
        $schema = Path::join($this->rootPath, 'src', 'resources', 'build', 'schemas', self::CV_SCHEMA);
        return new SchemaValidator($schema);
    }

}
