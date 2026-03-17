---
applyTo: "src/**/*.php"
---

# MCP Server — Regras Universais para PHP

**MCP SDK (PHP) · PHP 8.5+ · PSR-11 Container**

> Relacionado: `php.instructions.md` (estilo PHP), `testing.instructions.md` (testes), skill `php-mcp-server-generator`

---

## 1. Annotations Obrigatórias

Todo `#[McpTool]` DEVE declarar `annotations:` com `ToolAnnotations`:

```php
#[McpTool(
    name: 'tool_name',
    description: 'Descrição clara da ação',
    annotations: new ToolAnnotations(readOnlyHint: true),
)]
```

### 1.1 `readOnlyHint`

| Valor | Quando usar | Exemplos |
|---|---|---|
| `true` | Operações que apenas leem dados | list, search, read, count, get, status |
| `false` | Operações que alteram estado | create, update, copy, save attachments |

### 1.2 `destructiveHint`

| Valor | Quando usar | Exemplos |
|---|---|---|
| `true` | Operações irreversíveis ou de alto impacto | delete, move (remove da origem), purge, send email |
| `false` | Operações reversíveis ou seguras | create, copy, flag (exceto Deleted), update |

Regras:
- `destructiveHint: true` → descrição DEVE conter `"REQUIRES USER CONFIRMATION."`
- `destructiveHint: true` + `readOnlyHint: true` é combinação inválida — NUNCA usar
- Na dúvida, prefira `destructiveHint: true` (segurança primeiro)

### 1.3 Outros Hints

| Hint | Quando usar |
|---|---|
| `idempotentHint: true` | Chamadas repetidas produzem mesmo resultado (set flag, create if not exists) |
| `openWorldHint: true` | Tool interage com sistema externo (API, email, banco) |
| `title` | Usar quando o nome precisa de contexto extra para o UI |

---

## 2. Dependency Injection

### 2.1 Regra: Constructor Injection Obrigatório

Toda classe Tool, Resource e Prompt que depende de serviços externos DEVE receber dependências via construtor:

```php
class MessageTool
{
    public function __construct(
        private readonly ImapConnectionFactory $factory,
    ) {}
}
```

**PROIBIDO**: instanciar dependências dentro do construtor:

```php
// ❌ ERRADO
public function __construct()
{
    $this->factory = new ImapConnectionFactory(ImapConfig::fromEnv());
}

// ✅ CERTO
public function __construct(
    private readonly ImapConnectionFactory $factory,
) {}
```

### 2.2 Wiring no `server.php`

Registrar serviços no container do SDK usando `Container::set()`:

```php
use Mcp\Capability\Registry\Container;

$container = new Container();
$container->set(ImapConnectionFactory::class, new ImapConnectionFactory(ImapConfig::fromEnv()));

$server = Server::builder()
    ->setContainer($container)
    ->setDiscovery(...)
    ->build();
```

O container auto-resolve classes que dependem de serviços registrados — não é necessário registrar cada Tool individualmente.

---

## 3. Connection Lifecycle

Toda interação com serviço externo (IMAP, banco, API) DEVE usar `try/finally`:

```php
$connection = $this->factory->create();

try {
    return $connection->doSomething();
} finally {
    $connection->disconnect();
}
```

- NUNCA deixar conexão aberta em caso de exceção
- NUNCA chamar `disconnect()` dentro do `try` — usar `finally`
- Cada chamada de tool cria e fecha sua própria conexão (stateless)

---

## 4. Error Handling nos Tools

Tools DEVEM capturar exceções de domínio e retornar mensagem amigável ao LLM:

```php
#[McpTool(...)]
public function doAction(int $uid): array
{
    $connection = $this->factory->create();

    try {
        $result = $connection->action($uid);

        return ['success' => true, 'data' => $result];
    } catch (MessageNotFoundException $e) {
        return ['error' => true, 'message' => $e->getMessage()];
    } catch (ImapConnectionException $e) {
        return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
    } finally {
        $connection->disconnect();
    }
}
```

| Regra | Detalhe |
|---|---|
| Catch exceções de domínio | `NotFoundException`, `AuthException`, `ConnectionException` |
| Retornar `['error' => true, 'message' => '...']` | Mensagem legível para o LLM interpretar |
| NUNCA catch `\Throwable` genérico | Deixar erros inesperados propagarem para o SDK |
| `finally` para cleanup | Sempre fechar conexão, mesmo em erro |

---

## 5. Tool vs Resource vs Prompt

### 5.1 Tool (`#[McpTool]`)

Ação invocada explicitamente pelo LLM. Recebe parâmetros, executa lógica, retorna resultado.

| Usar quando | Exemplo |
|---|---|
| Operação com efeito colateral | `delete_message`, `create_mailbox`, `move_message` |
| Query parametrizada | `search_messages(from: "x", since: "2024-01-01")` |
| Ação que requer decisão | `flag_message(uid: 123, flag: "Seen")` |

### 5.2 Resource (`#[McpResource]` / `#[McpResourceTemplate]`)

Dado exposto como contexto passivo, acessível por URI. O LLM lê sem "invocar" uma ação.

| Usar quando | Exemplo |
|---|---|
| Dado estático/snapshot | `mailbox://status` — status de todas as caixas |
| Template parametrizado | `message://{mailbox}/{uid}` — conteúdo de uma mensagem |
| Configuração exposta | `config://app/settings` |

### 5.3 Prompt (`#[McpPrompt]`)

Template de interação humano-LLM com placeholders. Gera mensagens pre-formatadas.

| Usar quando | Exemplo |
|---|---|
| Template de análise | `summarize_email(content, language)` |
| Draft assistido | `draft_reply(content, tone, language)` |
| Categorização | `categorize_inbox(email_list)` |

### 5.4 Critério de Decisão

```
Precisa de ação/mutação?  → Tool
Expõe dado estático/URI?  → Resource
Gera template de chat?    → Prompt
```

---

## 6. Naming

| Elemento | Convenção | Exemplo |
|---|---|---|
| Tool name | `snake_case`, verbo + objeto | `list_messages`, `delete_mailbox` |
| Resource URI | `scheme://path` descritivo | `mailbox://status`, `message://INBOX/123` |
| Prompt name | `snake_case`, ação descritiva | `summarize_email`, `draft_reply` |
| Classe PHP | PascalCase, sufixo por tipo | `MessageTool`, `MailboxStatusResource`, `EmailPrompt` |
| Parâmetros | `snake_case` nos attributes MCP | `from_mailbox`, `save_path` |

- Sem prefixo de domínio no nome das tools — o server já é o contexto
- Nomes curtos e descritivos — o LLM precisa entender a ação sem ambiguidade

---

## 7. `#[Schema]` Attributes

| Regra | Detalhe |
|---|---|
| `description` obrigatório | Em parâmetros cujo propósito não é óbvio pelo nome |
| `pattern` para enums string | Regex de validação: `'^(text\|html\|both)$'` |
| `format` para tipos comuns | `format: 'email'`, `format: 'date-time'` |
| Não duplicar tipo | Schema NÃO deve repetir o type hint PHP |

```php
public function readMessage(
    #[Schema(description: 'Message UID')]
    int $uid,
    string $mailbox = 'INBOX',
    #[Schema(description: 'Body format', pattern: '^(text|html|both)$')]
    string $format = 'text',
): array
```

---

## 8. `.env.example`

Todo projeto MCP DEVE ter `.env.example` na raiz com todas as variáveis de ambiente documentadas:

```env
# Required
IMAP_HOST=imap.example.com
IMAP_USER=user@example.com
IMAP_PASSWORD=

# Optional (defaults shown)
IMAP_PORT=993
IMAP_ENCRYPTION=ssl
IMAP_VALIDATE_CERT=true
```

- Valores de exemplo em variáveis obrigatórias (exceto passwords/secrets)
- Valores default em variáveis opcionais
- `.env` no `.gitignore` — NUNCA versionado
- `.env.example` versionado — sempre atualizado
