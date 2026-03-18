<?php

declare(strict_types=1);

namespace App\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Psr\SimpleCache\CacheInterface;

class CacheTool
{
	public function __construct(
		private readonly CacheInterface $cache,
	) {}

	/** @return array{success: true, message: string} */
	#[McpTool(
		name: 'clear_cache',
		description: 'Clear IMAP data cache. Use when you suspect stale or outdated data after external changes.',
		annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
	)]
	public function clearCache(): array
	{
		$this->cache->clear();

		return ['success' => true, 'message' => 'IMAP data cache cleared'];
	}
}
