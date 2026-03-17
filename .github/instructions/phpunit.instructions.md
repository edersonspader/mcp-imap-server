---
applyTo: "phpunit.xml,phpunit.xml.dist"
---

# PHPUnit — Configuração XML

**PHPUnit 13 · PHP 8.5+**

> Relacionado: `php.instructions.md`

---

## 1. Regras Universais

| Regra | Detalhe |
|---|---|
| XSD obrigatório | `xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"` — valida estrutura |
| `bootstrap` | `vendor/autoload.php` — ou bootstrap customizado se existir |
| `colors="true"` | Saída colorida no terminal |
| `failOnRisky="true"` | Testes sem assertion falham |
| `failOnWarning="true"` | Deprecations e warnings falham |
| Strict modes ligados | `beStrictAboutTestsThatDoNotTestAnything`, `beStrictAboutOutputDuringTests`, `beStrictAboutCoverageMetadata` — todos `true` |
| `cacheDirectory` | Caminho relativo (ex: `var/cache/.phpunit.cache`) — NUNCA absoluto |
| Cache no `.gitignore` | Diretório de cache sempre ignorado no versionamento |
| Paths relativos | Todos os caminhos relativos à raiz do projeto — NUNCA absolutos |
| 1 suite = 1 tipo | Separar `Unit`, `Integration`, `Functional` em suites distintas |
| Sem credenciais no XML | Variáveis sensíveis via `.env.test` ou CI — NUNCA hardcoded em `<env>` |

---

## 2. Template Completo

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
    <!-- Testsuites: 1 por tipo de teste -->
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <!-- Adicionar conforme necessário:
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
        <testsuite name="Functional">
            <directory>tests/Functional</directory>
        </testsuite>
        -->
    </testsuites>

    <!-- Source: código-fonte para coverage -->
    <source>
        <include>
            <directory>src</directory>
        </include>
        <exclude>
            <directory>src/**/Infrastructure/Http</directory>
            <directory>src/**/Infrastructure/Persistence/Migration</directory>
            <file>config/container.php</file>
        </exclude>
    </source>

    <!-- Coverage: relatórios -->
    <coverage>
        <report>
            <clover outputFile="var/cache/coverage/clover.xml"/>
            <html outputDirectory="var/cache/coverage/html"/>
        </report>
    </coverage>

    <!-- Variáveis de ambiente e INI -->
    <php>
        <env name="APP_ENV" value="test"/>
        <ini name="memory_limit" value="256M"/>
        <ini name="error_reporting" value="-1"/>
    </php>
</phpunit>
```

---

## 3. Atributos `<phpunit>`

| Atributo | Valor | Motivo |
|---|---|---|
| `bootstrap` | `vendor/autoload.php` | Autoload PSR-4 via Composer — substituir se usar bootstrap customizado |
| `colors` | `true` | Diferencia passes/fails visualmente |
| `cacheDirectory` | `var/cache/.phpunit.cache` | Result cache para `--order-by=defects` — acelera feedback loop |
| `failOnRisky` | `true` | Testes sem assertion são erro, não silêncio |
| `failOnWarning` | `true` | Força resolução de deprecations antes de upgrade |
| `beStrictAboutTestsThatDoNotTestAnything` | `true` | Marca teste sem assertion como risky |
| `beStrictAboutOutputDuringTests` | `true` | Proíbe `echo`/`var_dump` acidentais em testes |
| `beStrictAboutCoverageMetadata` | `true` | Exige `#[CoversClass]` ou `#[CoversNothing]` para coverage preciso |
| `executionOrder` | `random` | _(Opcional)_ Detecta dependências ocultas entre testes |
| `displayDetailsOnTestsThatTriggerDeprecations` | `true` | _(Opcional)_ Mostra detalhes de deprecations para facilitar migração |

> Atributos não listados: usar apenas se houver justificativa documentada no projeto.

---

## 4. `<testsuites>`

| Regra | Detalhe |
|---|---|
| 1 suite por tipo | `Unit`, `Integration`, `Functional` — cada qual com diretório próprio |
| Nome da suite | PascalCase, singular: `Unit`, `Integration`, `Functional` |
| Path relativo | `<directory>tests/Unit</directory>` — sem `./` prefixo |
| Filtrar via CLI | `vendor/bin/phpunit --testsuite Unit` — executa apenas a suite selecionada |

### Estrutura de diretórios

```
tests/
├── Unit/              ← sem I/O, sem banco, sem rede
├── Integration/       ← banco, filesystem, cache
└── Functional/        ← HTTP, end-to-end
```

### Template multi-suite

```xml
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Integration">
        <directory>tests/Integration</directory>
    </testsuite>
    <testsuite name="Functional">
        <directory>tests/Functional</directory>
    </testsuite>
</testsuites>
```

---

## 5. `<source>` e Coverage

### 5.1 `<include>`

```xml
<source>
    <include>
        <directory>src</directory>
    </include>
</source>
```

Incluir o diretório raiz do código-fonte — todo o código produtivo.

### 5.2 `<exclude>`

| O que excluir | Exemplo |
|---|---|
| Controllers / Actions HTTP | `src/**/Infrastructure/Http` |
| Migrations | `src/**/Infrastructure/Persistence/Migration` |
| Config / Bootstrap | `<file>config/container.php</file>` |
| Stubs / Fixtures de teste | `src/**/Stub` |

```xml
<source>
    <include>
        <directory>src</directory>
    </include>
    <exclude>
        <directory>src/**/Infrastructure/Http</directory>
        <directory>src/**/Infrastructure/Persistence/Migration</directory>
    </exclude>
</source>
```

### 5.3 `<coverage>` e `<report>`

```xml
<coverage>
    <report>
        <clover outputFile="var/cache/coverage/clover.xml"/>
        <html outputDirectory="var/cache/coverage/html"/>
    </report>
</coverage>
```

| Formato | Uso |
|---|---|
| `clover` | CI (upload para Codecov, SonarQube, etc.) |
| `html` | Navegação local — abrir `index.html` no browser |

### 5.4 Thresholds de cobertura

PHPUnit não suporta thresholds no XML. Usar via CLI:

```bash
vendor/bin/phpunit --coverage-clover var/cache/coverage/clover.xml \
  --min=80
```

`--min=80` falha se cobertura de linhas < 80%. Ajustar conforme maturidade do projeto.

---

## 6. `<php>` — Variáveis de Ambiente e INI

### 6.1 `<env>`

```xml
<php>
    <env name="APP_ENV" value="test"/>
    <env name="DATABASE_URL" value="sqlite:///:memory:"/>
    <env name="CACHE_DRIVER" value="array"/>
</php>
```

| Regra | Detalhe |
|---|---|
| Valores de teste apenas | URLs in-memory, drivers fake — NUNCA produção |
| Sem segredos | Tokens, senhas, API keys → CI secrets ou `.env.test` local |
| `force="true"` para override | `<env name="APP_ENV" value="test" force="true"/>` — sobrescreve variável do sistema |

### 6.2 `<ini>`

```xml
<php>
    <ini name="memory_limit" value="256M"/>
    <ini name="error_reporting" value="-1"/>
    <ini name="display_errors" value="On"/>
</php>
```

| Setting | Valor | Motivo |
|---|---|---|
| `memory_limit` | `256M` | Headroom para suites grandes sem OOM |
| `error_reporting` | `-1` | Reportar todos os erros/notices/deprecations |
| `display_errors` | `On` | Erros visíveis durante testes |

---

## 7. Grupos

### 7.1 No código PHP

```php
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
final class HeavyIntegrationTest extends TestCase { }
```

### 7.2 No XML (filtrar por padrão)

```xml
<groups>
    <exclude>
        <group>slow</group>
    </exclude>
</groups>
```

### 7.3 Via CLI

```bash
# Executar apenas um grupo
vendor/bin/phpunit --group slow

# Excluir um grupo
vendor/bin/phpunit --exclude-group slow
```

| Grupo comum | Uso |
|---|---|
| `slow` | Testes > 1s — excluir do CI rápido, rodar em pipeline dedicado |
| `external` | Depende de API/serviço externo — excluir em ambiente offline |
| `database` | Precisa de banco real — separar de unit puro |

---

## 8. Cache

| Regra | Detalhe |
|---|---|
| `cacheDirectory` obrigatório | Habilita result cache — melhora `--order-by=defects` |
| Path relativo | `var/cache/.phpunit.cache` — convenção com outros caches do projeto |
| `.gitignore` | Adicionar `var/cache/.phpunit.cache/` ao `.gitignore` |
| Invalidação | Cache invalidado automaticamente quando o código-fonte muda |

### `.gitignore`

```gitignore
# PHPUnit
var/cache/.phpunit.cache/
var/cache/coverage/
```

### `--order-by=defects`

```bash
vendor/bin/phpunit --order-by=defects
```

Executa primeiro os testes que falharam na última execução — feedback mais rápido.

---

## 9. Proibido

| Errado | Correto | Motivo |
|---|---|---|
| `stopOnFailure="true"` no XML | `vendor/bin/phpunit --stop-on-failure` via CLI | Config permanente bloqueia feedback completo da suite |
| Paths absolutos: `/home/user/project/tests` | `tests/Unit` (relativo) | Quebra em qualquer outra máquina |
| `verbose="true"` | Removido no PHPUnit 11 | Atributo não existe mais — causa warning |
| `<logging>` para coverage | `<coverage><report>` | `<logging>` é formato legado removido no PHPUnit 10+ |
| `processIsolation="true"` global | Usar `#[RunInSeparateProcess]` por teste | Isolamento global multiplica tempo de execução |
| `backupGlobals="true"` | Remover — legado | Desnecessário com strict types e DI |
| `backupStaticProperties="true"` | Remover — legado | Mesmo motivo |
| Credenciais em `<env>` | `.env.test` ou CI secrets | XML é versionado — segredos expostos no repositório |
| `<directory suffix="Test.php">` com suffix custom | Omitir `suffix` (default já é `Test.php`) | Redundante — PHPUnit já filtra por `*Test.php` |
| `<testsuites>` vazio | Pelo menos 1 suite | PHPUnit ignora silenciosamente — testes não executam |
