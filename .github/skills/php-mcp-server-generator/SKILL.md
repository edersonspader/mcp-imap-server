---
name: php-mcp-server-generator
description: 'Generate a complete PHP Model Context Protocol server project with tools, resources, prompts, and tests using the official PHP SDK'
---

# PHP MCP Server Generator

You are a PHP MCP server generator. Create a complete, production-ready PHP MCP server project using the official PHP SDK.

## Project Requirements

Ask the user for:
1. **Project name** (e.g., "my-mcp-server")
2. **Server description** (e.g., "A file management MCP server")
3. **Transport type** (stdio, http, or both)
4. **Tools to include** (e.g., "file read", "file write", "list directory")
5. **Whether to include resources and prompts**
6. **External service** (e.g., IMAP, database, REST API) — if any, ask for connection parameters
7. **PHP version** (8.5+ required)

## Project Structure

```
/
├── .env.example
├── .gitignore
├── composer.json
├── README.md
├── phpstan.neon.dist
├── phpunit.xml.dist
├── server.php
├── docs/
│   └── README.pt-BR.md
├── src/
│   ├── Exception/
│   │   └── {DomainException}.php
│   ├── {Service}/
│   │   ├── {ServiceConfig}.php
│   │   ├── {ServiceConnection}.php
│   │   └── {ServiceConnectionFactory}.php
│   ├── Tools/
│   │   └── {ToolClass}.php
│   ├── Resources/
│   │   └── {ResourceClass}.php
│   └── Prompts/
│       └── {PromptClass}.php
└── tests/
    └── Unit/
        ├── {Service}/
        │   └── {ServiceConnectionTest}.php
        ├── Tools/
        │   └── {ToolClass}Test.php
        ├── Resources/
        │   └── {ResourceClass}Test.php
        └── Prompts/
            └── {PromptClass}Test.php
```

## File Templates

### composer.json

```json
{
    "name": "{vendor}/{project-name}",
    "description": "{Server description}",
    "type": "project",
    "license": "proprietary",
    "minimum-stability": "stable",
    "prefer-stable": true,
    "require": {
        "php": "^8.5",
        "mcp/sdk": "^0.4",
        "monolog/monolog": "^3.0",
        "symfony/cache": "^8.0",
        "vlucas/phpdotenv": "^5.6"
    },
    "require-dev": {
        "phpstan/phpstan": "^2.0",
        "phpunit/phpunit": "^13.0"
    },
    "autoload": {
        "psr-4": {
            "App\\\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "App\\\\Tests\\\\": "tests/"
        }
    },
    "config": {
        "sort-packages": true
    },
    "scripts": {
        "test": "phpunit",
        "analyse": "phpstan analyse src/ --level=9"
    }
}
```

### .gitignore

```
.env
/vendor
/var/cache
phpstan.neon
```

### README.md

```markdown
# {Project Name}

[🇧🇷 Português](docs/README.pt-BR.md)

{Server description}

## Requirements

- PHP 8.5 or higher
- Composer

## Installation

```bash
composer install
```

## Usage

### Start Server (Stdio)

```bash
php server.php
```

### Configure in Claude Desktop

```json
{
  "mcpServers": {
    "{project-name}": {
      "command": "php",
      "args": ["/absolute/path/to/server.php"]
    }
  }
}
```

### Configure in VS Code
Add to `.vscode/mcp.json`:
```json
{
  "servers": {
    "{project-name}": {
      "command": "php",
      "args": ["/absolute/path/to/server.php"]
    }
  }
}
```

## Testing

```bash
vendor/bin/phpunit
```

## Tools

- **{tool_name}**: {Tool description}

### server.php

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Mcp\Capability\Registry\Container;
use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Setup logger for debugging (optional)
$logger = new Logger('mcp-server');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));

// Setup cache for discovery
$cache = new Psr16Cache(new FilesystemAdapter('mcp-discovery', 3600, __DIR__ . '/var/cache'));

// Setup DI container — register shared services
$container = new Container();
// $container->set(ServiceConnectionFactory::class, new ServiceConnectionFactory(ServiceConfig::fromEnv()));

// Build server with discovery + DI
$server = Server::builder()
    ->setServerInfo('{Project Name}', '1.0.0')
    ->setLogger($logger)
    ->setContainer($container)
    ->setDiscovery(
        basePath: __DIR__,
        scanDirs: ['src'],
        excludeDirs: ['vendor', 'tests', 'var'],
        cache: $cache
    )
    ->build();

// Run with stdio transport
$transport = new StdioTransport();

$server->run($transport);
```

### src/Tools/ExampleTool.php

```php
<?php

declare(strict_types=1);

namespace App\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Types\ToolAnnotations;

class ExampleTool
{
    #[McpTool(
        name: 'greet',
        description: 'Greet a person by name',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }

    /** @return array{result: float} */
    #[McpTool(
        name: 'calculate',
        description: 'Perform a calculation on two numbers',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function performCalculation(
        float $a,
        float $b,
        #[Schema(pattern: '^(add|subtract|multiply|divide)$')]
        string $operation,
    ): array {
        $result = match ($operation) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b !== 0.0 ? $a / $b :
                throw new \InvalidArgumentException('Division by zero'),
        };

        return ['result' => $result];
    }
}
```

### src/Tools/ServiceTool.php (with DI + Error Handling)

```php
<?php

declare(strict_types=1);

namespace App\Tools;

use App\Exception\ConnectionException;
use App\Exception\NotFoundException;
use App\Service\ServiceConnectionFactory;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Types\ToolAnnotations;

class ServiceTool
{
    public function __construct(
        private readonly ServiceConnectionFactory $factory,
    ) {}

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'get_item',
        description: 'Get an item by ID',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function getItem(int $id): array
    {
        $connection = null;

        try {
            $connection = $this->factory->create();

            return $connection->findById($id);
        } catch (NotFoundException $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        } catch (ConnectionException $e) {
            return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
        } finally {
            $connection?->disconnect();
        }
    }

    /** @return array{success: bool, message: string} */
    #[McpTool(
        name: 'delete_item',
        description: 'Delete an item by ID. REQUIRES USER CONFIRMATION.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    public function deleteItem(int $id): array
    {
        $connection = null;

        try {
            $connection = $this->factory->create();
            $connection->delete($id);

            return ['success' => true, 'message' => "Item {$id} deleted"];
        } catch (NotFoundException $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        } catch (ConnectionException $e) {
            return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
        } finally {
            $connection?->disconnect();
        }
    }
}
```

### src/Resources/ConfigResource.php

```php
<?php

declare(strict_types=1);

namespace App\Resources;

use Mcp\Capability\Attribute\McpResource;

class ConfigResource
{
    /** @return array<string, mixed> */
    #[McpResource(
        uri: 'config://app/settings',
        name: 'app_config',
        mimeType: 'application/json'
    )]
    public function getConfiguration(): array
    {
        return [
            'version' => '1.0.0',
            'environment' => 'production',
            'features' => [
                'logging' => true,
                'caching' => true,
            ],
        ];
    }
}
```

### src/Resources/DataProvider.php

```php
<?php

declare(strict_types=1);

namespace App\Resources;

use Mcp\Capability\Attribute\McpResourceTemplate;

class DataProvider
{
    /** @return array<string, string> */
    #[McpResourceTemplate(
        uriTemplate: 'data://{category}/{id}',
        name: 'data_resource',
        mimeType: 'application/json'
    )]
    public function getData(string $category, string $id): array
    {
        return [
            'category' => $category,
            'id' => $id,
            'data' => "Sample data for {$category}/{$id}",
        ];
    }
}
```

### src/Prompts/PromptGenerator.php

```php
<?php

declare(strict_types=1);

namespace App\Prompts;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\CompletionProvider;

class PromptGenerator
{
    /** @return list<array{role: string, content: string}> */
    #[McpPrompt(name: 'code_review')]
    public function reviewCode(
        #[CompletionProvider(values: ['php', 'javascript', 'python', 'go', 'rust'])]
        string $language,
        string $code,
        #[CompletionProvider(values: ['performance', 'security', 'style', 'general'])]
        string $focus = 'general'
    ): array {
        return [
            [
                'role' => 'assistant',
                'content' => 'You are an expert code reviewer specializing in best practices and optimization.',
            ],
            [
                'role' => 'user',
                'content' => "Review this {$language} code with focus on {$focus}:\n\n```{$language}\n{$code}\n```",
            ],
        ];
    }

    /** @return list<array{role: string, content: string}> */
    #[McpPrompt]
    public function generateDocs(string $code, string $style = 'detailed'): array
    {
        return [
            [
                'role' => 'user',
                'content' => "Generate {$style} documentation for:\n\n```\n{$code}\n```",
            ],
        ];
    }
}
```

### tests/Unit/Tools/ExampleToolTest.php

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools;

use App\Tools\ExampleTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExampleTool::class)]
final class ExampleToolTest extends TestCase
{
    private ExampleTool $tool;

    protected function setUp(): void
    {
        $this->tool = new ExampleTool();
    }

    #[Test]
    public function it_greets_by_name(): void
    {
        $result = $this->tool->greet('World');

        self::assertSame('Hello, World!', $result);
    }

    #[Test]
    public function it_calculates_addition(): void
    {
        $result = $this->tool->performCalculation(5, 3, 'add');

        self::assertSame(['result' => 8.0], $result);
    }

    #[Test]
    public function it_throws_on_division_by_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Division by zero');

        $this->tool->performCalculation(10, 0, 'divide');
    }
}
```

### tests/Unit/Tools/ServiceToolTest.php (with DI mocks)

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools;

use App\Exception\ConnectionException;
use App\Exception\NotFoundException;
use App\Service\ServiceConnection;
use App\Service\ServiceConnectionFactory;
use App\Tools\ServiceTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceTool::class)]
final class ServiceToolTest extends TestCase
{
    private ServiceConnection $connection;
    private ServiceTool $tool;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ServiceConnection::class);

        $factory = $this->createStub(ServiceConnectionFactory::class);
        $factory->method('create')->willReturn($this->connection);

        $this->tool = new ServiceTool($factory);
    }

    #[Test]
    public function it_gets_item(): void
    {
        $expected = ['id' => 1, 'name' => 'Test'];

        $this->connection->expects(self::once())->method('findById')->with(1)->willReturn($expected);
        $this->connection->expects(self::once())->method('disconnect');

        $result = $this->tool->getItem(1);

        self::assertSame($expected, $result);
    }

    #[Test]
    public function it_returns_error_on_not_found(): void
    {
        $this->connection->expects(self::once())->method('findById')
            ->willThrowException(new NotFoundException('Item 99 not found'));
        $this->connection->expects(self::once())->method('disconnect');

        $result = $this->tool->getItem(99);

        self::assertTrue($result['error']);
        self::assertStringContainsString('99', $result['message']);
    }

    #[Test]
    public function it_returns_error_on_connection_failure(): void
    {
        $this->connection->expects(self::never())->method('disconnect');

        $factory = $this->createStub(ServiceConnectionFactory::class);
        $factory->method('create')->willThrowException(new ConnectionException('Timeout'));

        $tool = new ServiceTool($factory);
        $result = $tool->getItem(1);

        self::assertTrue($result['error']);
        self::assertStringContainsString('Connection failed', $result['message']);
    }

    #[Test]
    public function it_deletes_item(): void
    {
        $this->connection->expects(self::once())->method('delete')->with(5);
        $this->connection->expects(self::once())->method('disconnect');

        $result = $this->tool->deleteItem(5);

        self::assertTrue($result['success']);
        self::assertStringContainsString('deleted', $result['message']);
    }
}
```

### phpstan.neon.dist

```neon
parameters:
    level: 9
    paths:
        - src
    tmpDir: var/cache/.phpstan.cache
    phpVersion: 80500
```

### phpunit.xml.dist

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory="var/cache/.phpunit.cache"
         failOnRisky="true"
         failOnWarning="true"
         beStrictAboutTestsThatDoNotTestAnything="true"
         beStrictAboutOutputDuringTests="true"
         beStrictAboutCoverageMetadata="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

## Implementation Guidelines

1. **ToolAnnotations**: Every `#[McpTool]` MUST declare `annotations:` with `ToolAnnotations`. Set `readOnlyHint: true` for read-only tools, `destructiveHint: true` for destructive tools
2. **Dependency Injection**: Use constructor injection for all external services. Register in Container via `$container->set()`. NEVER instantiate services inside constructors
3. **Connection Lifecycle**: Use `$connection = null; try { $connection = $factory->create(); ... } finally { $connection?->disconnect(); }` pattern. Each tool call is stateless
4. **Error Handling**: Catch domain exceptions → return `['error' => true, 'message' => '...']`. NEVER catch `\Throwable`. Let unexpected errors propagate to the SDK
5. **Type Declarations**: Use strict types (`declare(strict_types=1);`) in all files
6. **PSR-12 Coding Standard**: Follow PHP-FIG standards
7. **Schema Validation**: Use `#[Schema]` attributes for parameter validation
8. **Testing**: Write PHPUnit tests for all tools. Mock `ConnectionFactory` → returns mock `Connection`. Test happy path, domain exceptions, and connection failures
9. **Documentation**: PHPDoc only for PHPStan annotations (`@throws`, `@template`, `@var`, `@return` for arrays) — never duplicate types already in the signature
10. **Caching**: Always use PSR-16 cache for discovery in production
11. **Environment**: Use `.env.example` with all variables documented. Use `vlucas/phpdotenv` to load

## Tool Patterns

### Read-Only Tool
```php
/** @return array<string, mixed> */
#[McpTool(
    name: 'get_status',
    description: 'Get the current status',
    annotations: new ToolAnnotations(readOnlyHint: true),
)]
public function getStatus(): array
{
    $connection = null;

    try {
        $connection = $this->factory->create();

        return $connection->getStatus();
    } catch (ConnectionException $e) {
        return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
    } finally {
        $connection?->disconnect();
    }
}
```

### Destructive Tool
```php
/** @return array{success: bool, message: string} */
#[McpTool(
    name: 'delete_item',
    description: 'Permanently delete an item. REQUIRES USER CONFIRMATION.',
    annotations: new ToolAnnotations(destructiveHint: true),
)]
public function deleteItem(int $id): array
{
    $connection = null;

    try {
        $connection = $this->factory->create();
        $connection->delete($id);

        return ['success' => true, 'message' => "Item {$id} deleted"];
    } catch (NotFoundException $e) {
        return ['error' => true, 'message' => $e->getMessage()];
    } catch (ConnectionException $e) {
        return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
    } finally {
        $connection?->disconnect();
    }
}
```

### Tool with Validation
```php
#[McpTool(
    name: 'validate_email',
    description: 'Validate an email address format',
    annotations: new ToolAnnotations(readOnlyHint: true),
)]
public function validateEmail(
    #[Schema(format: 'email')]
    string $email,
): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
```

### Tool with Enum
```php
enum Priority: string {
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
}

/** @return array{id: int, priority: string} */
#[McpTool(
    name: 'set_priority',
    description: 'Set priority for an item',
    annotations: new ToolAnnotations(idempotentHint: true),
)]
public function setPriority(int $id, Priority $priority): array
{
    return ['id' => $id, 'priority' => $priority->value];
}
```

## Resource Patterns

### Static Resource
```php
/** @return array<string, string> */
#[McpResource(uri: 'config://settings', mimeType: 'application/json')]
public function getSettings(): array
{
    return ['key' => 'value'];
}
```

### Dynamic Resource
```php
/** @return array<string, mixed> */
#[McpResourceTemplate(uriTemplate: 'user://{id}')]
public function getUser(string $id): array
{
    return $this->users[$id] ?? throw new \RuntimeException('User not found');
}
```

## Tool vs Resource vs Prompt

| Question | Answer | Use |
|---|---|---|
| Does it perform an action or mutation? | Yes | **Tool** (`#[McpTool]`) |
| Does it expose data by URI? | Yes | **Resource** (`#[McpResource]`) |
| Does it generate a chat template? | Yes | **Prompt** (`#[McpPrompt]`) |

- **Tool**: Action invoked by LLM. Receives parameters, executes logic, returns result. Use for queries, mutations, and any operation requiring user-supplied arguments.
- **Resource**: Passive data exposed by URI. LLM reads without "invoking" an action. Use for status, config, and snapshots.
- **Prompt**: Pre-formatted message template. Use for analysis templates, reply drafts, and categorization.

## Naming Conventions

| Element | Convention | Example |
|---|---|---|
| Tool name | `snake_case`, verb + object | `list_messages`, `delete_mailbox` |
| Resource URI | `scheme://path` | `mailbox://status`, `message://INBOX/42` |
| Prompt name | `snake_case`, action | `summarize_email`, `draft_reply` |
| PHP class | PascalCase, type suffix | `MessageTool`, `MailboxStatusResource` |
| Parameters | `snake_case` in MCP attributes | `from_mailbox`, `save_path` |

## .env.example Template

Every project MUST include `.env.example` with all environment variables documented:

```env
# Required
SERVICE_HOST=service.example.com
SERVICE_USER=user@example.com
SERVICE_PASSWORD=

# Optional (defaults shown)
SERVICE_PORT=443
SERVICE_ENCRYPTION=ssl
```

- Passwords/secrets: empty value
- Required vars: example value (except secrets)
- Optional vars: default value shown
- `.env` MUST be in `.gitignore`

## Running the Server

```bash
# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Start server (stdio)
php server.php

# Start server (HTTP)
php -S localhost:8080 server-http.php

# Test with inspector
npx @modelcontextprotocol/inspector php server.php
```

## HTTP Transport

When the user asks for HTTP transport, generate the `server-http.php` file:

### server-http.php

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

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

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$logger = new Logger('mcp-http');
$logger->pushHandler(new StreamHandler('php://stderr', Level::Error));

$cache = new Psr16Cache(new FilesystemAdapter('mcp-discovery', 3600, __DIR__ . '/var/cache'));

// Setup DI container — register shared services
$container = new Container();
// $container->set(ServiceConnectionFactory::class, new ServiceConnectionFactory(ServiceConfig::fromEnv()));

$server = Server::builder()
    ->setServerInfo('{Project Name}', '1.0.0')
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

$transport = new StreamableHttpTransport($request, logger: $logger);

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
}
```

### HTTP Authentication (Bearer Token)

When the user asks for authentication on the HTTP transport, create a PSR-15 middleware:

#### src/Http/Middleware/BearerTokenAuthMiddleware.php

```php
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
```

#### Wiring in server-http.php

```php
use App\Http\Middleware\BearerTokenAuthMiddleware;

// After creating $request, before creating $transport:
$middleware = [];
$authToken = $_ENV['MCP_AUTH_TOKEN'] ?? '';

if ($authToken !== '') {
    $middleware[] = new BearerTokenAuthMiddleware($authToken);
}

$transport = new StreamableHttpTransport($request, logger: $logger, middleware: $middleware);
```

#### .env.example

```env
# HTTP Authentication (optional — leave empty to disable)
MCP_AUTH_TOKEN=
```

#### Auth design rules

| Rule | Detail |
|---|---|
| Token source | `MCP_AUTH_TOKEN` environment variable |
| Empty/unset = disabled | When empty, no middleware is registered — all requests pass through |
| OPTIONS passthrough | CORS preflight requests bypass authentication |
| Timing-safe comparison | Always use `hash_equals()` — NEVER `===` for token comparison |
| Error format | JSON-RPC error with code `-32001`, HTTP 401 + `WWW-Authenticate: Bearer` |
| Rejection | Returns before reaching the MCP SDK — no session created |

### HTTP Composer Dependencies

When HTTP transport is requested, add these extra dependencies to `composer.json`:

```json
{
    "require": {
        "nyholm/psr7": "^1.8",
        "nyholm/psr7-server": "^1.1"
    }
}
```

### HTTP Transport Examples (curl)

The HTTP transport exposes the MCP protocol over Streamable HTTP. All requests use `POST /` with `Content-Type: application/json`.

#### Initialize

```bash
curl -X POST http://localhost:8080 \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "initialize",
    "params": {
      "protocolVersion": "2025-03-26",
      "capabilities": {},
      "clientInfo": { "name": "curl", "version": "1.0.0" }
    }
  }'
```

#### List Tools

```bash
curl -X POST http://localhost:8080 \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 2,
    "method": "tools/list"
  }'
```

#### Call a Tool

```bash
curl -X POST http://localhost:8080 \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 3,
    "method": "tools/call",
    "params": {
      "name": "{tool_name}",
      "arguments": { "key": "value" }
    }
  }'
```

#### List Resources

```bash
curl -X POST http://localhost:8080 \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 4,
    "method": "resources/list"
  }'
```

#### List Prompts

```bash
curl -X POST http://localhost:8080 \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 5,
    "method": "prompts/list"
  }'
```

### README HTTP Section Template

Include in the generated README when HTTP transport is selected:

````markdown
### HTTP Transport Examples

Start the server:

```bash
php -S localhost:8080 server-http.php
```

Initialize:

```bash
curl -X POST http://localhost:8080 \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"curl","version":"1.0.0"}}}'
```

List tools:

```bash
curl -X POST http://localhost:8080 \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}'
```

Call a tool:

```bash
curl -X POST http://localhost:8080 \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"tool_name","arguments":{}}}'
```
````

## Claude Desktop Configuration

```json
{
  "mcpServers": {
    "{project-name}": {
      "command": "php",
      "args": ["/absolute/path/to/server.php"]
    }
  }
}
```

## Internationalization (i18n)

Every project SHOULD include a translated README in `docs/`.

### docs/README.pt-BR.md

Brazilian Portuguese version of the README. Structure:

```markdown
# {Project Name}

[🇬🇧 English](../README.md)

{Server description translated to pt-BR}

## Requisitos
...
```

### i18n Rules

| Rule | Detail |
|---|---|
| Location | `docs/README.{lang-code}.md` (e.g., `README.pt-BR.md`, `README.es.md`) |
| Link from root README | `[🇧🇷 Português](docs/README.pt-BR.md)` after the `# Title` |
| Link back to root | `[🇬🇧 English](../README.md)` after the `# Title` |
| Content | Full translation — all sections, tables, and descriptions |
| Code blocks | Keep as-is (commands, JSON, PHP). Only translate comments and placeholder paths (e.g., `/absolute/path/to/` → `/caminho/absoluto/para/`) |
| Tool/Resource/Prompt names | Keep original `snake_case` names — only translate descriptions |
| Flag emojis | Use country flag for the target language, 🇬🇧 for English back-link |

### Supported Languages

| Language | File | Flag |
|---|---|---|
| Português (Brasil) | `docs/README.pt-BR.md` | 🇧🇷 |
| Español | `docs/README.es.md` | 🇪🇸 |
| Français | `docs/README.fr.md` | 🇫🇷 |
| Deutsch | `docs/README.de.md` | 🇩🇪 |

When generating a project, always create the `docs/README.pt-BR.md` by default. Add other languages only if the user requests them.

Now generate the complete project based on user requirements!