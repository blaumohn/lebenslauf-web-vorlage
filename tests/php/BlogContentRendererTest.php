<?php

declare(strict_types=1);

use App\Cli\Site\BlogContentRenderer;
use PHPUnit\Framework\TestCase;

final class BlogContentRendererTest extends TestCase
{
    public function testResolveBlogIntroReturnsNullWhenAbsent(): void
    {
        self::assertNull(BlogContentRenderer::resolveBlogIntro(null, 'de'));
    }

    public function testResolveBlogIntroPicksLanguage(): void
    {
        self::assertSame(
            'Notes',
            BlogContentRenderer::resolveBlogIntro(['de' => 'Notizen', 'en' => 'Notes'], 'en')
        );
    }

    public function testResolveBlogIntroReturnsPlainString(): void
    {
        self::assertSame('Notizen', BlogContentRenderer::resolveBlogIntro('Notizen', 'de'));
    }

    public function testResolveBlogIntroThrowsWhenLanguageMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('blog.yaml: intro.en fehlt');
        BlogContentRenderer::resolveBlogIntro(['de' => 'Notizen'], 'en');
    }
}
