<?php

namespace App\Cli\Site;

use App\Http\Contact\ContactContent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

final class ContactContentRenderer extends BaseContentRenderer
{
    public const SCHEMA = 'contact.schema.json';

    public function sectionKey(): ?string
    {
        return 'contact';
    }

    public function schemaNames(): array
    {
        return [self::SCHEMA];
    }

    public function validateContent(OutputInterface $output): bool
    {
        return $this->validateYamlFile($this->dataPath(), self::SCHEMA, 'Contact', $output);
    }

    public function render(OutputInterface $output): void
    {
        $yamlPath = $this->dataPath();
        if (!is_file($yamlPath)) {
            throw new \RuntimeException("Contact-YAML nicht gefunden: {$yamlPath}");
        }
        $data = Yaml::parseFile($yamlPath);
        if (!is_array($data)) {
            throw new \RuntimeException("Ungültiges Contact-YAML: {$yamlPath}");
        }
        $this->assertValid($data, self::SCHEMA, $output);
        $labelCatalog = LabelCatalog::fromJsonFile($this->labelsPath());
        $contactContent = new ContactContent($data, $labelCatalog->languages());
        foreach ($this->resolveLangs() as $lang) {
            $this->renderForLang($contactContent, $labelCatalog, $lang, $output);
        }
    }

    private function renderForLang(
        ContactContent $contactContent,
        LabelCatalog $labelCatalog,
        string $lang,
        OutputInterface $output
    ): void {
        $contact = $contactContent->forLanguage($lang);
        $twig = $this->buildTwig();
        $contact['intro_html'] = $this->renderMarkdown(
            $twig,
            (string) $contact['intro'],
            $lang,
            "contact.{$lang}.intro"
        );
        $labelsForLang = $labelCatalog->forLanguage($lang);
        $text = array_merge($contact, $this->resolveContactLabels($labelsForLang));
        $this->writeTemplate($lang, $this->generateTemplate($text));
        $output->writeln("Contact-Template generiert ({$lang}).");
    }

    private function resolveContactLabels(array $labels): array
    {
        $contact = $labels['contact'];
        $fields = $contact['childLabels'];
        return [
            'title'         => $contact['value'],
            'name_label'    => $fields['name']['value'],
            'email_label'   => $fields['email']['value'],
            'message_label' => $fields['message']['value'],
            'submit_label'  => $fields['submit']['value'],
        ];
    }

    private function labelsPath(): string
    {
        return Path::join($this->rootPath, 'src', 'resources', 'build', 'labels.json');
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
          <article class="markdown-body">
            {{ ui.page_title(text.title) }}
            {{ text.intro_html|raw }}
            {% if form.show_error %}
              <p class="form-error">{{ form.error_text }}</p>
            {% endif %}
            <form class="contact-form" method="post" action="{{ path('/contact') }}">
              {{ form_ui.input(text.name_label, 'name', 'text', form.values.name, true) }}
              {{ form_ui.input(text.email_label, 'email', 'email', form.values.email, true, 'name@beispiel.de') }}
              {{ form_ui.textarea(text.message_label, 'message', form.values.message, 6, true) }}
              {{ form_ui.captcha(form.captcha_id, form.captcha_url) }}
              <button type="submit">{{ text.submit_label }}</button>
            </form>
          </article>
        {% endblock %}
        TWIG;
    }

    private function buildTextBlock(array $text): string
    {
        $entries = [];
        foreach ($text as $key => $value) {
            $encoded = json_encode(
                (string) $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            $entries[] = "  {$key}: {$encoded}";
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
