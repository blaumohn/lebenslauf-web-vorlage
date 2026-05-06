<?php

namespace App\Http\Actions;

use App\Http\AppContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminTaskAction
{
    public function __construct(private readonly AppContext $context) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $count = $this->context->adminTaskRunner->runPending();
        $response->getBody()->write($count > 0 ? "ok:{$count}\n" : "idle\n");
        return $response->withHeader('Content-Type', 'text/plain');
    }
}
