# IMAP MCP Server

[🇧🇷 Português](docs/README.pt-BR.md)

Servidor MCP para acesso ao protocolo IMAP. Permite que LLMs leiam, busquem, organizem e-mails e gerenciem mailboxes via protocolo MCP.

## Requirements

- PHP 8.5 or higher
- Composer

## Installation

```bash
composer install
cp .env.example .env
# Edit .env with your IMAP credentials
```

## Configuration

Edit `.env` with your IMAP server details:

| Variable | Description | Default |
|---|---|---|
| `IMAP_HOST` | IMAP server hostname | — |
| `IMAP_PORT` | IMAP server port | `993` |
| `IMAP_USER` | IMAP username/email | — |
| `IMAP_PASSWORD` | IMAP password | — |
| `IMAP_ENCRYPTION` | Encryption type (`ssl`, `tls`, or empty) | `ssl` |
| `IMAP_VALIDATE_CERT` | Validate SSL certificate | `true` |
| `SMTP_HOST` | SMTP server hostname | — |
| `SMTP_PORT` | SMTP server port | `587` |
| `SMTP_USER` | SMTP username/email | — |
| `SMTP_PASSWORD` | SMTP password | — |
| `SMTP_FROM` | Default sender address | — |
| `SMTP_ENCRYPTION` | Encryption type (`tls`, `ssl`, or empty) | `tls` |
| `SMTP_ALLOWED_FROM` | Comma-separated list of allowed sender aliases | value of `SMTP_FROM` |
| `MCP_AUTH_TOKEN` | Bearer token for HTTP transport authentication (empty = disabled) | — |

## Usage

### Start Server (Stdio)

```bash
php server.php
```

### Start Server (HTTP)

```bash
php -S localhost:8080 server-http.php
```

### HTTP Authentication

The HTTP transport supports optional Bearer token authentication. Set `MCP_AUTH_TOKEN` in `.env` to enable it:

```env
MCP_AUTH_TOKEN=your-secret-token
```

When enabled, all requests must include the `Authorization` header:

```bash
curl -X POST http://localhost:8080 \
  -H "Authorization: Bearer your-secret-token" \
  -H "Content-Type: application/json" \
  -d '{...}'
```

Leave `MCP_AUTH_TOKEN` empty or unset to disable authentication.

### Running as a systemd Service

To run the HTTP server as a user-level systemd service (no root required):

Create `~/.config/systemd/user/imap-mcp-http.service`:

```ini
[Unit]
Description=IMAP MCP Server (Streamable HTTP)
After=network-online.target

[Service]
Type=simple
WorkingDirectory=/absolute/path/to/project
EnvironmentFile=/absolute/path/to/project/.env

ExecStart=/usr/bin/php -S 127.0.0.1:8080 server-http.php

Restart=on-failure
RestartSec=5

NoNewPrivileges=yes
ProtectHome=read-only
PrivateTmp=yes

StandardOutput=journal
StandardError=journal
SyslogIdentifier=imap-mcp-http

[Install]
WantedBy=default.target
```

Manage the service:

```bash
# Reload after creating/editing the unit file
systemctl --user daemon-reload

# Enable on login and start
systemctl --user enable --now imap-mcp-http

# Check status
systemctl --user status imap-mcp-http

# View logs
journalctl --user -u imap-mcp-http -f

# To keep the service running after logout
loginctl enable-linger $USER
```

### HTTP Transport Examples

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

#### Call a Tool (count_messages)

```bash
curl -X POST http://localhost:8080 \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 3,
    "method": "tools/call",
    "params": {
      "name": "count_messages",
      "arguments": { "mailbox": "INBOX" }
    }
  }'
```

#### Call a Tool (list_messages)

```bash
curl -X POST http://localhost:8080 \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 4,
    "method": "tools/call",
    "params": {
      "name": "list_messages",
      "arguments": { "mailbox": "INBOX", "limit": 5 }
    }
  }'
```

#### Call a Tool (search_messages)

```bash
curl -X POST http://localhost:8080 \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 5,
    "method": "tools/call",
    "params": {
      "name": "search_messages",
      "arguments": { "mailbox": "INBOX", "unseen": true, "limit": 10 }
    }
  }'
```

#### List Resources

```bash
curl -X POST http://localhost:8080 \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 6,
    "method": "resources/list"
  }'
```

#### List Prompts

```bash
curl -X POST http://localhost:8080 \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 7,
    "method": "prompts/list"
  }'
```

### Configure in Claude Desktop

#### Stdio

```json
{
  "mcpServers": {
    "imap-server": {
      "command": "php",
      "args": ["/absolute/path/to/server.php"]
    }
  }
}
```

#### Streamable HTTP (via mcp-remote)

Claude Desktop does not support Streamable HTTP natively. Use `mcp-remote` as a bridge:

```json
{
  "mcpServers": {
    "imap-server": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "http://localhost:8080",
        "--header",
        "Authorization: Bearer your-secret-token"
      ]
    }
  }
}
```

> Remove the `--header` and `Authorization` args if `MCP_AUTH_TOKEN` is not set.

### Configure in VS Code

Add to `.vscode/mcp.json`:

#### Stdio

```json
{
  "servers": {
    "imap-server": {
      "command": "php",
      "args": ["/absolute/path/to/server.php"]
    }
  }
}
```

#### Streamable HTTP

```json
{
  "servers": {
    "imap-server": {
      "type": "streamable-http",
      "url": "http://localhost:8080",
      "headers": {
        "Authorization": "Bearer your-secret-token"
      }
    }
  }
}
```

> Remove the `headers` block if `MCP_AUTH_TOKEN` is not set.

## Tools

| Tool | Description |
|---|---|
| **list_mailboxes** | List all mailboxes/folders |
| **count_messages** | Count messages in a mailbox (total, unseen, recent) |
| **create_mailbox** | Create a new mailbox/folder |
| **delete_mailbox** | Delete a mailbox/folder |
| **list_messages** | List messages with pagination |
| **search_messages** | Search messages by criteria (from, subject, date, flags) |
| **read_message** | Read full message content (headers + body) |
| **get_message_headers** | Get message headers only |
| **move_message** | Move message to another mailbox |
| **copy_message** | Copy message to another mailbox |
| **delete_message** | Delete a message |
| **flag_message** | Set/clear message flags (Seen, Flagged, Answered, Draft) |
| **get_attachments** | Save message attachments to disk |
| **send_email** | Compose and send a new email |
| **reply_email** | Reply to an existing email with proper threading |
| **forward_email** | Forward an email with its attachments to new recipients |
| **save_draft** | Save an email draft to the Drafts folder |

## Resources

| Resource | Description |
|---|---|
| `mailbox://status` | Status of all mailboxes (message counts) |
| `message://{mailbox}/{uid}` | Full message content by mailbox and UID |

## Prompts

| Prompt | Description |
|---|---|
| **summarize_email** | Summarize an email's content |
| **draft_reply** | Draft a reply to an email |
| **categorize_inbox** | Categorize inbox messages |

## Testing

```bash
vendor/bin/phpunit
```

## Static Analysis

```bash
vendor/bin/phpstan analyse src/ --level=9
```
