---
applyTo: "**/*.php"
---

# PHP

**PHP 8.5 · PSR-12 · PHPStan 2 (nível 9) · PHPUnit 13**

## Arquivo

- `<?php` → linha em branco → `declare(strict_types=1);` → linha em branco → `namespace`
- `use` em ordem alfabética estrita, sem agrupamento
- Classes nativas do PHP nunca importadas — usar `\` prefixo: `\PDO`, `\DateTimeImmutable`

## Classes

- Constructor property promotion sempre
- Propriedade pública somente `readonly`; caso contrário `private` ou `protected`
- Toda propriedade tipada — sem exceção
- `readonly class` quando todas as propriedades são readonly — ex: VOs, Events, Commands, DTOs. Aggregates/Entities com estado mutável **não** usam `readonly class` — usam `readonly` apenas nas propriedades imutáveis (`$id`, `$createdAt`)

## Tipos

- Type hints obrigatórios em parâmetros, retornos e propriedades
- `Type|null` para nullable — NUNCA `?Type`
- `never` quando sempre lança exceção; `void` quando sem retorno
- `array<K, V>` ou `list<T>` — NUNCA `array` sem tipo
- Arrow functions (`fn()`) também exigem return type — ERRADO: `fn (array $row)` · CERTO: `fn (array $row): array`
- Comparações estritas: `===`, `!==`

## Métodos

- Guard clauses primeiro — return/throw antes da lógica principal
- Máx. ~20 linhas — extrair se ultrapassar
- Aspas simples; duplas somente para interpolação
- `fn()` para closures de uma expressão; `function` para múltiplas instruções — return type obrigatório
- `match` ao invés de `switch`
- Named arguments em construtores com 3+ parâmetros
- First-class callable: `$this->method(...)` ao invés de `Closure::fromCallable`

## Espaçamento

- Linha em branco antes de `if`, `foreach`, `while`, `for`, `match`, `return`, `throw` — exceto quando é primeira instrução do bloco
- NUNCA alinhar `=`, `=>`, `:` em colunas visuais

## SQL

- SQL curto: inline no `prepare()`
- SQL longo: `$sql = <<<SQL ... SQL;` (HEREDOC), linha em branco antes do `prepare()`
- Nomes de tabelas e colunas sempre entre backticks: `` `tabela` ``, `` `coluna` `` — evita conflitos com palavras reservadas do MySQL

## Docblocks

- Somente para: `@throws`, `@template`, `@var` (PHPStan), algoritmos não óbvios
- NUNCA duplicar tipo já na assinatura

## Arrays

- `[]` literal — `array()` proibido
- Trailing comma obrigatória em multilinhas

## Enums

- Enums tipados (`string`/`int` backed) ao invés de constantes string/int
- Um enum por arquivo, mesmo namespace da classe que consome

## Proibido

- Operador `@` de supressão → tratar explicitamente
- Herança de classe concreta → composição + interface
