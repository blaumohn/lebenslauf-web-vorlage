<?php

declare(strict_types=1);

use App\Http\Response;
use App\Http\ResponseHelper;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response as PsrResponse;

final class ResponseHeadersTest extends TestCase
{
    public function testResponseHelperHtmlSetsContentTypeOptions(): void
    {
        $response = ResponseHelper::html(new PsrResponse(), '<h1>OK</h1>');

        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
    }

    public function testResponseHtmlSendsContentTypeOptions(): void
    {
        $response = Response::html('<h1>OK</h1>');

        $this->assertSame('nosniff', $response->headers()['X-Content-Type-Options'] ?? null);
    }
}
