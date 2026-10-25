# ORM vs DBAL Bookstore Benchmark Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A Symfony console benchmark that runs five bookstore scenarios through three persistence variants (ORM naive, ORM optimized, DBAL) at 100 / 10,000 / 100,000 books and prints median time, SQL query count and peak memory in a terminal table.

**Architecture:** Hexagonal. `src/Domain` holds plain PHP aggregates (Book, Author, Discount, Purchase, Rental) and the best-discount rule; `src/Application` holds the ports, the three write use cases and a factory that wires one variant's adapters behind the ports; `src/Infrastructure` holds the XML ORM mapping of the domain classes, three adapter sets, the seeder and the benchmark. The benchmark runs each cell (scenario × variant × size) in a child process against a per-size database.

**Tech Stack:** PHP 8.4, Symfony 8.1, Doctrine ORM 3.7 / DBAL 4.5 / DoctrineBundle 3.3 / Migrations, PostgreSQL 18 (Docker), PHPUnit 13, symfony/uid, symfony/process. Timing uses `hrtime()`.

**Spec:** `docs/superpowers/specs/2026-10-25-orm-vs-dbal-bookstore-benchmark-design.md`

## Global Constraints

- Follow `AGENTS.md` and `CLAUDE.md`: Flex installs (`composer require`), PHP attributes, autowiring, schema changes only through Doctrine migrations, `@Symfony` code style, constructor promotion, `readonly` for value objects and views.
- No narrative comments or docblocks; only typed `@param`/`@return`/`@var`/`@throws`/`@template` annotations where the signature can't express it.
- No Doctrine attribute, base class or import in `src/Domain` other than `Doctrine\Common\Collections\Collection`/`ArrayCollection` on the two aggregate-internal link collections (Book → BookAuthor, Discount → DiscountAuthor), which the ORM mapping of domain classes requires. `Symfony\Component\Uid\Uuid` is allowed in the domain for Purchase and Rental identifiers.
- Domain entities are **not** `final` (Doctrine must be able to build proxies if it ever needs them). Value objects and views are `final readonly`.
- Money is integer cents. Percentage reduction = `round($base * $percent / 100)` (PHP `round`, half away from zero); final price = `max(0, $base - $reduction)`. Fixed reduction = the discount value in cents, same floor at 0.
- Best discount: among discounts active on the reference date (`startsOn <= date <= endsOn`, both inclusive, compared as dates) whose rule targets the book, the one giving the lowest final price; tie → smallest discount id. At most one discount per book.
- Rental: base price = `round($bookPrice * 10 / 100)`; due date = rental date + 14 days; the best discount is computed against the rental base price. No late penalty.
- ORM optimized variant: DQL or QueryBuilder only — no `NativeQuery`, no `ResultSetMapping`, no `$em->getConnection()`. ORM naive: repositories, `find`/`findBy`/`findAll`, collection navigation, rules applied in PHP.
- All three variants must return identical results; ordering of catalogue entries is `title ASC, id ASC`; `authorNames` ordered `name ASC, id ASC`.
- Reference date used by the benchmark and the seeder: `2026-06-15`. Seed: `42`.
- Branch: `feat/flegars/orm-vs-dbal-benchmark`. Conventional Commits with a mandatory scope.

## Review Focus

1. A discount whose `endsOn` or `startsOn` is exactly the reference date — must count as active. Pinned in Task 2 (unit) and in the reference fixture (discounts 2 and 4) used by Tasks 7–9.
2. A fixed discount larger than the price — final price 0, never negative, in PHP **and** in SQL (`GREATEST(0, …)`). Pinned by book 3 of the reference fixture.
3. A book with two authors both targeted by the same author discount — must appear once, with that discount counted once (SQL joins duplicating rows, ORM fetch-join + `LIMIT` paging on rows instead of books). Pinned by book 3 and discount 5 of the reference fixture, export and page tests.
4. A page beyond the last page returns `[]`, and a last partial page returns only the remaining books. Pinned in Task 7 (`page(3, 2)` and `page(2, 3)`).
5. A write that fails (out of stock, unknown book, already-returned rental) leaves no partial write (no purchase row, stock unchanged). Pinned in the write-adapter tests of Tasks 7–9.

---

## File Structure

```
src/Domain/Book/        Book, BookAuthor, Genre, OutOfStock, BookNotFound
src/Domain/Author/      Author
src/Domain/Discount/    Discount, DiscountAuthor, DiscountKind, DiscountTarget, DiscountPolicy, PriceBasis, Quote
src/Domain/Sale/        Purchase
src/Domain/Rental/      Rental, RentalNotFound, RentalAlreadyReturned
src/Application/        Variant, Bookstore, BookstoreFactory
src/Application/Port/   Shelf, Pricing, Catalogue, PurchaseRepository, RentalRepository, Transaction, VariantAdapters
src/Application/Catalogue/CatalogueEntry
src/Application/UseCase/ PurchaseBook, RentBook, ReturnRental
src/Infrastructure/Persistence/Doctrine/Mapping/*.orm.xml
src/Infrastructure/Persistence/DbalTransaction
src/Infrastructure/Persistence/Orm/        OrmPurchaseRepository, OrmRentalRepository (shared by both ORM variants)
src/Infrastructure/Persistence/OrmNaive/   OrmNaiveShelf, OrmNaivePricing, OrmNaiveCatalogue, OrmNaiveAdapters
src/Infrastructure/Persistence/OrmOptimized/ OrmOptimizedShelf, OrmOptimizedPricing, OrmOptimizedCatalogue, OrmOptimizedAdapters
src/Infrastructure/Persistence/Dbal/       DbalShelf, DbalPricing, DbalCatalogue, DbalPurchaseRepository, DbalRentalRepository, DbalAdapters
src/Infrastructure/Seeding/                CatalogueSeeder, SeedCatalogueCommand
src/Infrastructure/Benchmark/              Scenario, CellStatus, CellResult, QueryCounter, QueryCountingMiddleware (+ driver/connection/statement wrappers), CellMeasurer, RunCellCommand, BenchmarkDatabases, CellRunner, BenchmarkTable, PrepareBenchmarkCommand, BenchmarkCommand
tests/Unit/Domain/..., tests/Unit/Application/... (with in-memory fakes in tests/Unit/Application/Fake/)
tests/Integration/Persistence/  DatabaseTestCase, ReferenceCatalogue, ImplementedVariants, MappingTest, CatalogueAdaptersTest, WriteAdaptersTest
tests/Integration/Seeding/      SeededEquivalenceTest
tests/Integration/Benchmark/    CellMeasurerTest, CellRunnerTest, BenchmarkTableTest
```

Port placement note: the spec mentions one repository port per aggregate; the intent-level write decision makes Book/Author/Discount repository ports unnecessary (no use case loads them through a port). Only the ports above are created (YAGNI); the ORM adapters use Doctrine repositories internally.

---

### Task 0: Baseline branch

**Files:** none created.

- [ ] **Step 1: Create the branch and commit the existing setup**

```bash
git switch -c feat/flegars/orm-vs-dbal-benchmark
git add -A
git commit -m "chore(setup): install doctrine orm-pack, test-pack and postgres 18 compose"
```

(`git add -A` here only covers the setup already reviewed: composer files, compose files, Doctrine/PHPUnit recipe files, `CLAUDE.md`, the spec and this plan.)

- [ ] **Step 2: Verify the database is up**

Run: `docker compose up -d --wait && bin/console dbal:run-sql 'SELECT 1'`
Expected: a one-row table.

---

### Task 1: Book, Author and stock rules

**Files:**
- Create: `src/Domain/Book/{Book,BookAuthor,Genre,OutOfStock,BookNotFound}.php`, `src/Domain/Author/Author.php`
- Modify: `config/services.yaml` (add `exclude: '../src/Domain/'` under `App\:`), `composer.json` (`composer require doctrine/collections` — already pulled in by the ORM, required explicitly because the domain uses it)
- Test: `tests/Unit/Domain/Book/BookTest.php`

**Interfaces:**
- Produces:
  - `enum Genre: string { Fantasy='fantasy'; ScienceFiction='science_fiction'; Thriller='thriller'; Romance='romance'; History='history'; Biography='biography'; Poetry='poetry'; Children='children' }`
  - `class Book` — `__construct(int $id, string $title, int $priceInCents, Genre $genre, \DateTimeImmutable $publishedOn, int $stock, list<int> $authorIds)`; getters `id(): int`, `title(): string`, `priceInCents(): int`, `genre(): Genre`, `publishedOn(): \DateTimeImmutable`, `stock(): int`, `authorIds(): list<int>` (ascending); `takeCopy(): void` (throws `OutOfStock`), `putCopyBack(): void`. Constructor throws `\InvalidArgumentException` on empty `$authorIds`, negative price or negative stock.
  - `class BookAuthor` — `__construct(Book $book, int $authorId)`, `authorId(): int`. Book stores `Collection<int, BookAuthor> $authors`.
  - `class Author` — `__construct(int $id, string $name)`, `id(): int`, `name(): string`.
  - `final class OutOfStock extends \DomainException` — `static forBook(int $bookId): self`; `final class BookNotFound extends \DomainException` — `static withId(int $bookId): self`.

- [ ] **Step 1: Write the failing tests**

```php
public function testTakeCopyDecrementsStock(): void
{
    $book = new Book(1, 'Alpha', 2000, Genre::Fantasy, new \DateTimeImmutable('2010-05-01'), 1, [3, 1]);
    $book->takeCopy();
    self::assertSame(0, $book->stock());
    self::assertSame([1, 3], $book->authorIds());
}

public function testTakeCopyRefusesWhenStockIsEmpty(): void
{
    $book = new Book(2, 'Bravo', 1000, Genre::ScienceFiction, new \DateTimeImmutable('2021-03-10'), 0, [2]);
    $this->expectException(OutOfStock::class);
    $book->takeCopy();
}

public function testPutCopyBackIncrementsStock(): void { /* stock 0 → putCopyBack → 1 */ }

public function testBookNeedsAtLeastOneAuthor(): void { /* [] → InvalidArgumentException */ }
```

- [ ] **Step 2: Run** `php bin/phpunit tests/Unit/Domain/Book` — Expected: FAIL, classes not found.
- [ ] **Step 3: Implement the classes listed in Interfaces.** Exclude `src/Domain/` from service autoregistration in `config/services.yaml`.
- [ ] **Step 4: Run** `php bin/phpunit tests/Unit/Domain/Book` — Expected: PASS. Then `bin/console lint:container` — Expected: OK.
- [ ] **Step 5: Commit** — `git commit -m "feat(domain): add book and author aggregates with stock rules"`

---

### Task 2: Discounts and the best-discount rule

**Files:**
- Create: `src/Domain/Discount/{Discount,DiscountAuthor,DiscountKind,DiscountTarget,DiscountPolicy,PriceBasis,Quote}.php`
- Test: `tests/Unit/Domain/Discount/{DiscountTest,DiscountPolicyTest,PriceBasisTest}.php`

**Interfaces:**
- Consumes: `Genre` (Task 1).
- Produces:
  - `enum DiscountKind: string { Percentage='percentage'; FixedAmount='fixed_amount' }`
  - `enum DiscountTarget: string { Genre='genre'; Authors='authors'; PublicationPeriod='publication_period' }`
  - `class Discount` — named constructors sharing `(int $id, DiscountKind $kind, int $value, \DateTimeImmutable $startsOn, \DateTimeImmutable $endsOn, …)`: `forGenre(…, Genre $genre)`, `forAuthors(…, list<int> $authorIds)`, `forPublicationPeriod(…, \DateTimeImmutable $publishedFrom, \DateTimeImmutable $publishedTo)`. Methods: `id(): int`, `isActiveOn(\DateTimeImmutable $date): bool`, `targets(Genre $genre, \DateTimeImmutable $publishedOn, list<int> $authorIds): bool`, `priceAfter(int $basePriceInCents): int`. Validation (`\InvalidArgumentException`): percentage value in 1..100, fixed value > 0, `startsOn <= endsOn`, non-empty author list, `publishedFrom <= publishedTo`. Nullable properties `?Genre $genre`, `?\DateTimeImmutable $publishedFrom/$publishedTo`, `Collection<int, DiscountAuthor> $authors`.
  - `class DiscountAuthor` — `__construct(Discount $discount, int $authorId)`, `authorId(): int`.
  - `final class DiscountPolicy` — `best(iterable<Discount> $discounts, Genre $genre, \DateTimeImmutable $publishedOn, list<int> $authorIds, int $basePriceInCents, \DateTimeImmutable $on): ?Discount`.
  - `enum PriceBasis { case Sale; case Rental; }` with `const RENTAL_PERCENT = 10` and `baseFor(int $bookPriceInCents): int`.
  - `final readonly class Quote` — `__construct(int $basePriceInCents, ?int $discountId, int $finalPriceInCents)`, `static fromBest(int $basePriceInCents, ?Discount $best): self`.

- [ ] **Step 1: Write the failing tests** (`$on = new \DateTimeImmutable('2026-06-15')`)

```php
public function testPercentageRoundsHalfUp(): void
{
    $d = Discount::forGenre(1, DiscountKind::Percentage, 25, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), Genre::Fantasy);
    self::assertSame(749, $d->priceAfter(999));   // 249.75 → 250 off
}

public function testFixedAmountNeverGoesBelowZero(): void { /* fixed 500 on 400 → 0 */ }

public function testActiveWindowIsInclusiveOnBothEnds(): void
{
    // window 2026-06-01..2026-06-15: active on 2026-06-15 and 2026-06-01, inactive on 2026-06-16 and 2026-05-31
}

public function testTargetsByGenreAuthorsAndPublicationPeriod(): void
{
    // forAuthors([2]) targets authorIds [1, 2], not [1]; forPublicationPeriod(2020-01-01..2022-12-31) targets 2021-03-10 and 2022-12-31, not 2023-01-01
}

public function testBestPicksLowestFinalPrice(): void
{
    // base 1000: 25 % (→750) vs fixed 500 (→500) vs fixed 300 (→700) → the fixed 500 discount
}

public function testBestBreaksTiesOnSmallestId(): void
{
    // ids 7 and 3 both give 800 on base 1000 → id 3
}

public function testBestIgnoresInactiveAndUntargetedDiscounts(): void { /* returns null */ }

public function testRentalBaseIsTenPercentRounded(): void
{
    self::assertSame(40, PriceBasis::Rental->baseFor(400));
    self::assertSame(100, PriceBasis::Rental->baseFor(995)); // 99.5 → 100
    self::assertSame(2000, PriceBasis::Sale->baseFor(2000));
}

public function testQuoteWithoutDiscountKeepsBasePrice(): void
{
    self::assertEquals(new Quote(5000, null, 5000), Quote::fromBest(5000, null));
}
```

- [ ] **Step 2: Run** `php bin/phpunit tests/Unit/Domain/Discount` — Expected: FAIL.
- [ ] **Step 3: Implement.** Date comparisons on `format('Y-m-d')` so a time component never shifts a boundary.
- [ ] **Step 4: Run** `php bin/phpunit tests/Unit/Domain` — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "feat(domain): add discounts and best-discount policy"`

---

### Task 3: Purchase and Rental

**Files:**
- Create: `src/Domain/Sale/Purchase.php`, `src/Domain/Rental/{Rental,RentalNotFound,RentalAlreadyReturned}.php`
- Modify: `composer.json` (`composer require symfony/uid`)
- Test: `tests/Unit/Domain/Sale/PurchaseTest.php`, `tests/Unit/Domain/Rental/RentalTest.php`

**Interfaces:**
- Consumes: `Quote` (Task 2).
- Produces:
  - `class Purchase` — `static record(Uuid $id, int $bookId, Quote $quote, \DateTimeImmutable $on): self`; getters `id(): Uuid`, `bookId(): int`, `purchasedOn()`, `basePriceInCents()`, `discountId(): ?int`, `paidInCents(): int`.
  - `class Rental` — `const DURATION_DAYS = 14`; `__construct(Uuid $id, int $bookId, \DateTimeImmutable $rentedOn, \DateTimeImmutable $dueOn, int $basePriceInCents, ?int $discountId, int $priceInCents, ?\DateTimeImmutable $returnedOn = null)`; `static start(Uuid $id, int $bookId, Quote $quote, \DateTimeImmutable $on): self`; `markReturned(\DateTimeImmutable $on): void` (throws `RentalAlreadyReturned`); getters incl. `returnedOn(): ?\DateTimeImmutable`, `isReturned(): bool`.
  - `RentalNotFound::withId(Uuid $id)`, `RentalAlreadyReturned::withId(Uuid $id)`, both `extends \DomainException`.

- [ ] **Step 1: Write the failing tests**

```php
public function testStartSetsDueDateFourteenDaysLater(): void
{
    $rental = Rental::start(Uuid::v7(), 4, new Quote(500, null, 500), new \DateTimeImmutable('2026-06-15'));
    self::assertSame('2026-06-29', $rental->dueOn()->format('Y-m-d'));
    self::assertSame(500, $rental->priceInCents());
    self::assertFalse($rental->isReturned());
}

public function testReturningTwiceIsRefused(): void { /* markReturned twice → RentalAlreadyReturned */ }

public function testPurchaseRecordsPaidPriceAndDiscount(): void
{
    $p = Purchase::record(Uuid::v7(), 1, new Quote(2000, 5, 1500), new \DateTimeImmutable('2026-06-15'));
    self::assertSame([2000, 5, 1500], [$p->basePriceInCents(), $p->discountId(), $p->paidInCents()]);
}
```

- [ ] **Step 2: Run** `php bin/phpunit tests/Unit/Domain` — Expected: FAIL on the new tests.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php bin/phpunit tests/Unit/Domain` — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "feat(domain): add purchase and rental aggregates"`

---

### Task 4: ORM mapping of the domain and the schema

**Files:**
- Delete: `src/Entity/`, `src/Repository/`
- Modify: `config/packages/doctrine.yaml` (replace the `App` mapping)
- Create: `src/Infrastructure/Persistence/Doctrine/Mapping/{Book.Book,Book.BookAuthor,Author.Author,Discount.Discount,Discount.DiscountAuthor,Sale.Purchase,Rental.Rental}.orm.xml`, `migrations/Version*.php` (generated)
- Test: `tests/Integration/Persistence/{DatabaseTestCase,MappingTest}.php`

**Interfaces:**
- Consumes: all domain classes (Tasks 1–3).
- Produces — the schema every adapter relies on (exact names):
  - `book(id int PK, title varchar(255), price_in_cents int, genre varchar(32), published_on date, stock int)`, index `(title, id)`
  - `author(id int PK, name varchar(255))`
  - `book_author(book_id int FK→book, author_id int)` PK `(book_id, author_id)`, index `(author_id)`
  - `discount(id int PK, kind varchar(16), value int, starts_on date, ends_on date, target varchar(32), genre varchar(32) NULL, published_from date NULL, published_to date NULL)`, index `(starts_on, ends_on)`
  - `discount_author(discount_id int FK→discount, author_id int)` PK `(discount_id, author_id)`, index `(author_id)`
  - `purchase(id uuid PK, book_id int, purchased_on date, base_price_in_cents int, discount_id int NULL, paid_in_cents int)`
  - `rental(id uuid PK, book_id int, rented_on date, due_on date, base_price_in_cents int, discount_id int NULL, price_in_cents int, returned_on date NULL)`, index `(book_id)`
  - Book/Author/Discount ids are **assigned** (`generator strategy="NONE"`); no ORM association between aggregates (author/book/discount references are plain int fields).
  - `abstract class DatabaseTestCase extends KernelTestCase` — `setUp()` boots the kernel and runs `TRUNCATE book, author, book_author, discount, discount_author, purchase, rental CASCADE`; helpers `connection(): Connection`, `entityManager(): EntityManagerInterface`.

- [ ] **Step 1: Configure mapping** in `doctrine.yaml`: one mapping `Domain`, `type: xml`, `is_bundle: false`, `dir: '%kernel.project_dir%/src/Infrastructure/Persistence/Doctrine/Mapping'`, `prefix: 'App\Domain'`. Enums via `enum-type`, dates `date_immutable`, ids of Purchase/Rental `type="uuid"`. Book `one-to-many` → BookAuthor (`cascade persist`, `orphan-removal="true"`), BookAuthor composite id `book` (association key) + `authorId`; same pattern for Discount → DiscountAuthor. If Doctrine raises a proxy/lazy-object deprecation, set `doctrine.orm.enable_native_lazy_objects: true`.
- [ ] **Step 2: Write the failing mapping test**

```php
public function testBookWithAuthorsRoundTrips(): void
{
    $em = $this->entityManager();
    $em->persist(new Book(1, 'Charlie', 400, Genre::Fantasy, new \DateTimeImmutable('2024-01-20'), 1, [2, 1]));
    $em->persist(Discount::forAuthors(5, DiscountKind::Percentage, 25, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), [1, 2]));
    $em->flush();
    $em->clear();

    $book = $em->find(Book::class, 1);
    self::assertSame([1, 2], $book->authorIds());
    self::assertSame(Genre::Fantasy, $book->genre());
    self::assertTrue($em->find(Discount::class, 5)->targets(Genre::Poetry, new \DateTimeImmutable('2000-01-01'), [2]));
}
```

- [ ] **Step 3: Generate and apply the migration**

Run: `bin/console doctrine:migrations:diff -n && bin/console doctrine:migrations:migrate -n && bin/console doctrine:migrations:migrate -n --env=test`
Then check the generated SQL contains the indexes listed above (add any missing ones to the mapping and regenerate).

- [ ] **Step 4: Run** `bin/console doctrine:schema:validate && php bin/phpunit tests/Integration/Persistence/MappingTest.php` — Expected: mapping OK, database in sync, test PASS.
- [ ] **Step 5: Commit** — `git commit -m "feat(persistence): map domain aggregates with doctrine xml and add schema migration"`

---

### Task 5: Ports, use cases and the variant factory

**Files:**
- Create: `src/Application/Port/{Shelf,Pricing,Catalogue,PurchaseRepository,RentalRepository,Transaction,VariantAdapters}.php`, `src/Application/Catalogue/CatalogueEntry.php`, `src/Application/UseCase/{PurchaseBook,RentBook,ReturnRental}.php`, `src/Application/{Variant,Bookstore,BookstoreFactory}.php`, `src/Infrastructure/Persistence/DbalTransaction.php`
- Modify: `config/services.yaml` (also exclude `../src/Application/UseCase/` and `../src/Application/Bookstore.php` — they are built by the factory per variant)
- Test: `tests/Unit/Application/{PurchaseBookTest,RentBookTest,ReturnRentalTest}.php`, fakes in `tests/Unit/Application/Fake/{InMemoryShelf,InMemoryPricing,InMemoryPurchases,InMemoryRentals,ImmediateTransaction}.php`

**Interfaces:**
- Consumes: domain (Tasks 1–3).
- Produces:
  - `interface Shelf { /** @throws BookNotFound|OutOfStock */ takeCopy(int $bookId): void; /** @throws BookNotFound */ putCopyBack(int $bookId): void; }`
  - `interface Pricing { /** @throws BookNotFound */ quote(int $bookId, PriceBasis $basis, \DateTimeImmutable $on): Quote; }`
  - `interface Catalogue { /** @return iterable<CatalogueEntry> */ export(\DateTimeImmutable $on): iterable; /** @return list<CatalogueEntry> */ page(int $page, int $perPage, \DateTimeImmutable $on): array; }` — `$page` is 1-based.
  - `interface PurchaseRepository { save(Purchase $purchase): void; }`
  - `interface RentalRepository { /** @throws RentalNotFound */ get(Uuid $id): Rental; save(Rental $rental): void; }`
  - `interface Transaction { /** @template T @param callable(): T $operation @return T */ run(callable $operation): mixed; }` — implemented once by `DbalTransaction` (wraps `Connection::transactional`; ORM and DBAL share the connection).
  - `final readonly class CatalogueEntry(int $bookId, string $title, list<string> $authorNames, Genre $genre, int $basePriceInCents, ?int $discountId, int $finalPriceInCents)`
  - `enum Variant: string { OrmNaive='orm-naive'; OrmOptimized='orm-optimized'; Dbal='dbal' }`
  - `#[AutoconfigureTag] interface VariantAdapters { variant(): Variant; shelf(): Shelf; pricing(): Pricing; catalogue(): Catalogue; purchases(): PurchaseRepository; rentals(): RentalRepository; }`
  - Use cases (constructor: the ports they need + `Transaction`):
    - `PurchaseBook::__invoke(int $bookId, \DateTimeImmutable $on): Purchase` — in one transaction: `quote(Sale)` → `takeCopy` → `Purchase::record(Uuid::v7(), …)` → `save`.
    - `RentBook::__invoke(int $bookId, \DateTimeImmutable $on): Rental` — same with `PriceBasis::Rental` and `Rental::start`.
    - `ReturnRental::__invoke(Uuid $rentalId, \DateTimeImmutable $on): void` — in one transaction: `get` → `markReturned` → `save` → `putCopyBack($rental->bookId())`.
  - `final readonly class Bookstore(PurchaseBook $purchaseBook, RentBook $rentBook, ReturnRental $returnRental, Catalogue $catalogue)`
  - `BookstoreFactory` — constructor `#[AutowireIterator(VariantAdapters::class)] iterable $adapters, Transaction $transaction`; `for(Variant $variant): Bookstore`; throws `\LogicException` for a variant with no adapters.

- [ ] **Step 1: Write the failing tests** with the in-memory fakes (`InMemoryShelf` keeps `array<int,int>` stock, `InMemoryPricing` returns preset quotes, `ImmediateTransaction` just calls the operation):

```php
public function testPurchaseTakesACopyAndRecordsThePaidPrice(): void
{
    // stock [1 => 3], quote for book 1 = Quote(2000, 5, 1500)
    $purchase = ($this->purchaseBook)(1, new \DateTimeImmutable('2026-06-15'));
    self::assertSame(1500, $purchase->paidInCents());
    self::assertSame(2, $this->shelf->stockOf(1));
    self::assertCount(1, $this->purchases->saved);
}

public function testPurchaseOfAnOutOfStockBookSavesNothing(): void
{
    // stock [2 => 0] → OutOfStock, purchases->saved empty
}

public function testRentUsesTheRentalBasis(): void { /* InMemoryPricing asserts it received PriceBasis::Rental */ }

public function testReturnPutsTheCopyBack(): void { /* open rental on book 4, stock 2 → returned, stock 3 */ }

public function testReturningAnAlreadyReturnedRentalLeavesStockUnchanged(): void { /* RentalAlreadyReturned, stock unchanged */ }
```

- [ ] **Step 2: Run** `php bin/phpunit tests/Unit/Application` — Expected: FAIL.
- [ ] **Step 3: Implement** ports, use cases, `Variant`, `Bookstore`, `BookstoreFactory`, `DbalTransaction`.
- [ ] **Step 4: Run** `php bin/phpunit tests/Unit && bin/console lint:container` — Expected: PASS / OK.
- [ ] **Step 5: Commit** — `git commit -m "feat(application): add ports, write use cases and variant factory"`

---

### Task 6: Reference fixture and test harness for adapters

**Files:**
- Create: `tests/Integration/Persistence/{ReferenceCatalogue,ImplementedVariants}.php`

**Interfaces:**
- Consumes: schema (Task 4), `Variant`, `BookstoreFactory` (Task 5).
- Produces:
  - `final class ReferenceCatalogue` — `const ON = '2026-06-15'`, `const OPEN_RENTAL_ID = '01900000-0000-7000-8000-000000000001'`, `static load(Connection $connection): void` inserting exactly the data below with plain SQL.
  - `trait ImplementedVariants` — `public static function variants(): iterable` yielding `[Variant]` for each variant implemented so far (Task 7 starts it with `Variant::Dbal`; Tasks 8–9 add theirs; Task 9 ends with every `Variant::cases()`).
  - `DatabaseTestCase` (Task 4) gains helpers: `bookstore(Variant $variant): Bookstore` (via `BookstoreFactory`), `on(): \DateTimeImmutable` (`ReferenceCatalogue::ON`), `stockOf(int $bookId): int`, `count(string $table): int`.

Reference data:

| Authors | |
|---|---|
| 1 | Ada Martin |
| 2 | Bruno Petit |
| 3 | Chloé Durand |

| Book | title | price | genre | published | stock | authors |
|---|---|---|---|---|---|---|
| 1 | Alpha | 2000 | fantasy | 2010-05-01 | 3 | 1 |
| 2 | Bravo | 1000 | science_fiction | 2021-03-10 | 0 | 2 |
| 3 | Charlie | 400 | fantasy | 2024-01-20 | 1 | 1, 2 |
| 4 | Delta | 5000 | history | 1999-11-11 | 2 | 3 |

| Discount | kind | value | starts | ends | target |
|---|---|---|---|---|---|
| 1 | percentage | 10 | 2026-01-01 | 2026-12-31 | genre fantasy |
| 2 | fixed_amount | 500 | 2026-06-01 | 2026-06-15 | authors [2] |
| 3 | percentage | 50 | 2025-01-01 | 2025-12-31 | genre history (expired) |
| 4 | fixed_amount | 300 | 2026-06-15 | 2026-07-31 | published 2020-01-01..2022-12-31 |
| 5 | percentage | 25 | 2026-01-01 | 2026-12-31 | authors [1, 2] |

Open rental `OPEN_RENTAL_ID`: book 4, rented 2026-06-01, due 2026-06-15, base 500, no discount, price 500.

Expected catalogue on 2026-06-15 (`CatalogueEntry` fields in order):

```
(1, 'Alpha',   ['Ada Martin'],                Fantasy,        2000, 5,    1500)
(2, 'Bravo',   ['Bruno Petit'],               ScienceFiction, 1000, 2,     500)
(3, 'Charlie', ['Ada Martin', 'Bruno Petit'], Fantasy,         400, 2,       0)
(4, 'Delta',   ['Chloé Durand'],              History,        5000, null, 5000)
```

- [ ] **Step 1: Write `ReferenceCatalogue`, `ImplementedVariants` and the `DatabaseTestCase` helpers.** Nothing consumes them yet: Task 7's tests are the first to run them, and they are committed with Task 7 (a fixture without a consumer is not reviewable alone).

---

### Task 7: DBAL adapters

**Files:**
- Create: `src/Infrastructure/Persistence/Dbal/{DbalShelf,DbalPricing,DbalCatalogue,DbalPurchaseRepository,DbalRentalRepository,DbalAdapters}.php`
- Test: `tests/Integration/Persistence/{CatalogueAdaptersTest,WriteAdaptersTest}.php` (+ Task 6 files)

**Interfaces:**
- Consumes: ports (Task 5), schema (Task 4), `ReferenceCatalogue` (Task 6).
- Produces: `DbalAdapters implements VariantAdapters` with `variant(): Variant::Dbal`.

Adapter rules:
- `DbalShelf::takeCopy`: `UPDATE book SET stock = stock - 1 WHERE id = ? AND stock > 0`; 0 rows affected → `SELECT 1 FROM book WHERE id = ?` decides `BookNotFound` vs `OutOfStock`. `putCopyBack`: `UPDATE … stock + 1`, 0 rows → `BookNotFound`.
- Best discount in SQL (shared by `DbalPricing` and `DbalCatalogue`, extracted into one private SQL builder or a small `BestDiscountSql` class in the same folder). Shape, with `:on` and the base price expression (`b.price_in_cents` for sales, `ROUND(b.price_in_cents * 10 / 100.0)::int` for rentals):

```sql
WITH active AS (
    SELECT * FROM discount WHERE starts_on <= :on AND ends_on >= :on
), candidate AS (
    SELECT b.id AS book_id, a.id AS discount_id,
           GREATEST(0, <base> - CASE a.kind WHEN 'percentage' THEN ROUND(<base> * a.value / 100.0)::int ELSE a.value END) AS final_price
    FROM book b
    JOIN active a ON (a.target = 'genre' AND a.genre = b.genre)
                  OR (a.target = 'publication_period' AND b.published_on BETWEEN a.published_from AND a.published_to)
                  OR (a.target = 'authors' AND EXISTS (
                        SELECT 1 FROM book_author ba JOIN discount_author da ON da.author_id = ba.author_id
                        WHERE ba.book_id = b.id AND da.discount_id = a.id))
), best AS (
    SELECT DISTINCT ON (book_id) book_id, discount_id, final_price
    FROM candidate ORDER BY book_id, final_price, discount_id
)
```

- `DbalCatalogue`: one query = paged/ordered `book` subquery (`ORDER BY title, id` + `LIMIT/OFFSET` for `page`), `LEFT JOIN best`, author names as `json_agg(a.name ORDER BY a.name, a.id)` (decoded in PHP). `export` streams with `iterateAssociative()` and yields `CatalogueEntry`; `page` returns a list.
- `DbalRentalRepository::get` builds `Rental` through its constructor; `save` is an upsert (`INSERT … ON CONFLICT (id) DO UPDATE SET returned_on = EXCLUDED.returned_on`). `DbalPurchaseRepository::save` inserts.

- [ ] **Step 1: Write the failing catalogue test** (uses `ImplementedVariants`, `#[DataProvider('variants')]`):

```php
public function testExportMatchesTheReferenceCatalogue(Variant $variant): void
{
    ReferenceCatalogue::load($this->connection());
    $entries = iterator_to_array($this->bookstore($variant)->catalogue->export(new \DateTimeImmutable(ReferenceCatalogue::ON)), false);
    self::assertEquals(self::expectedEntries(), $entries);   // the four rows above, in order
}

public function testPageReturnsTheRequestedSlice(Variant $variant): void
{
    // page(2, 2) → [Charlie, Delta]; page(2, 3) → [Delta]; page(3, 2) → []
}
```

- [ ] **Step 2: Write the failing write test** (same provider; each test loads the fixture):

```php
public function testPurchaseAppliesTheBestDiscount(Variant $variant): void
{
    $purchase = ($this->bookstore($variant)->purchaseBook)(1, $this->on());
    self::assertSame([2000, 5, 1500], [$purchase->basePriceInCents(), $purchase->discountId(), $purchase->paidInCents()]);
    self::assertSame(2, $this->stockOf(1));
    self::assertSame(1, $this->count('purchase'));
}

public function testPurchaseOfAnOutOfStockBookWritesNothing(Variant $variant): void
{
    // book 2 → OutOfStock; stock 0; 0 purchase rows
}

public function testPurchaseOfAnUnknownBookIsRefused(Variant $variant): void { /* book 99 → BookNotFound */ }

public function testRentalAppliesTheBestDiscountOnTheRentalBase(Variant $variant): void
{
    // book 3: base 40, discount 2 → 0, due 2026-06-29, stock 0; book 4: base 500, no discount, price 500, stock 1
}

public function testReturnClosesTheRentalAndPutsTheCopyBack(Variant $variant): void
{
    // OPEN_RENTAL_ID → returned_on 2026-06-15, book 4 stock 3; second return → RentalAlreadyReturned, stock still 3
}

public function testReturnOfAnUnknownRentalIsRefused(Variant $variant): void { /* RentalNotFound */ }
```

- [ ] **Step 3: Run** `php bin/phpunit tests/Integration/Persistence` — Expected: FAIL (no `DbalAdapters`).
- [ ] **Step 4: Implement the DBAL adapters.**
- [ ] **Step 5: Run** `php bin/phpunit tests/Integration/Persistence` — Expected: PASS for `dbal`.
- [ ] **Step 6: Commit** — `git commit -m "feat(persistence): add dbal adapters and reference catalogue tests"`

---

### Task 8: ORM naive adapters

**Files:**
- Create: `src/Infrastructure/Persistence/OrmNaive/{OrmNaiveShelf,OrmNaivePricing,OrmNaiveCatalogue,OrmNaiveAdapters}.php`, `src/Infrastructure/Persistence/Orm/{OrmPurchaseRepository,OrmRentalRepository}.php`
- Modify: `tests/Integration/Persistence/ImplementedVariants.php` (add `Variant::OrmNaive`)

**Interfaces:**
- Consumes: ports (Task 5), mapping (Task 4), `DiscountPolicy`, `PriceBasis`, `Quote` (Task 2).
- Produces: `OrmNaiveAdapters` (`Variant::OrmNaive`); `OrmPurchaseRepository`/`OrmRentalRepository` (`persist` + `flush`; `get` = `find` or `RentalNotFound`), reused by Task 9.

Adapter rules (everyday code, not deliberately bad):
- Shelf: `find(Book::class, $id)` (or `BookNotFound`) → `$book->takeCopy()` / `putCopyBack()` → `flush()`.
- Pricing: `find` the book → discount repository `findAll()` → `DiscountPolicy::best(…)` with `PriceBasis::baseFor($book->priceInCents())` → `Quote::fromBest`.
- Catalogue: `findBy([], ['title' => 'ASC', 'id' => 'ASC'])` (with `limit`/`offset` for `page`) → for each book, navigate `authorIds()` (lazy collection) and fetch each author with the Author repository `find()`, sort names → discounts loaded once per call with `findAll()` and matched with `DiscountPolicy`. `export` returns the built array.

- [ ] **Step 1: Add `Variant::OrmNaive` to `ImplementedVariants`.**
- [ ] **Step 2: Run** `php bin/phpunit tests/Integration/Persistence` — Expected: FAIL for `orm-naive` only.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php bin/phpunit tests/Integration/Persistence` — Expected: PASS for `dbal` and `orm-naive`.
- [ ] **Step 5: Commit** — `git commit -m "feat(persistence): add naive orm adapters"`

---

### Task 9: ORM optimized adapters

**Files:**
- Create: `src/Infrastructure/Persistence/OrmOptimized/{OrmOptimizedShelf,OrmOptimizedPricing,OrmOptimizedCatalogue,OrmOptimizedAdapters}.php`
- Modify: `tests/Integration/Persistence/ImplementedVariants.php` (yield every `Variant::cases()`)

**Interfaces:**
- Consumes: as Task 8; reuses `OrmPurchaseRepository`, `OrmRentalRepository`.
- Produces: `OrmOptimizedAdapters` (`Variant::OrmOptimized`).

Adapter rules (DQL / QueryBuilder only):
- Shelf: DQL `UPDATE App\Domain\Book\Book b SET b.stock = b.stock - 1 WHERE b.id = :id AND b.stock > 0`; 0 affected → DQL `SELECT COUNT(b.id)` decides `BookNotFound` vs `OutOfStock`.
- Pricing: one scalar DQL query for the book and its author ids (`SELECT b.priceInCents, b.genre, b.publishedOn, ba.authorId FROM Book b JOIN b.authors ba WHERE b.id = :id`), one DQL query for **active candidate** discounts (`SELECT d, da FROM Discount d LEFT JOIN d.authors da WHERE d.startsOn <= :on AND d.endsOn >= :on AND (d.genre = :genre OR da.authorId IN (:authorIds) OR :publishedOn BETWEEN d.publishedFrom AND d.publishedTo)`), then `DiscountPolicy::best`.
- Catalogue: active discounts loaded once with their authors (one DQL fetch-join query); books read as **scalar** rows (`getArrayResult()` for `page` with `setFirstResult/setMaxResults` — no fetch join, so paging counts books; `toIterable()` for `export`); author names fetched per batch of 1,000 books with one DQL query joining `BookAuthor` to `Author` (`JOIN App\Domain\Author\Author a WITH a.id = ba.authorId`); best discount via `DiscountPolicy`; `export` yields entries and calls `$em->clear()` after each batch.

- [ ] **Step 1: Switch `ImplementedVariants` to `Variant::cases()`.**
- [ ] **Step 2: Run** `php bin/phpunit tests/Integration/Persistence` — Expected: FAIL for `orm-optimized` only.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php bin/phpunit` — Expected: all PASS, three variants.
- [ ] **Step 5: Commit** — `git commit -m "feat(persistence): add optimized orm adapters"`

---

### Task 10: Seeder and seeded equivalence

**Files:**
- Create: `src/Infrastructure/Seeding/{CatalogueSeeder,SeedCatalogueCommand}.php`
- Test: `tests/Integration/Seeding/SeededEquivalenceTest.php`

**Interfaces:**
- Consumes: schema (Task 4), `Genre`, `DiscountKind`, `DiscountTarget`, `BookstoreFactory` (Task 5).
- Produces:
  - `CatalogueSeeder::seed(int $books, int $seed = 42): void` — truncates all tables, then multi-row DBAL inserts in chunks of 1,000 inside one transaction, using `new \Random\Randomizer(new \Random\Engine\Mt19937($seed))`. Reference date `2026-06-15`.
  - `#[AsCommand('app:catalogue:seed')]` with `--size` (int, required) and `--seed` (default 42).

Proportions (all derived from `$books`, reproducible for a given seed):
- Authors: `max(10, intdiv($books, 10))`; books get 1 author (70 %), 2 (25 %) or 3 (5 %), distinct.
- Book: title `"Book %06d"` shuffled-order irrelevant (sort is by title), price 300..6000 cents, genre uniform over `Genre::cases()`, published 1950-01-01..2026-06-01, stock 0..5 (≈ 10 % at 0).
- Discounts: **40 at every size** (user decision, 2026-10-25: a count scaling with the catalogue made each book match ~49 discounts at 100,000 books, so every variant measured discount matching instead of persistence overhead), targets split evenly genre / authors (1–3 authors) / publication period (2–10 years wide); kind 50 % percentage (5..50) / 50 % fixed (100..1500); ≈ 70 % active on 2026-06-15, the rest expired or future. A book matches only a few active discounts.
- Purchases: `intdiv($books, 2)`; rentals: `intdiv($books, 5)` returned + `intdiv($books, 5)` open (open ones `returned_on NULL`); ids `Uuid::v7()`; prices consistent with base prices.

- [ ] **Step 1: Write the failing equivalence test**

```php
public function testAllVariantsAgreeOnASeededCatalogue(): void
{
    self::getContainer()->get(CatalogueSeeder::class)->seed(300);
    $on = new \DateTimeImmutable('2026-06-15');
    $reference = iterator_to_array($this->bookstore(Variant::Dbal)->catalogue->export($on), false);
    self::assertCount(300, $reference);
    foreach ([Variant::OrmNaive, Variant::OrmOptimized] as $variant) {
        $this->entityManager()->clear();
        self::assertEquals($reference, iterator_to_array($this->bookstore($variant)->catalogue->export($on), false), $variant->value);
        self::assertEquals(array_slice($reference, 100, 50), $this->bookstore($variant)->catalogue->page(3, 50, $on), $variant->value);
    }
}

public function testAllVariantsQuoteTheSameForEveryBook(): void
{
    // for both PriceBasis cases and the first 50 book ids: the three Pricing adapters return equal Quotes
}

public function testSeedingIsReproducible(): void
{
    // seed(300) twice → identical `SELECT * FROM book ORDER BY id` and `SELECT * FROM discount ORDER BY id`
}
```

- [ ] **Step 2: Run** `php bin/phpunit tests/Integration/Seeding` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php bin/phpunit tests/Integration/Seeding && bin/console app:catalogue:seed --size=100` — Expected: PASS; command reports the inserted counts.
- [ ] **Step 5: Commit** — `git commit -m "feat(seeding): add reproducible catalogue seeder"`

---

### Task 11: Query counting and single-cell measurement

**Files:**
- Create: `src/Infrastructure/Benchmark/{Scenario,CellStatus,CellResult,QueryCounter,QueryCountingMiddleware,QueryCountingDriver,QueryCountingConnection,QueryCountingStatement,CellMeasurer,RunCellCommand}.php`
- Modify: `composer.json` (`composer require symfony/process` — used here only by Task 12, installed now so the benchmark folder has one dependency step)
- Test: `tests/Integration/Benchmark/CellMeasurerTest.php`

**Interfaces:**
- Consumes: `BookstoreFactory`, `Variant` (Task 5), `ReferenceCatalogue` for tests.
- Produces:
  - `enum Scenario: string { CatalogueExport='catalogue-export'; CataloguePage='catalogue-page'; Purchase='purchase'; Rental='rental'; Return='return' }`
  - `enum CellStatus: string { Ok='ok'; Timeout='timeout'; Failed='failed' }`
  - `final readonly class CellResult(Scenario $scenario, Variant $variant, int $size, CellStatus $status, ?float $medianMs = null, ?int $queries = null, ?int $peakBytes = null, ?string $error = null)` with `toArray(): array` / `static fromArray(array): self` (JSON transport between processes).
  - `QueryCounter` service (`reset(): void`, `count(): int`, `increment(): void`), fed by `#[AsMiddleware]` `QueryCountingMiddleware` (wrappers extend DBAL's `AbstractDriverMiddleware` / `AbstractConnectionMiddleware` / `AbstractStatementMiddleware`; count `query`, `exec` and statement `execute`).
  - `CellMeasurer::measure(Scenario $scenario, Variant $variant, int $size, int $runs, \DateTimeImmutable $on): CellResult` — `$size` is only echoed into the result.
  - `#[AsCommand('app:benchmark:cell')]` arguments `scenario variant size`, options `--runs=5`; prints `json_encode($result->toArray())` on stdout.

Measurement rules:
- Targets resolved once before the runs, outside the measurement: purchase/rental book = `SELECT id FROM book WHERE stock > 0 ORDER BY id LIMIT 1`; return rental = `SELECT id FROM rental WHERE returned_on IS NULL ORDER BY id LIMIT 1`; page = `intdiv(intdiv($bookCount, 50), 2) + 1`, 50 per page.
- One warm-up run, then `$runs` measured runs. Each run: `$em->clear()`, `gc_collect_cycles()`, `QueryCounter::reset()`, `memory_reset_peak_usage()`, baseline `memory_get_usage()`, `hrtime(true)` around the call, peak = `memory_get_peak_usage() - baseline`.
- Export runs consume the whole iterable inside the timed section.
- Write scenarios: `beginTransaction()` before the call, `rollBack()` after (also when it throws), so data never drifts.
- Result: median time in ms, query count of the last run, max peak across runs. An exception → `CellStatus::Failed` with the message.

- [ ] **Step 1: Write the failing tests** (reference fixture loaded, test env):

```php
public function testDbalPageIsASingleQuery(): void
{
    $result = $this->measurer->measure(Scenario::CataloguePage, Variant::Dbal, 4, 2, new \DateTimeImmutable('2026-06-15'));
    self::assertSame(CellStatus::Ok, $result->status);
    self::assertSame(1, $result->queries);
    self::assertGreaterThan(0, $result->medianMs);
}

public function testNaiveExportIssuesMoreQueriesThanDbal(): void { /* orm-naive queries > dbal queries on CatalogueExport */ }

public function testWriteScenariosLeaveTheDataUntouched(): void
{
    // measure Purchase, Rental, Return for each Variant; afterwards stocks are [3, 0, 1, 2], 0 purchases, 1 rental still open
}

public function testCellCommandPrintsJson(): void { /* CommandTester on app:benchmark:cell catalogue-page dbal 4 --runs=1 → CellResult::fromArray(json_decode(output)) status ok */ }
```

- [ ] **Step 2: Run** `php bin/phpunit tests/Integration/Benchmark/CellMeasurerTest.php` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php bin/phpunit tests/Integration/Benchmark/CellMeasurerTest.php` — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "feat(benchmark): measure one scenario cell with query counting"`

---

### Task 12: Benchmark orchestration and terminal table

**Files:**
- Create: `src/Infrastructure/Benchmark/{BenchmarkDatabases,CellRunner,BenchmarkTable,PrepareBenchmarkCommand,BenchmarkCommand}.php`
- Modify: `CLAUDE.md` (Project state, layout, benchmark commands)
- Test: `tests/Integration/Benchmark/{CellRunnerTest,BenchmarkTableTest}.php`

**Interfaces:**
- Consumes: `CellResult`, `Scenario`, `CellStatus` (Task 11), `Variant` (Task 5), `app:catalogue:seed` (Task 10), `app:benchmark:cell` (Task 11).
- Produces:
  - `BenchmarkDatabases` — `#[Autowire('%env(DATABASE_URL)%')]`; `urlFor(int $size): string` = same URL with database name `app_bench_{size}`.
  - `CellRunner::run(Scenario $scenario, Variant $variant, int $size, int $runs, float $timeoutSeconds): CellResult` — `Process` running `bin/console app:benchmark:cell …` with env `APP_ENV=prod`, `APP_DEBUG=0`, `DATABASE_URL=urlFor($size)`; `ProcessTimedOutException` → `CellStatus::Timeout`; non-zero exit → `Failed` with stderr tail.
  - `BenchmarkTable::render(OutputInterface $output, list<CellResult> $results): void` — one table per scenario; rows = size (100, 10 000, 100 000); for each variant (in `Variant::cases()` order) three columns `ms` (median, 1 decimal), `queries`, `peak MB` (1 decimal); a timed-out cell shows `timeout` in its three columns, a failed one `failed`.
  - `#[AsCommand('app:benchmark:prepare')]` `--sizes=100,10000,100000`: for each size, child processes with `DATABASE_URL=urlFor(size)`: `doctrine:database:create --if-not-exists`, `doctrine:migrations:migrate -n`, `app:catalogue:seed --size=<size>`; then `cache:warmup` with `APP_ENV=prod`.
  - `#[AsCommand('app:benchmark')]` options `--sizes=100,10000,100000`, `--scenarios=` (default all), `--variants=` (default all), `--runs=5`, `--timeout=120`; runs every cell sequentially through `CellRunner`, shows a progress bar, then `BenchmarkTable::render`. Fails with a clear message if a size's database is missing (tell the user to run `app:benchmark:prepare`).

- [ ] **Step 1: Write the failing tests**

```php
public function testTableShowsTimeoutCells(): void
{
    $output = new BufferedOutput();
    (new BenchmarkTable())->render($output, [
        new CellResult(Scenario::CatalogueExport, Variant::Dbal, 100000, CellStatus::Ok, 812.34, 1, 52_428_800),
        new CellResult(Scenario::CatalogueExport, Variant::OrmNaive, 100000, CellStatus::Timeout),
    ]);
    self::assertStringContainsString('812.3', $output->fetch());
    // and contains 'timeout', '50.0', '100 000'
}

public function testRunnerReportsATimeout(): void
{
    // CellRunner against the test DB URL with $timeoutSeconds = 0.05 → CellStatus::Timeout
}

public function testRunnerReturnsTheChildResult(): void
{
    // ReferenceCatalogue loaded in app_test, runner pointed at the app_test URL (override urlFor in a test subclass or pass the URL through a constructor argument), CataloguePage / Dbal / runs 1 → CellStatus::Ok, queries 1
}
```

- [ ] **Step 2: Run** `php bin/phpunit tests/Integration/Benchmark` — Expected: FAIL on the new tests.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run the whole suite and a real smoke run**

Run: `php bin/phpunit && bin/console app:benchmark:prepare --sizes=100 && bin/console app:benchmark --sizes=100 --runs=1`
Expected: tests PASS; five tables, one row (`100`) each, nine figures per row, no `failed` cell.

- [ ] **Step 5: Update `CLAUDE.md`**: replace "Project state" with the real layout (Domain / Application / Infrastructure folders, three adapter sets, XML mapping location), add `app:benchmark:prepare`, `app:benchmark` and `app:catalogue:seed` to Commands, and note that the benchmark uses the `app_bench_{size}` databases and the `prod` environment.
- [ ] **Step 6: Commit** — `git commit -m "feat(benchmark): add benchmark orchestration and terminal table"`
