---
applyTo: "phpstan.neon,phpstan.neon.dist"
---

# PHPStan — Configuração NEON

**PHPStan 2.x · PHP 8.5+ · Nível 9**

> Relacionado: `php.instructions.md` (regras de linguagem), `phpunit.instructions.md` (configuração XML), `testing.instructions.md` (escrita de testes)

---

## 1. Regras Universais

| Regra | Detalhe |
|---|---|
| Nível 9 para novos projetos | Máximo rigor — zero `mixed`, tudo tipado |
| `paths` obrigatório | Lista explícita de diretórios a analisar |
| `tmpDir` relativo | Ex: `var/cache/.phpstan.cache` — NUNCA absoluto |
| Cache no `.gitignore` | Diretório de cache sempre ignorado no versionamento |
| Extensions via `includes` | Cada extension adicionada com `includes` — NUNCA regras manuais substituindo extensions |
| `phpVersion` explícito | Travar na versão mínima de produção — evita falsos negativos |
| `excludePaths` controlado | Excluir apenas o estritamente necessário (migrations, stubs) |
| 1 arquivo NEON na raiz | `phpstan.neon` ou `phpstan.neon.dist` — NUNCA ambos simultaneamente |
| `phpstan.neon.dist` versionado | Para times: `.dist` no repositório, `phpstan.neon` local no `.gitignore` |
| Sem erros ignorados sem justificativa | Cada `ignoreErrors` deve ter motivo documentável |

---

## 2. Template Mínimo

Template base — funciona sem extensões adicionais:

```neon
parameters:
    level: 9
    paths:
        - src
    excludePaths:
        - src/**/Infrastructure/Persistence/Migration
    tmpDir: var/cache/.phpstan.cache
    phpVersion: 80500  # PHP 8.5 — ajustar para a versão mínima do projeto
```

### 2.1 Template com Extensions Recomendadas

Quando `phpstan-strict-rules` e `phpstan-deprecation-rules` estiverem instalados (ver seção 4):

```neon
includes:
    - vendor/phpstan/phpstan-strict-rules/rules.neon
    - vendor/phpstan/phpstan-deprecation-rules/rules.neon

parameters:
    level: 9
    paths:
        - src
    excludePaths:
        - src/**/Infrastructure/Persistence/Migration
    tmpDir: var/cache/.phpstan.cache
    phpVersion: 80500  # PHP 8.5 — ajustar para a versão mínima do projeto
```

> Extensions são **recomendadas**, não obrigatórias. O template mínimo (sem `includes`) é válido.

---

## 3. Parâmetros

### 3.1 `level`

| Nível | O que adiciona |
|---|---|
| 0 | Erros básicos, classes/funções desconhecidas |
| 1 | Variáveis possivelmente indefinidas |
| 2 | Métodos/propriedades desconhecidas em `mixed` (quando verificável) |
| 3 | Retornos de funções, verificação de tipos |
| 4 | Dead code, `instanceof` desnecessário |
| 5 | Verificação de argumentos passados a funções |
| 6 | Tipagem de `array<K, V>` — reporta `array` sem tipo |
| 7 | Union types verificados |
| 8 | Reporta chamadas em `mixed` |
| **9** | **Máximo — `mixed` proibido em toda operação** |

> Novos projetos: começar no **9**. Projetos existentes: subir gradualmente (5 → 6 → … → 9).

### 3.2 `paths`

```neon
parameters:
    paths:
        - src
        - lib
```

Lista explícita de diretórios com código-fonte a analisar. Usar caminhos relativos à raiz do projeto.

### 3.3 `excludePaths`

```neon
parameters:
    excludePaths:
        - src/**/Migration
        - src/Kernel.php
```

| O que excluir | Exemplo |
|---|---|
| Migrations | `src/**/Migration` |
| Código gerado | `src/Generated` |
| Stubs / Fixtures | `src/**/Stub` |

> NUNCA excluir código de negócio — apenas gerado, migrations ou fixtures.

### 3.4 `phpVersion`

```neon
parameters:
    phpVersion: 80500  # 8.5.0
```

Formato: `MMPPP` (major × 10000 + minor × 100 + patch). Travar na versão mínima suportada em produção.

| Versão PHP | Valor |
|---|---|
| 8.2 | `80200` |
| 8.3 | `80300` |
| 8.4 | `80400` |
| 8.5 | `80500` |

---

## 4. Extensions Recomendadas

### 4.1 phpstan-strict-rules

Regras adicionais de strictness: comparações estritas obrigatórias, `in_array` strict, etc.

```bash
composer require --dev phpstan/phpstan-strict-rules
```

```neon
includes:
    - vendor/phpstan/phpstan-strict-rules/rules.neon
```

### 4.2 phpstan-deprecation-rules

Detecta uso de código deprecated (funções, classes, métodos, constantes).

```bash
composer require --dev phpstan/phpstan-deprecation-rules
```

```neon
includes:
    - vendor/phpstan/phpstan-deprecation-rules/rules.neon
```

### 4.3 Extensions por framework (opcional)

| Framework | Extension | Include |
|---|---|---|
| PHPUnit | `phpstan/phpstan-phpunit` | `vendor/phpstan/phpstan-phpunit/extension.neon` |
| Doctrine | `phpstan/phpstan-doctrine` | `vendor/phpstan/phpstan-doctrine/extension.neon` |
| Symfony | `phpstan/phpstan-symfony` | `vendor/phpstan/phpstan-symfony/extension.neon` |
| Laravel | `larastan/larastan` | `vendor/larastan/larastan/extension.neon` |

> Instalar via Composer e adicionar ao `includes` — verificar documentação de cada extension para configuração específica.

---

## 5. ignoreErrors

### 5.1 Sintaxe básica

```neon
parameters:
    ignoreErrors:
        # Por mensagem (regex)
        - '#Call to an undefined method App\\Legacy\\.*::oldMethod\(\)#'

        # Por mensagem + path
        -
            message: '#Parameter \$data of method .* expects array<string, mixed>#'
            paths:
                - src/Legacy/Adapter.php

        # Com contagem exata (falha se o erro desaparecer)
        -
            message: '#Undefined variable: \$legacy#'
            count: 2
            path: src/Legacy/Bridge.php

        # Por identifier (PHPStan 2.x)
        -
            identifier: argument.type
            path: src/Legacy/Bridge.php
```

### 5.2 `reportUnmatchedIgnoredErrors`

```neon
parameters:
    reportUnmatchedIgnoredErrors: true  # default — manter true
```

Reporta quando um `ignoreErrors` não corresponde a nenhum erro — indica que o erro foi corrigido e o ignore deve ser removido.

---

## 6. CI

### 6.1 Comandos

```bash
# Análise padrão (usa phpstan.neon da raiz)
vendor/bin/phpstan analyse

# Override de nível via CLI
vendor/bin/phpstan analyse --level=6

# Saída em formato CI (sem progress bar)
vendor/bin/phpstan analyse --no-progress --error-format=table

# Formato para GitHub Actions (annotations inline)
vendor/bin/phpstan analyse --no-progress --error-format=github

# Formato para GitLab CI (code quality report)
vendor/bin/phpstan analyse --no-progress --error-format=json > phpstan-report.json

# Limpar cache antes de análise (troubleshooting)
vendor/bin/phpstan clear-result-cache
```

### 6.2 Composer script

```json
{
    "scripts": {
        "analyse": "phpstan analyse --no-progress"
    }
}
```

```bash
composer analyse
```

---

## 7. Proibido

| Errado | Correto | Motivo |
|---|---|---|
| `level: 0` em projeto novo | `level: 9` | Máximo rigor desde o início — muito mais fácil que subir depois |
| Paths absolutos: `/home/user/project/src` | `src` (relativo) | Quebra em qualquer outra máquina |
| `ignoreErrors` sem regex delimitado | `'#mensagem exata#'` | Regex sem `#` pode casar com erros inesperados |
| `ignoreErrors` em massa para silenciar nível | Reduzir o `level` temporariamente | Ignore em massa esconde bugs reais |
| `tmpDir` absoluto | `var/cache/.phpstan.cache` (relativo) | Portabilidade entre máquinas |
| `phpstan.neon` e `phpstan.neon.dist` juntos | Apenas 1 — `.dist` versionado, `.neon` local | PHPStan prioriza `.neon` sobre `.dist` — pode ignorar regras do time |
| `checkGenericClassInNonGenericObjectType: false` | Manter `true` (default) | Esconde erros de tipagem genérica |
| Misturar include de extension + regras manuais da mesma | Apenas o include | Conflitos e regras duplicadas |
| `reportUnmatchedIgnoredErrors: false` | Manter `true` (default) | Ignores obsoletos ficam acumulando sem detecção |
| Cache versionado no Git | `var/cache/.phpstan.cache/` no `.gitignore` | Cache é máquina-específico — poluição no repositório |
