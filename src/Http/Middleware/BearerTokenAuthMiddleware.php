<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class BearerTokenAuthMiddleware implements MiddlewareInterface
{
	public function __construct(
		private readonly string $expectedToken,
	) {}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		if ($request->getMethod() === 'OPTIONS') {
			return $handler->handle($request);
		}

		$header = $request->getHeaderLine('Authorization');

		if (!str_starts_with($header, 'Bearer ')) {
			return $this->unauthorized('Missing or invalid Authorization header');
		}

		$token = substr($header, 7);

		if (!hash_equals($this->expectedToken, $token)) {
			return $this->unauthorized('Invalid token');
		}

		return $handler->handle($request);
	}

	private function unauthorized(string $message): ResponseInterface
	{
		$factory = new Psr17Factory();

		$body = json_encode([
			'jsonrpc' => '2.0',
			'error' => [
				'code' => -32001,
				'message' => $message,
			],
		], \JSON_THROW_ON_ERROR);

		return $factory->createResponse(401)
			->withHeader('Content-Type', 'application/json')
			->withHeader('WWW-Authenticate', 'Bearer')
			->withBody($factory->createStream($body));
	}
}
