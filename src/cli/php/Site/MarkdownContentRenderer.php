<?php

namespace App\Cli\Site;

use App\Http\Url\QueryString;
use League\CommonMark\Environment\Environment as MarkdownEnvironment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Twig\Environment;
use Twig\Markup;

final class MarkdownContentRenderer
{
    private MarkdownConverter $converter;
    private string $currentLang = '';

    public function __construct(private readonly Environment $twig)
    {
        $environment = new MarkdownEnvironment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event): void {
            $this->appendLangToInternalLinks($event);
        });

        $this->converter = new MarkdownConverter($environment);
    }

    public function render(string $markdown, array $context, string $name, string $lang): Markup
    {
        $template = $this->twig->createTemplate(
            "{% autoescape false %}\n{$markdown}\n{% endautoescape %}",
            $name
        );
        $renderedMarkdown = $template->render($context);

        $this->currentLang = $lang;
        $html = (string) $this->converter->convert($renderedMarkdown);

        return new Markup($html, 'UTF-8');
    }

    private function appendLangToInternalLinks(DocumentParsedEvent $event): void
    {
        $walker = $event->getDocument()->walker();

        while ($walkerEvent = $walker->next()) {
            $node = $walkerEvent->getNode();
            if (!$walkerEvent->isEntering() || !$node instanceof Link) {
                continue;
            }

            $url = $node->getUrl();
            if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
                continue;
            }

            $node->setUrl($this->withLangParam($url, $this->currentLang));
        }
    }

    private function withLangParam(string $url, string $lang): string
    {
        [$path, $query, $fragment] = $this->splitUrl($url);

        if ($this->hasLangParam($query)) {
            return $path . '?' . $query . $fragment;
        }

        return $path . '?' . QueryString::withParam($query, 'lang', $lang) . $fragment;
    }

    /** @return array{0: string, 1: ?string, 2: string} */
    private function splitUrl(string $url): array
    {
        $fragment = '';
        $hashPos = strpos($url, '#');
        if ($hashPos !== false) {
            $fragment = substr($url, $hashPos);
            $url = substr($url, 0, $hashPos);
        }

        $queryPos = strpos($url, '?');
        $path = $queryPos === false ? $url : substr($url, 0, $queryPos);
        $query = $queryPos === false ? null : substr($url, $queryPos + 1);

        return [$path, $query, $fragment];
    }

    private function hasLangParam(?string $query): bool
    {
        if ($query === null) {
            return false;
        }

        foreach (QueryString::parse($query) as [$key]) {
            if ($key === 'lang') {
                return true;
            }
        }

        return false;
    }
}
