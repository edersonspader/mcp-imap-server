#!/usr/bin/env php
<?php

declare(strict_types=1);

ini_set('display_errors', 'stderr');

require_once __DIR__ . '/vendor/autoload.php';

use App\Imap\ImapConfig;
use App\Imap\ImapConnectionFactory;
use Dotenv\Dotenv;
use Mcp\Capability\Registry\Container;
use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$logger = new Logger('imap-mcp');
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

$transport = new StdioTransport();

$server->run($transport);
