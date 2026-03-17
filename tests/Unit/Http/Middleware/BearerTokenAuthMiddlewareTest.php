<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Middleware;

use App\Http\Middleware\BearerTokenAuthMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(BearerTokenAuthMiddleware::class)]
final class BearerTokenAuthMiddlewareTest extends TestCase
{
	private const TOKEN = 'test-secret-token-123';
	private BearerTokenAuthMiddleware $middleware;
	private RequestHandlerInterface $handler;

	protected function setUp(): void
	{
		$this->middleware = new BearerTokenAuthMiddleware(self::TOKEN);

		$factory = new Psr17Factory();
		$response = $factory->createResponse(200);

		$this->handler = new class($response) implements RequestHandlerInterface {
			public bool $called = false;

			public function __construct(
				private readonly ResponseInterface $response,
			) {}

			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				$this->called = true;

				return $this->response;
			}
		};
	}

	#[Test]
	public function it_allows_request_with_valid_token(): void
	{
		$request = $this->createRequest('POST', 'Bearer ' . self::TOKEN);

		$response = $this->middleware->process($request, $this->handler);

		self::assertSame(200, $response->getStatusCode());
		self::assertTrue($this->handler->called);
	}

	#[Test]
	public function it_rejects_request_without_authorization_header(): void
	{
		$request = $this->createRequest('POST');

		$response = $this->middleware->process($request, $this->handler);

		self::assertSame(401, $response->getStatusCode());
		self::assertFalse($this->handler->called);
		self::assertStringContainsString('Missing', $this->getResponseBody($response));
	}

	#[Test]
	public function it_rejects_request_with_invalid_token(): void
	{
		$request = $this->createRequest('POST', 'Bearer wrong-token');

		$response = $this->middleware->process($request, $this->handler);

		self::assertSame(401, $response->getStatusCode());
		self::assertFalse($this->handler->called);
		self::assertStringContainsString('Invalid token', $this->getResponseBody($response));
	}

	#[Test]
	public function it_rejects_non_bearer_authorization(): void
	{
		$request = $this->createRequest('POST', 'Basic dXNlcjpwYXNz');

		$response = $this->middleware->process($request, $this->handler);

		self::assertSame(401, $response->getStatusCode());
		self::assertFalse($this->handler->called);
	}

	#[Test]
	public function it_passes_options_request_without_auth(): void
	{
		$request = $this->createRequest('OPTIONS');

		$response = $this->middleware->process($request, $this->handler);

		self::assertSame(200, $response->getStatusCode());
		self::assertTrue($this->handler->called);
	}

	#[Test]
	public function it_returns_www_authenticate_header_on_401(): void
	{
		$request = $this->createRequest('POST');

		$response = $this->middleware->process($request, $this->handler);

		self::assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
	}

	#[Test]
	public function it_returns_json_rpc_error_format(): void
	{
		$request = $this->createRequest('POST');

		$response = $this->middleware->process($request, $this->handler);
		$body = json_decode($this->getResponseBody($response), true);

		self::assertSame('2.0', $body['jsonrpc']);
		self::assertSame(-32001, $body['error']['code']);
		self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
	}

	private function createRequest(string $method, string $authorization = ''): ServerRequestInterface
	{
		$factory = new Psr17Factory();
		$request = $factory->createServerRequest($method, 'http://localhost:8080/mcp');

		if ($authorization !== '') {
			$request = $request->withHeader('Authorization', $authorization);
		}

		return $request;
	}

	private function getResponseBody(ResponseInterface $response): string
	{
		$response->getBody()->rewind();

		return $response->getBody()->getContents();
	}
}
