# ORM vs DBAL — bookstore benchmark

Proof of concept that measures the overhead of **Doctrine ORM** compared with **Doctrine DBAL alone** on the same bookstore use cases (books, authors, discounts, purchases, rentals), in a hexagonal Symfony application.

Every scenario is implemented three ways, behind the same application ports:

| Variant | What it is |
|---|---|
| **ORM naive** | Everyday Doctrine code: repositories (`find`, `findBy`, `findAll`), lazy navigation of relations, business rules applied in PHP. No SQL, no DQL. |
| **ORM optimized** | What a Doctrine expert writes: DQL / QueryBuilder only (fetch joins, array hydration, bulk `UPDATE`, `toIterable()` with periodic `clear()`). Never native SQL. |
| **DBAL** | Hand-written SQL through `Doctrine\DBAL\Connection`, rows mapped by hand. |

The three variants return identical results: the test suite checks every variant against a hand-built catalogue with exact expected values, and against generated catalogues of 300 and 2,500 books.

## Scenarios

| Scenario | What it does |
|---|---|
| `catalogue-export` | Every book with its author names, best active discount and final price. |
| `catalogue-page` | The same view, one page of 50 books taken from the middle of the catalogue. |
| `purchase` | Find the best discount, take a copy out of stock, record the purchase. |
| `rental` | Same as purchase on the rental price (10 % of the book price), due in 14 days. |
| `return` | Close an open rental and put the copy back in stock. |

When several discounts match a book, only the one giving the lowest final price applies. Discounts target a genre, one or more authors, or a publication period.

## Results

Run on 2026-10-25 — Apple M1 Max, 64 GB, macOS 26.6, PHP 8.4.8 (CLI, `memory_limit` 128 MB), PostgreSQL 18.6 in Docker, Symfony 8.1 in the `prod` environment. Reference date 2026-06-15, 5 measured runs after a warm-up, median time, 120 s timeout per cell.

Generated catalogues: 100, 10,000 and 100,000 books, with authors (one per ten books, at least 10), 40 discounts at every size (23, 28 and 31 of them active on the reference date; about 2.3 match each book at 100,000 books), purchases and rentals in fixed proportions, seed 42.

Each cell reads **median time · SQL queries · peak RSS growth**.

### Catalogue export

| Books | ORM naive | ORM optimized | DBAL |
|---|---|---|---|
| 100 | 29.7 ms · 121 · 12.5 MB | 5.4 ms · 3 · 13.7 MB | 2.0 ms · 1 · 10.6 MB |
| 10,000 | 2,752.3 ms · 11,010 · 55.2 MB | 431.8 ms · 12 · 15.4 MB | 37.3 ms · 1 · 12.1 MB |
| 100,000 | **failed: out of memory** | 4,734.5 ms · 102 · 28.2 MB | 402.7 ms · 1 · 30.3 MB |

### Catalogue page (50 books)

| Books | ORM naive | ORM optimized | DBAL |
|---|---|---|---|
| 100 | 17.2 ms · 71 · 1.7 MB | 3.4 ms · 3 · 2.5 MB | 1.5 ms · 1 · 0.0 MB |
| 10,000 | 27.6 ms · 125 · 1.8 MB | 4.0 ms · 3 · 1.2 MB | 2.5 ms · 1 · 0.0 MB |
| 100,000 | 30.7 ms · 123 · 1.8 MB | 8.1 ms · 3 · 1.2 MB | 4.3 ms · 1 · 0.0 MB |

### Purchase

| Books | ORM naive | ORM optimized | DBAL |
|---|---|---|---|
| 100 | 7.0 ms · 20 · 1.7 MB | 2.6 ms · 8 · 3.0 MB | 1.8 ms · 5 · 0.0 MB |
| 10,000 | 7.4 ms · 19 · 1.6 MB | 2.3 ms · 8 · 1.6 MB | 1.8 ms · 5 · 0.0 MB |
| 100,000 | 7.6 ms · 20 · 1.7 MB | 2.5 ms · 8 · 1.9 MB | 2.0 ms · 5 · 0.0 MB |

### Rental

| Books | ORM naive | ORM optimized | DBAL |
|---|---|---|---|
| 100 | 6.7 ms · 20 · 1.7 MB | 2.5 ms · 8 · 1.9 MB | 1.9 ms · 5 · 0.0 MB |
| 10,000 | 6.2 ms · 19 · 1.7 MB | 2.3 ms · 8 · 1.7 MB | 1.9 ms · 5 · 0.0 MB |
| 100,000 | 6.6 ms · 20 · 1.7 MB | 2.3 ms · 8 · 1.9 MB | 1.9 ms · 5 · 0.0 MB |

### Return

| Books | ORM naive | ORM optimized | DBAL |
|---|---|---|---|
| 100 | 2.5 ms · 10 · 1.6 MB | 1.6 ms · 7 · 3.0 MB | 1.4 ms · 5 · 0.0 MB |
| 10,000 | 2.4 ms · 10 · 1.5 MB | 1.7 ms · 7 · 1.5 MB | 1.2 ms · 5 · 0.0 MB |
| 100,000 | 2.4 ms · 10 · 1.5 MB | 2.0 ms · 7 · 1.6 MB | 1.3 ms · 5 · 0.0 MB |

### What the numbers say

- **Bulk reads are where the ORM costs most.** At 100,000 books DBAL exports the catalogue about 12 times faster than well-written ORM code (403 ms vs 4,735 ms). The naive ORM issues one query per author and per lazy collection (11,010 queries at 10,000 books) and runs out of PHP's default 128 MB memory limit at 100,000 books, inside Doctrine's unit of work.
- **Paged reads narrow the gap.** On one page of 50 books the optimized ORM is about twice as slow as DBAL (8.1 ms vs 4.3 ms at 100,000 books). The naive ORM still issues about 120 queries per page.
- **Single writes are close.** For a purchase, rental or return the optimized ORM is within about 0.5 ms of DBAL. The naive ORM is slower because it loads every discount and lazy-loads the authors of each author-targeted discount.
- **Write cost does not depend on catalogue size**: every write touches a single book.

### Reading the memory column

The column is the growth of the process's peak resident memory (RSS) during the measurement. It counts the rows PostgreSQL's driver buffers outside PHP's heap, which PHP's own memory functions miss. A `0.0` means the measurement never pushed the process past the peak it had already reached while booting, not that it used no memory.

## Running it

Requirements: PHP 8.4 with `pdo_pgsql`, Composer, Docker, optionally the Symfony CLI.

```bash
composer install
docker compose up -d --wait
bin/console app:benchmark:prepare --sizes=100,10000,100000
bin/console app:benchmark --sizes=100,10000,100000 --runs=5 --timeout=120 2>/dev/null
```

`app:benchmark:prepare` creates, migrates and seeds one database per size (`app_bench_100`, …). `app:benchmark` runs every scenario × variant × size in its own child process and prints one table per scenario; `--scenarios=` and `--variants=` restrict the run. One cell alone, as JSON:

```bash
bin/console app:benchmark:cell catalogue-export dbal 10000 --runs=3 --no-debug
```

Tests (the dev and test databases need the schema: `bin/console doctrine:migrations:migrate -n` and the same with `--env=test`):

```bash
bin/console doctrine:database:create --env=test --if-not-exists
php bin/phpunit
```

## Design

- Design document: `docs/superpowers/specs/2026-10-25-orm-vs-dbal-bookstore-benchmark-design.md`
- Implementation plan: `docs/superpowers/plans/2026-10-25-orm-vs-dbal-bookstore-benchmark.md`
