<?php

declare(strict_types=1);

use App\Cli\Site\BlogIndexRenderer;
use PHPUnit\Framework\TestCase;

final class BlogIndexRendererTest extends TestCase
{
    public function testResolveBlogIntroReturnsNullWhenAbsent(): void
    {
        self::assertNull(BlogIndexRenderer::resolveBlogIntro(null, 'de'));
    }

    public function testResolveBlogIntroPicksLanguage(): void
    {
        self::assertSame(
            'Notas',
            BlogIndexRenderer::resolveBlogIntro(['de' => 'Notizen', 'es' => 'Notas'], 'es')
        );
    }

    public function testResolveBlogIntroReturnsPlainString(): void
    {
        self::assertSame('Notizen', BlogIndexRenderer::resolveBlogIntro('Notizen', 'de'));
    }

    public function testResolveBlogIntroThrowsWhenLanguageMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('blog.yaml: intro.es fehlt');
        BlogIndexRenderer::resolveBlogIntro(['de' => 'Notizen'], 'es');
    }
}
