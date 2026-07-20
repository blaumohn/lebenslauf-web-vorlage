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
            'test.markdown'
        );

        self::assertStringContainsString('<h2>Über Alex B.</h2>', $html);
        self::assertStringContainsString('<a href="/basis/contact">Kontakt</a>', $html);
    }

    public function testStripsRawHtmlAndUnsafeLinks(): void
    {
        $twig = TwigFactory::create(dirname(__DIR__, 2) . '/src/resources/templates');
        $renderer = new MarkdownContentRenderer($twig);

        $html = (string) $renderer->render(
            '<script>alert("x")</script> [Link](javascript:alert(1))',
            [],
            'test.unsafe-markdown'
        );

        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('javascript:', $html);
    }
}
