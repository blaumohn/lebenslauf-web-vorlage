<?php

namespace App\Cli\Site;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

final class ContactContentRenderer extends BaseContentRenderer
{
    public function render(OutputInterface $output): void
    {
        $yamlPath = $this->dataPath();
        if (!is_file($yamlPath)) {
            $output->writeln("Contact: YAML nicht gefunden ({$yamlPath}), übersprungen.");
            return;
        }
        $data = Yaml::parseFile($yamlPath);
        if (!is_array($data)) {
            throw new \RuntimeException("Ungültiges Contact-YAML: {$yamlPath}");
        }
        $this->assertValid($data, 'contact.schema.json', $output);
        foreach ($this->resolveLangs() as $lang) {
            $this->renderForLang($data, $lang, $output);
        }
    }

    private function renderForLang(array $data, string $lang, OutputInterface $output): void
    {
        $text = $this->pickLang($data, $lang);
        $this->writeTemplate($lang, $this->generateTemplate($text));
        $output->writeln("Contact-Template generiert ({$lang}).");
    }

    private function generateTemplate(array $text): string
    {
        $textBlock = $this->buildTextBlock($text);
        return <<<TWIG
        {% extends 'base.html.twig' %}
        {% import 'components/site/lib.html.twig' as ui %}
        {% import 'components/site/form.html.twig' as form_ui %}
        {% set text = {
        {$textBlock}
        } %}
        {% block content %}
          {{ ui.page_title(text.title) }}
          {{ ui.muted(text.intro) }}
          {% if form.show_error %}
            {{ ui.muted(form.error_text) }}
          {% endif %}
          <form method="post" action="{{ path('/contact') }}">
            {{ form_ui.input(text.name_label, 'name', 'text', form.values.name, true) }}
            <br><br>
            {{ form_ui.input(text.email_label, 'email', 'email', form.values.email, true, 'name@beispiel.de') }}
            <br><br>
            {{ form_ui.textarea(text.message_label, 'message', form.values.message, 6, true) }}
            <br><br>
            {{ form_ui.captcha(form.captcha_id, form.captcha_url) }}
            <button type="submit">{{ text.submit_label }}</button>
          </form>
        {% endblock %}
        TWIG;
    }

    private function buildTextBlock(array $text): string
    {
        $entries = [];
        foreach ($text as $key => $value) {
            $escaped = str_replace("'", "\\'", (string) $value);
            $entries[] = "  {$key}: '{$escaped}'";
        }
        return implode(",\n", $entries);
    }

    private function writeTemplate(string $lang, string $content): void
    {
        $path = $this->templatePath($lang);
        $this->ensureDir(dirname($path));
        file_put_contents($path, $content);
    }

    private function dataPath(): string
    {
        return Path::join($this->rootPath, 'src', 'resources', 'contact', 'contact.yaml');
    }

    private function templatePath(string $lang): string
    {
        return Path::join($this->rootPath, 'var', 'cache', 'templates', 'contact', "{$lang}.twig");
    }

    private function ensureDir(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}
