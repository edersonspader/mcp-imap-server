---
applyTo: "composer.json"
---

# Composer — Estrutura do `composer.json` e Comandos CLI

> Relacionado: `php.instructions.md` (PHP ^8.5, PSR-12)

## 1. Regras Gerais

| Regra | Detalhe |
|---|---|
| PHP | `"php": "^8.5"` — caret obrigatório |
| Tipo | `"type": "project"` para aplicações |
| Estabilidade | `"minimum-stability": "stable"` — sempre |
| `prefer-stable` | `true` — sempre |
| Constraints | Exclusivamente caret `^` — NUNCA `~`, `>=`, `*`, `dev-*` |
| Autoload | PSR-4 único — `"App\\": "src/"` |
| Autoload-dev | Obrigatório — `"App\\Tests\\": "tests/"` |
| Config | `"sort-packages": true` obrigatório |
| Scripts mínimos | `test` e `analyse` obrigatórios |
| Lock file | `composer.lock` commitado no repositório |

## 2. Campos Obrigatórios — Top-level

Todo `composer.json` de projeto DEVE conter estes 12 campos:

```
name, description, type, license, minimum-stability, prefer-stable,
require, require-dev, autoload, autoload-dev, config, scripts
```

### 2.1 Identidade

| Campo | Regra | Exemplo |
|---|---|---|
| `name` | Formato `vendor/kebab-case` | `"acme/inventory-api"` |
| `description` | Frase curta descrevendo o projeto | `"Inventory management API"` |
| `type` | `"project"` para aplicações | `"project"` |
| `license` | SPDX identifier válido ou `"proprietary"` | `"proprietary"`, `"MIT"` |

### 2.2 Estabilidade

| Campo | Valor |
|---|---|
| `minimum-stability` | `"stable"` — obrigatório, sem exceções |
| `prefer-stable` | `true` — obrigatório |

### 2.3 Dependências — `require` e `require-dev`

| Campo | Regra |
|---|---|
| `require` | Dependências de runtime — PHP + pacotes necessários em produção |
| `require-dev` | Ferramentas de desenvolvimento — DEVE conter no mínimo PHPUnit e PHPStan |

Regras de constraint:

- **Apenas caret `^`** — ex: `"^4.7"`, `"^2.0"`
- **Exceção**: extensões PHP (`ext-*`) usam `"*"` — ex: `"ext-pcntl": "*"`, `"ext-pdo": "*"`
- NUNCA usar `~`, `>=`, `>`, `dev-*`, `@dev`, ranges com `\|\|`
- PHP: `"php": "^8.5"`
- Cada pacote em uma linha, ordenado alfabeticamente (`sort-packages`)

### 2.4 Autoload

| Campo | Padrão | Mapeamento |
|---|---|---|
| `autoload` | PSR-4 | `"App\\": "src/"` — namespace raiz único |
| `autoload-dev` | PSR-4 | `"App\\Tests\\": "tests/"` — obrigatório |

- NUNCA usar PSR-0, classmap ou files no autoload principal
- O namespace raiz (`App\\`) mapeia para `src/` — bounded contexts ficam como subnamespaces: `App\Catalog\`, `App\Shared\`

### 2.5 Config

```json
"config": {
    "sort-packages": true
}
```

- `sort-packages: true` — obrigatório — ordena pacotes alfabeticamente no `require` e `require-dev`

### 2.6 Scripts

| Script | Comando | Obrigatório |
|---|---|---|
| `test` | `"phpunit"` | ✅ |
| `analyse` | `"phpstan analyse src/ --level=9"` | ✅ |

- Scripts adicionais são livres por projeto (ex: `cs-fix`, `ci`, `migrate`)
- Referenciar binários sem `vendor/bin/` — Composer resolve automaticamente

## 3. Lock File

| Regra | Detalhe |
|---|---|
| `composer.lock` commitado | Obrigatório para projetos `type: project` |
| NUNCA no `.gitignore` | O lock garante builds reproduzíveis |
| Atualização | Apenas via `composer update` — nunca editar manualmente |

## 4. Comandos CLI — Boas Práticas

### 4.1 `composer install`

```bash
composer install
```

- Instala dependências a partir do `composer.lock`
- Usar em CI, Docker, e ao clonar o repositório
- Se `composer.lock` não existe, comporta-se como `update` e gera o lock

### 4.2 `composer update`

```bash
# Atualizar TUDO (cuidado)
composer update

# Atualizar pacote específico (preferível)
composer update vendor/package

# Atualizar apenas dependências de dev
composer update --dev-only
```

- Sempre revisar mudanças no `composer.lock` antes de commitar
- Preferir atualização granular (pacote a pacote) em vez de `update` geral

### 4.3 `composer require`

```bash
# Dependência de runtime
composer require vendor/package

# Dependência de dev
composer require --dev vendor/package
```

- Sempre usar `--dev` para ferramentas de teste, análise estática e debug
- Composer escolhe a melhor versão compatível e adiciona com `^`

### 4.4 `composer remove`

```bash
# Remover dependência de runtime
composer remove vendor/package

# Remover dependência de dev
composer remove --dev vendor/package
```

- Após remoção, verificar se não há imports órfãos no código

### 4.5 `composer dump-autoload`

```bash
composer dump-autoload
```

- Regenera o autoloader sem atualizar dependências
- Necessário após adicionar/mover classes manualmente fora do fluxo `require`/`update`

### 4.6 `composer validate`

```bash
composer validate --strict
```

- Valida `composer.json` e `composer.lock`
- `--strict` trata warnings como erros
- Executar antes de commitar alterações no `composer.json`

## 5. Template Completo

```json
{
    "name": "vendor/project-name",
    "description": "Descrição curta do projeto",
    "type": "project",
    "license": "proprietary",
    "minimum-stability": "stable",
    "prefer-stable": true,
    "require": {
        "php": "^8.5"
    },
    "require-dev": {
        "phpstan/phpstan": "^2.0",
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "App\\Tests\\": "tests/"
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

## 6. Checklist de Revisão

### Campos

- [ ] `name` no formato `vendor/kebab-case`
- [ ] `description` preenchido
- [ ] `type` é `"project"`
- [ ] `license` com SPDX válido ou `"proprietary"`
- [ ] `minimum-stability` é `"stable"`
- [ ] `prefer-stable` é `true`

### Dependências

- [ ] `"php": "^8.5"` presente no `require`
- [ ] Todas as constraints usam caret `^`
- [ ] `phpunit/phpunit` e `phpstan/phpstan` no `require-dev`
- [ ] Nenhum pacote de dev no `require` (e vice-versa)

### Autoload

- [ ] PSR-4: `"App\\": "src/"` no `autoload`
- [ ] PSR-4: `"App\\Tests\\": "tests/"` no `autoload-dev`
- [ ] Nenhum PSR-0, classmap ou files

### Config e Scripts

- [ ] `sort-packages: true` no `config`
- [ ] Script `test` definido
- [ ] Script `analyse` definido

### Lock File

- [ ] `composer.lock` commitado no repositório
- [ ] `composer.lock` NÃO está no `.gitignore`

### Proibido

- [ ] Nenhum constraint com `~`, `>=`, `*`, `dev-*`, `@dev`
- [ ] Nenhum `repositories` apontando para forks sem justificativa
- [ ] Nenhum `minimum-stability` diferente de `"stable"`
