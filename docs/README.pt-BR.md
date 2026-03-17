# IMAP MCP Server

[🇬🇧 English](../README.md)

Servidor MCP para acesso ao protocolo IMAP. Permite que LLMs leiam, busquem, organizem e-mails e gerenciem mailboxes via protocolo MCP.

## Requisitos

- PHP 8.5 ou superior
- Composer

## Instalação

```bash
composer install
cp .env.example .env
# Edite o .env com suas credenciais IMAP
```

## Configuração

Edite o `.env` com os dados do seu servidor IMAP:

| Variável | Descrição | Padrão |
|---|---|---|
| `IMAP_HOST` | Hostname do servidor IMAP | — |
| `IMAP_PORT` | Porta do servidor IMAP | `993` |
| `IMAP_USER` | Usuário/e-mail IMAP | — |
| `IMAP_PASSWORD` | Senha IMAP | — |
| `IMAP_ENCRYPTION` | Tipo de criptografia (`ssl`, `tls`, ou vazio) | `ssl` |
| `IMAP_VALIDATE_CERT` | Validar certificado SSL | `true` |
| `ATTACHMENT_PATH` | Diretório para salvar anexos | `var/attachments` |
| `MCP_AUTH_TOKEN` | Token Bearer para autenticação no transporte HTTP (vazio = desabilitado) | — |

## Uso

### Iniciar Servidor (Stdio)

```bash
php server.php
```

### Iniciar Servidor (HTTP)

```bash
php -S localhost:8080 server-http.php
```

### Autenticação HTTP

O transporte HTTP suporta autenticação opcional por Bearer token. Defina `MCP_AUTH_TOKEN` no `.env` para habilitá-la:

```env
MCP_AUTH_TOKEN=seu-token-secreto
```

Quando habilitada, todas as requisições devem incluir o header `Authorization`:

```bash
curl -X POST http://localhost:8080 \
  -H "Authorization: Bearer seu-token-secreto" \
  -H "Content-Type: application/json" \
  -d '{...}'
```

Deixe `MCP_AUTH_TOKEN` vazio ou não definido para desabilitar a autenticação.

### Executar como Serviço systemd

Para rodar o servidor HTTP como serviço systemd no nível do usuário (sem root):

Crie `~/.config/systemd/user/imap-mcp-http.service`:

```ini
[Unit]
Description=IMAP MCP Server (Streamable HTTP)
After=network-online.target

[Service]
Type=simple
WorkingDirectory=/caminho/absoluto/para/projeto
EnvironmentFile=/caminho/absoluto/para/projeto/.env

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

Gerenciar o serviço:

```bash
# Recarregar após criar/editar o unit file
systemctl --user daemon-reload

# Habilitar no login e iniciar
systemctl --user enable --now imap-mcp-http

# Verificar status
systemctl --user status imap-mcp-http

# Ver logs
journalctl --user -u imap-mcp-http -f

# Para manter o serviço rodando após logout
loginctl enable-linger $USER
```

### Exemplos com Transporte HTTP

O transporte HTTP expõe o protocolo MCP via Streamable HTTP. Todas as requisições usam `POST /` com `Content-Type: application/json`.

#### Inicializar

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

#### Listar Tools

```bash
curl -X POST http://localhost:8080 \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 2,
    "method": "tools/list"
  }'
```

#### Chamar uma Tool (count_messages)

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

#### Chamar uma Tool (list_messages)

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

#### Chamar uma Tool (search_messages)

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

#### Listar Resources

```bash
curl -X POST http://localhost:8080 \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 6,
    "method": "resources/list"
  }'
```

#### Listar Prompts

```bash
curl -X POST http://localhost:8080 \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 7,
    "method": "prompts/list"
  }'
```

### Configurar no Claude Desktop

#### Stdio

```json
{
  "mcpServers": {
    "imap-server": {
      "command": "php",
      "args": ["/caminho/absoluto/para/server.php"]
    }
  }
}
```

#### Streamable HTTP (via mcp-remote)

O Claude Desktop não suporta Streamable HTTP nativamente. Use `mcp-remote` como ponte:

```json
{
  "mcpServers": {
    "imap-server": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "http://localhost:8080",
        "--header",
        "Authorization: Bearer seu-token-secreto"
      ]
    }
  }
}
```

> Remova os args `--header` e `Authorization` se `MCP_AUTH_TOKEN` não estiver definido.

### Configurar no VS Code

Adicione ao `.vscode/mcp.json`:

#### Stdio

```json
{
  "servers": {
    "imap-server": {
      "command": "php",
      "args": ["/caminho/absoluto/para/server.php"]
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
        "Authorization": "Bearer seu-token-secreto"
      }
    }
  }
}
```

> Remova o bloco `headers` se `MCP_AUTH_TOKEN` não estiver definido.

## Tools

| Tool | Descrição |
|---|---|
| **list_mailboxes** | Listar todas as mailboxes/pastas |
| **count_messages** | Contar mensagens em uma mailbox (total, não lidas, recentes) |
| **create_mailbox** | Criar uma nova mailbox/pasta |
| **delete_mailbox** | Excluir uma mailbox/pasta |
| **list_messages** | Listar mensagens com paginação |
| **search_messages** | Buscar mensagens por critérios (remetente, assunto, data, flags) |
| **read_message** | Ler conteúdo completo da mensagem (cabeçalhos + corpo) |
| **get_message_headers** | Obter apenas os cabeçalhos da mensagem |
| **move_message** | Mover mensagem para outra mailbox |
| **copy_message** | Copiar mensagem para outra mailbox |
| **delete_message** | Excluir uma mensagem |
| **flag_message** | Definir/limpar flags de mensagem (Seen, Flagged, Answered, Draft) |
| **get_attachments** | Salvar anexos da mensagem em disco |

## Resources

| Resource | Descrição |
|---|---|
| `mailbox://status` | Status de todas as mailboxes (contagem de mensagens) |
| `message://{mailbox}/{uid}` | Conteúdo completo da mensagem por mailbox e UID |

## Prompts

| Prompt | Descrição |
|---|---|
| **summarize_email** | Resumir o conteúdo de um e-mail |
| **draft_reply** | Redigir uma resposta a um e-mail |
| **categorize_inbox** | Categorizar mensagens da caixa de entrada |

## Testes

```bash
vendor/bin/phpunit
```

## Análise Estática

```bash
vendor/bin/phpstan analyse src/ --level=9
```
