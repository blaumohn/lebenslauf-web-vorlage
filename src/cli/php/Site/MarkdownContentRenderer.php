<?php

namespace App\Cli\Site;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Twig\Environment;
use Twig\Markup;

final class MarkdownContentRenderer
{
    private GithubFlavoredMarkdownConverter $converter;

    public function __construct(private readonly Environment $twig)
    {
        $this->converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function render(string $markdown, array $context, string $name): Markup
    {
        $template = $this->twig->createTemplate(
            "{% autoescape false %}\n{$markdown}\n{% endautoescape %}",
            $name
        );
        $renderedMarkdown = $template->render($context);
        $html = (string) $this->converter->convert($renderedMarkdown);

        return new Markup($html, 'UTF-8');
    }
}
