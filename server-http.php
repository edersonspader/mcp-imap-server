#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Http\Middleware\BearerTokenAuthMiddleware;
use App\Imap\ImapConfig;
use App\Imap\ImapConnectionFactory;
use Dotenv\Dotenv;
use Mcp\Capability\Registry\Container;
use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$logger = new Logger('imap-mcp-http');
$logger->pushHandler(new StreamHandler('php://stderr', Level::Debug));

$cache = new Psr16Cache(new FilesystemAdapter('mcp-discovery', 3600, __DIR__ . '/var/cache'));

$container = new Container();
$container->set(ImapConnectionFactory::class, new ImapConnectionFactory(ImapConfig::fromEnv()));

$server = Server::builder()
	->setServerInfo('IMAP MCP Server', '1.0.0')
	->setLogger($logger)
	->setContainer($container)
	->setDiscovery(
		basePath: __DIR__,
		scanDirs: ['src'],
		excludeDirs: ['vendor', 'tests', 'var'],
		cache: $cache,
	)
	->build();

$psr17Factory = new Psr17Factory();
$creator = new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
$request = $creator->fromGlobals();

$middleware = [];
$authToken = $_ENV['MCP_AUTH_TOKEN'] ?? '';

if ($authToken !== '') {
	$middleware[] = new BearerTokenAuthMiddleware($authToken);
}

$transport = new StreamableHttpTransport($request, logger: $logger, middleware: $middleware);

$response = $server->run($transport);

// Emit the PSR-7 response
http_response_code($response->getStatusCode());

foreach ($response->getHeaders() as $name => $values) {
	foreach ($values as $value) {
		header("{$name}: {$value}", false);
	}
}

$body = $response->getBody();
$body->rewind();

while (!$body->eof()) {
	echo $body->read(8192);

	if (\function_exists('ob_flush')) {
		ob_flush();
	}

	flush();
}
