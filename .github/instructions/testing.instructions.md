---
applyTo: "tests/**/*.php"
---

# Testing — Regras Universais de Escrita de Testes

**PHPUnit 13 · PHP 8.5+ · #[Test] Attributes**

> Relacionado: `phpunit.instructions.md` (configuração XML), `php.instructions.md` (estilo PHP)

---

## 1. Regras Universais

| Regra | Detalhe |
|---|---|
| `final class` | Toda test class é `final` — sem herança de teste |
| `extends TestCase` | `PHPUnit\Framework\TestCase` — sem base class customizada |
| `#[Test]` attribute | Obrigatório — NUNCA prefixo `test` no nome do método |
| `#[CoversClass(Foo::class)]` | Obrigatório em toda test class — coverage preciso |
| Naming: `it_{comportamento}` | snake_case descritivo: `it_creates_from_valid_string()` |
| Retorno `void` | Todo método de teste retorna `: void` |
| AAA obrigatório | Arrange → Act → Assert separados por linha em branco |
| Max ~15 linhas por teste | Extrair helper privado se ultrapassar |
| 1 teste = 1 classe | `ProductNameTest` testa apenas `ProductName` |
| Mirror de diretórios | `src/Catalog/Domain/ValueObject/Sku.php` → `tests/Unit/Catalog/Domain/ValueObject/SkuTest.php` |
| `self::` para assertions | `self::assertSame()` — NUNCA `$this->assertSame()` |
| `$this->` para framework | `$this->createMock()`, `$this->createStub()`, `$this->expectException()` |
| Sem docblocks redundantes | `#[Test]` já declara — sem `/** @test */` ou `@covers` |

---

## 2. Estrutura da Test Class

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\ProductName;
use App\Shared\Domain\Exception\InvalidValueObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductName::class)]
final class ProductNameTest extends TestCase
{
    #[Test]
    public function it_creates_from_valid_string(): void
    {
        $name = ProductName::fromString('Widget');

        self::assertSame('Widget', $name->value);
    }

    #[Test]
    public function it_throws_on_empty_name(): void
    {
        $this->expectException(InvalidValueObject::class);

        ProductName::fromString('');
    }
}
```

### Ordem dos imports

1. Classe testada (SUT)
2. Dependências de domínio (exceptions, VOs, entities)
3. PHPUnit attributes (`CoversClass`, `DataProvider`, `Test`)
4. `PHPUnit\Framework\TestCase`

---

## 3. Naming de Métodos

| Padrão | Exemplo | Quando usar |
|---|---|---|
| `it_creates_{contexto}` | `it_creates_from_valid_string()` | Factory / construtor |
| `it_throws_{motivo}` | `it_throws_on_empty_name()` | Validação de guarda |
| `it_returns_{o_que}` | `it_returns_event_type()` | Getter / derivação |
| `it_{verbo}_{objeto}` | `it_activates_product()` | Mutação de estado |
| `it_{verbo}_when_{condição}` | `it_fails_when_sku_is_duplicate()` | Cenário condicional |
| `it_records_{evento}` | `it_records_product_created_event()` | Emissão de evento |
| `it_is_{adjetivo}` | `it_is_equal_to_same_value()` | Comparação / igualdade |
| `it_does_not_{verbo}` | `it_does_not_record_event_on_same_name()` | Idempotência / negação |

### Regras de naming

- Inglês sempre — mesmo que o projeto use português em outros lugares
- snake_case — NUNCA camelCase
- Sem prefixo `test_` — o `#[Test]` attribute substitui
- Descrever **comportamento**, não implementação: `it_throws_on_negative_amount` ✓ / `it_validates_constructor_parameter` ✗

---

## 4. Padrão AAA (Arrange-Act-Assert)

```php
#[Test]
public function it_renames_product(): void
{
    // Arrange
    $product = $this->createProduct();
    $newName = ProductName::fromString('New Widget');

    // Act
    $product->rename($newName);

    // Assert
    self::assertSame('New Widget', $product->name()->value);
}
```

| Regra | Detalhe |
|---|---|
| Linha em branco entre seções | Separar visualmente Arrange / Act / Assert |
| Comentários `// Arrange` etc. | Opcionais — usar apenas se o teste for longo |
| 1 Act por teste | Executar a ação testada uma única vez |
| Assert no final | Assertions sempre depois do Act — nunca intercalados |
| Múltiplos asserts permitidos | Desde que verifiquem o **mesmo comportamento** |

### Teste curto (AAA compacto)

Quando Arrange é 1 linha e o teste é trivial, a separação visual ainda se aplica:

```php
#[Test]
public function it_returns_value(): void
{
    $name = ProductName::fromString('Widget');

    self::assertSame('Widget', $name->value);
}
```

---

## 5. Assertions

### Preferidas

| Assertion | Quando usar |
|---|---|
| `self::assertSame($expected, $actual)` | Comparação estrita (tipo + valor) — **default** |
| `self::assertTrue($value)` | Resultado booleano (`equals()`, `isSatisfiedBy()`) |
| `self::assertFalse($value)` | Negação booleana |
| `self::assertNull($value)` | Verificar ausência |
| `self::assertInstanceOf(Foo::class, $obj)` | Verificar tipo / interface |
| `self::assertCount($n, $collection)` | Tamanho de array / coleção |
| `self::assertMatchesRegularExpression($pattern, $str)` | Formato (SKU, etc.) |
| `self::assertArrayHasKey($key, $array)` | Chave existe em payload |

### Evitar

| Evitar | Usar em vez |
|---|---|
| `assertEquals()` | `assertSame()` — comparação estrita |
| `assertNotNull()` + cast | `assertInstanceOf()` — verifica tipo diretamente |
| `assertStringContainsString()` para mensagens de erro | `$this->expectException()` + confiar na exception class |
| `assertIsArray()` sem checar conteúdo | `assertCount()` + `assertSame()` nos elementos |

---

## 6. Exception Testing

```php
#[Test]
public function it_throws_on_empty_name(): void
{
    $this->expectException(InvalidValueObject::class);

    ProductName::fromString('');
}
```

| Regra | Detalhe |
|---|---|
| `$this->expectException()` antes do ato | Sempre — PHPUnit captura a exceção automaticamente |
| 1 exceção por teste | NUNCA testar múltiplas exceções no mesmo método |
| Verificar código HTTP | `$this->expectExceptionCode(404)` quando relevante |
| Verificar mensagem | `$this->expectExceptionMessage('context')` — apenas quando a mensagem contém dados dinâmicos |
| Sem try/catch manual | NUNCA — PHPUnit gerencia a captura |

### Template completo

```php
#[Test]
public function it_throws_on_unknown_product(): void
{
    $this->expectException(ProductNotFoundException::class);
    $this->expectExceptionCode(404);

    ProductNotFoundException::withId('non-existent-id');
}
```

---

## 7. setUp — Configuração do SUT

Incluir **todas** as dependências do construtor do SUT — nunca omitir Specification ou DomainService.

```php
#[CoversClass(CreateProductHandler::class)]
final class CreateProductHandlerTest extends TestCase
{
    private ProductRepository $repository;
    private UniqueSkuSpecification $uniqueSku;
    private EventBus $eventBus;
    private CreateProductHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ProductRepository::class);
        $this->uniqueSku = $this->createStub(UniqueSkuSpecification::class);
        $this->eventBus = $this->createMock(EventBus::class);
        $this->handler = new CreateProductHandler(
            $this->repository,
            $this->uniqueSku,
            $this->eventBus,
        );
    }

    #[Test]
    public function it_creates_product(): void
    {
        $this->repository->method('nextIdentity')->willReturn(ProductId::generate());
        $this->uniqueSku->method('isSatisfiedBy')->willReturn(true);
        $this->repository->expects(self::once())->method('save');
        $this->eventBus->expects(self::once())->method('publish');

        ($this->handler)(new CreateProductCommand(name: 'Widget', sku: 'SKU-001'));
    }

    #[Test]
    public function it_throws_on_duplicate_sku(): void
    {
        $this->uniqueSku->method('isSatisfiedBy')->willReturn(false);

        $this->expectException(DuplicateSkuException::class);

        ($this->handler)(new CreateProductCommand(name: 'Widget', sku: 'SKU-001'));
    }
}
```

| Regra | Detalhe |
|---|---|
| Usar `setUp()` | Configurar SUT e mocks compartilhados |
| Propriedades tipadas | `private ProductRepository $repository` — sem default |
| Sem `tearDown()` | Desnecessário para testes unit — PHPUnit limpa automaticamente |
| Lógica de teste no método | `setUp` prepara, o método testa — nunca assertions em `setUp` |

---

## 8. Mocking e Stubbing

### Distinção: `createStub` vs `createMock`

| Método | Quando usar | Verifica chamadas? |
|---|---|---|
| `$this->createStub(Foo::class)` | Fornecer dados ao SUT (input) | Não |
| `$this->createMock(Foo::class)` | Verificar que o SUT chamou o colaborador | Sim (`expects()`) |

### Política: o que mockar

| Mockar | Não mockar |
|---|---|
| Interfaces de infraestrutura (`Repository`, `EventBus`) | Value Objects |
| Portas de saída (`Gateway`, `Mailer`, `Publisher`) | Entities do próprio aggregate |
| Specifications que acessam banco | Domain Exceptions |
| Geradores de ID (quando injetados) | Enums |

### Template: stub para input

```php
$spec = $this->createStub(UniqueSkuSpecification::class);
$spec->method('isSatisfiedBy')->willReturn(true);
```

### Template: mock para verificar interação

```php
$repository = $this->createMock(ProductRepository::class);
$repository->expects(self::once())
    ->method('save')
    ->with(self::isInstanceOf(Product::class));
```

### Proibido em mocking

| Errado | Motivo |
|---|---|
| Mockar a classe testada (SUT) | Testa a implementação do mock, não do código |
| Mockar Value Objects | São imutáveis e sem side effects — usar instância real |
| `willReturnCallback()` com lógica complexa | Mock recriando lógica de produção — sinal de design ruim |
| `expects(self::exactly(N))` para N > 2 | Teste frágil acoplado a implementação interna |

---

## 9. Data Providers

```php
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Sku::class)]
final class SkuTest extends TestCase
{
    #[Test]
    #[DataProvider('validSkuProvider')]
    public function it_creates_with_valid_sku(string $value): void
    {
        $sku = Sku::fromString($value);

        self::assertSame($value, $sku->value);
    }

    /** @return array<string, array{string}> */
    public static function validSkuProvider(): array
    {
        return [
            'uppercase'       => ['SKU-001'],
            'with numbers'    => ['PROD-12345'],
            'minimum length'  => ['A-1'],
        ];
    }
}
```

| Regra | Detalhe |
|---|---|
| `#[DataProvider('methodName')]` | Attribute — NUNCA `@dataProvider` docblock |
| Método `public static` | Retorna `array<string, array{...}>` |
| Chaves descritivas | `'uppercase'`, `'empty string'` — aparece no output de falha |
| PHPStan `@return` | Docblock com tipo do array para análise estática |
| Quando usar | 3+ cenários com mesma lógica, variando apenas input/output |
| Quando NÃO usar | Cenários com comportamento distinto — métodos separados mais legíveis |

---

## 10. Helpers Privados

```php
private function createProduct(
    string $name = 'Widget',
    string $sku = 'SKU-001',
): Product {
    return Product::create(
        id: ProductId::generate(),
        name: ProductName::fromString($name),
        sku: Sku::fromString($sku),
    );
}
```

| Regra | Detalhe |
|---|---|
| `private` | Nunca `protected` — sem herança de teste |
| Parâmetros com default | Permite override por cenário: `$this->createProduct(name: 'Other')` |
| Sem assertions dentro | Helper monta dados — NUNCA verifica |
| Named arguments | Para legibilidade quando 3+ parâmetros |
| Prefixo `create` | `createProduct()`, `createEvent()`, `createCommand()` |

---

## 11. Estrutura de Diretórios

```
src/Catalog/Domain/ValueObject/Sku.php
→ tests/Unit/Catalog/Domain/ValueObject/SkuTest.php

src/Catalog/Application/Command/CreateProduct/CreateProductHandler.php
→ tests/Unit/Catalog/Application/Command/CreateProduct/CreateProductHandlerTest.php
```

| Regra | Detalhe |
|---|---|
| Mirror exato | Diretório de teste espelha `src/` sob `tests/Unit/` |
| Sufixo `Test` | `{ClassName}Test.php` — sempre |
| Namespace `App\Tests\Unit\` | Prefixo de teste seguido pelo namespace original sem `App\` |
| 1 arquivo = 1 classe | `SkuTest.php` testa apenas `Sku` |

---

## 12. Proibido

| Errado | Correto | Motivo |
|---|---|---|
| `public function testCreates()` | `#[Test] public function it_creates()` | Naming inconsistente, sem attribute |
| `$this->assertSame()` | `self::assertSame()` | Chamada estática — convenção do projeto |
| `assertEquals()` | `assertSame()` | Comparação fraca ignora tipos |
| `/** @test */` | `#[Test]` | Docblock legado — usar attributes PHP 8.5+ |
| `/** @covers */` | `#[CoversClass]` | Docblock legado — usar attributes PHP 8.5+ |
| `/** @dataProvider */` | `#[DataProvider]` | Docblock legado — usar attributes PHP 8.5+ |
| `extends AbstractTestCase` | `extends TestCase` | Sem herança customizada de teste |
| `try { ... } catch { self::fail() }` | `$this->expectException()` | PHPUnit gerencia captura |
| Mock de Value Object | Instanciar VO real | VOs são imutáveis, sem side effects |
| Assertion no `setUp()` | Assertions apenas em `#[Test]` | `setUp` prepara, não verifica |
| `@depends` entre testes | Testes independentes | Acoplamento entre testes impede execução isolada |
| `echo` / `var_dump` em teste | Remover — `beStrictAboutOutputDuringTests` falha | Output em teste é erro |
| Dados aleatórios (`rand()`, `Faker`) em unit test | Dados fixos e determinísticos | Testes devem ser reproduzíveis |

---

## 13. Templates por Building Block

### Handler de mutação (com not-found)

```php
#[CoversClass(RenameProductHandler::class)]
final class RenameProductHandlerTest extends TestCase
{
    private ProductRepository $repository;
    private EventBus $eventBus;
    private RenameProductHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ProductRepository::class);
        $this->eventBus = $this->createMock(EventBus::class);
        $this->handler = new RenameProductHandler($this->repository, $this->eventBus);
    }

    #[Test]
    public function it_renames_product(): void
    {
        $product = $this->createProduct();
        $this->repository->method('findById')->willReturn($product);
        $this->repository->expects(self::once())->method('save');
        $this->eventBus->expects(self::once())->method('publish');

        ($this->handler)(new RenameProductCommand(
            productId: $product->aggregateId(),
            newName: 'New Name',
        ));
    }

    #[Test]
    public function it_throws_when_product_not_found(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $this->expectException(ProductNotFoundException::class);

        ($this->handler)(new RenameProductCommand(
            productId: 'non-existent-id',
            newName: 'New Name',
        ));
    }

    private function createProduct(): Product
    {
        return Product::create(
            id: ProductId::generate(),
            name: ProductName::fromString('Widget'),
            sku: Sku::fromString('SKU-001'),
        );
    }
}
```

### EventSubscriber

```php
#[CoversClass(ProductCreatedSubscriber::class)]
final class ProductCreatedSubscriberTest extends TestCase
{
    #[Test]
    public function it_handles_product_created_event(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('delete')->with('product_list');

        $subscriber = new ProductCreatedSubscriber($cache);

        $subscriber->handle(new ProductCreated(
            productId: 'product-id',
            name: 'Widget',
            sku: 'SKU-001',
            status: 'draft',
            occurredOn: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function it_subscribes_to_product_created(): void
    {
        self::assertSame(
            [ProductCreated::class],
            ProductCreatedSubscriber::subscribedTo(),
        );
    }
}
```
