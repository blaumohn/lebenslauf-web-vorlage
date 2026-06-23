<?php

namespace App\Cli\Site;

use App\Cli\ConfigValues;
use App\Http\Storage\FileStorage;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

final class HomeContentRenderer extends BaseContentRenderer
{
    private Environment $twig;
    private FileStorage $storage;

    public function __construct(ConfigValues $config, string $rootPath)
    {
        parent::__construct($config, $rootPath);
        $this->twig = $this->buildTwig();
        $this->storage = new FileStorage();
    }

    public function sectionKey(): ?string
    {
        return 'home';
    }

    public function render(OutputInterface $output): void
    {
        $yamlPath = $this->dataPath();
        if (!is_file($yamlPath)) {
            $output->writeln("Home: YAML nicht gefunden ({$yamlPath}), übersprungen.");
            return;
        }
        $data = Yaml::parseFile($yamlPath);
        if (!is_array($data)) {
            throw new \RuntimeException("Ungültiges Home-YAML: {$yamlPath}");
        }
        $this->assertValid($data, 'home.schema.json', $output);
        foreach ($this->resolveLangs() as $lang) {
            $this->renderForLang($data, $lang, $output);
        }
    }

    private function renderForLang(array $data, string $lang, OutputInterface $output): void
    {
        $resolved = $this->pickLang($data, $lang);
        $html = $this->twig->render('home.html.twig', $resolved + ['lang' => $lang]);
        $this->storage->writeText(Path::join($this->htmlPath(), $lang, 'index.html'), $html);
        $output->writeln("Home gerendert ({$lang}).");
    }

    private function dataPath(): string
    {
        return Path::join($this->resolveContentBase(), 'home', 'home.yaml');
    }

    private function htmlPath(): string
    {
        return Path::join($this->rootPath, 'var', 'cache', 'html', 'home');
    }
}
