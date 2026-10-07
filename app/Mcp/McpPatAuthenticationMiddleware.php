<?php

declare(strict_types=1);

namespace Hiram9112\Benehom\Mcp;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class McpPatAuthenticationMiddleware implements MiddlewareInterface
{
    /** @var \Closure(string): ?int */
    private \Closure $authenticate;

    /** @param callable(string): ?int $authenticate */
    public function __construct(
        callable $authenticate,
        private readonly McpAuthenticatedUserContext $context,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
        $this->authenticate = \Closure::fromCallable($authenticate);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorization = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer[ \t]+([^\s,]+)$/Di', $authorization, $matches)) {
            return $this->unauthorized();
        }

        try {
            $authenticatedUserId = ($this->authenticate)($matches[1]);
        } catch (\Throwable) {
            return $this->unauthorized();
        }

        if (!is_int($authenticatedUserId) || $authenticatedUserId <= 0) {
            return $this->unauthorized();
        }

        $this->context->set($authenticatedUserId);

        try {
            return $handler->handle($request);
        } finally {
            $this->context->clear();
        }
    }

    private function unauthorized(): ResponseInterface
    {
        return $this->responseFactory->createResponse(401)
            ->withHeader('WWW-Authenticate', 'Bearer')
            ->withHeader('Cache-Control', 'no-store');
    }
}
