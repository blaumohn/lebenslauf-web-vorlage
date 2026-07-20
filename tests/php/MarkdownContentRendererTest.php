<?php

declare(strict_types=1);

use App\Cli\Site\MarkdownContentRenderer;
use App\Http\Templating\TwigFactory;
use PHPUnit\Framework\TestCase;

final class MarkdownContentRendererTest extends TestCase
{
    public function testRendersTwigBeforeMarkdown(): void
    {
        $twig = TwigFactory::create(dirname(__DIR__, 2) . '/src/resources/templates');
        TwigFactory::configure($twig, '/basis');
        $renderer = new MarkdownContentRenderer($twig);

        $html = (string) $renderer->render(
            "## Über {{ site.name_kurz }}\n\n[Kontakt]({{ path('/contact') }})",
            ['site' => ['name_kurz' => 'Alex B.']],
            'test.markdown',
            'de'
        );

        self::assertStringContainsString('<h2>Über Alex B.</h2>', $html);
        self::assertStringContainsString('<a href="/basis/contact?lang=de">Kontakt</a>', $html);
    }

    public function testStripsRawHtmlAndUnsafeLinks(): void
    {
        $twig = TwigFactory::create(dirname(__DIR__, 2) . '/src/resources/templates');
        $renderer = new MarkdownContentRenderer($twig);

        $html = (string) $renderer->render(
            '<script>alert("x")</script> [Link](javascript:alert(1))',
            [],
            'test.unsafe-markdown',
            'de'
        );

        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('javascript:', $html);
    }

    public function testInternalLinkWithExistingQueryKeepsItAndAppendsLang(): void
    {
        $twig = TwigFactory::create(dirname(__DIR__, 2) . '/src/resources/templates');
        $renderer = new MarkdownContentRenderer($twig);

        $html = (string) $renderer->render(
            '[Anderer Post](/blog/anderer-post?ref=intern)',
            [],
            'test.internal-link',
            'es'
        );

        self::assertStringContainsString('href="/blog/anderer-post?ref=intern&amp;lang=es"', $html);
    }

    public function testInternalLinkWithExplicitLangIsNotOverridden(): void
    {
        $twig = TwigFactory::create(dirname(__DIR__, 2) . '/src/resources/templates');
        $renderer = new MarkdownContentRenderer($twig);

        $html = (string) $renderer->render(
            '[Englische Version](/blog/anderer-post?lang=en)',
            [],
            'test.internal-link-explicit',
            'es'
        );

        self::assertStringContainsString('href="/blog/anderer-post?lang=en"', $html);
    }

    public function testInternalLinkWithFragmentKeepsFragmentAfterLang(): void
    {
        $twig = TwigFactory::create(dirname(__DIR__, 2) . '/src/resources/templates');
        $renderer = new MarkdownContentRenderer($twig);

        $html = (string) $renderer->render(
            '[Abschnitt](/blog/anderer-post?ref=intern#abschnitt)',
            [],
            'test.internal-link-fragment',
            'es'
        );

        self::assertStringContainsString('href="/blog/anderer-post?ref=intern&amp;lang=es#abschnitt"', $html);
    }

    public function testExternalLinkIsNotChanged(): void
    {
        $twig = TwigFactory::create(dirname(__DIR__, 2) . '/src/resources/templates');
        $renderer = new MarkdownContentRenderer($twig);

        $html = (string) $renderer->render(
            '[Extern](https://example.com/seite)',
            [],
            'test.external-link',
            'de'
        );

        self::assertStringContainsString('href="https://example.com/seite"', $html);
    }
}
