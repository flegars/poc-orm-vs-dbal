# ORM vs DBAL bookstore benchmark — design

Date: 2026-10-25
Status: awaiting approval

## Intent

A proof of concept for the team that measures the **overhead of Doctrine ORM compared with Doctrine DBAL alone** on identical bookstore use cases. It is a neutral comparison: nothing is being argued for in advance, and the numbers decide. The deliverable the team looks at is a **terminal table printed by a benchmark console command**.

Every scenario is implemented three ways:

| Variant | Meaning |
|---|---|
| **ORM naive** | What a typical developer writes day to day: load entities through repositories, navigate relations and let them lazy-load, look up referenced aggregates by ID through their repository, match rules in PHP. Plausible everyday code, not deliberately bad code. |
| **ORM optimized** | What someone who knows Doctrine well would write: fetch joins, DQL doing the filtering, array/DTO hydration, `getReference()`, DQL bulk `UPDATE`, `toIterable()` with periodic `clear()`, batching, etc. **DQL or the QueryBuilder only: no `NativeQuery`/`ResultSetMapping` and no direct use of the underlying connection** (decided by the user). If a rule such as best-discount selection cannot be fully expressed in DQL, the remainder is done in PHP. |
| **DBAL** | Hand-written SQL through `Doctrine\DBAL\Connection`, rows mapped to domain objects or read views by hand. |

## Goals

- Show time, number of SQL queries and peak memory for each scenario × variant × catalogue size.
- Show how the gap evolves with data volume: **100, 10,000 and 100,000 books**.
- Guarantee that all three variants of a scenario produce exactly the same result, proven by tests, so no variant can be accused of cheating.
- Keep the domain and application layers strictly persistence-agnostic (hexagonal architecture).

## Non-goals

- No HTTP API or UI. The comparison runs through the application layer from a console command.
- **No concurrency benchmark** (e.g. two buyers racing for the last copy). Every scenario is measured as a single sequential call. The domain must still refuse a sale or rental when no copy is free.
- No late-return penalties, no customers/accounts management beyond what a purchase or rental needs to be recorded, no payment.
- No charts or reports beyond the terminal table.

## Anti-goals (failure even if it works)

- A strawman naive ORM variant, or a DBAL variant that returns less data or skips a business rule. Any of those invalidates the comparison.
- Doctrine leaking into the domain (attributes, `Collection` types imposed by ORM needs are acceptable only if justified below, Doctrine base classes are not).
- Results that are not reproducible between two runs on the same machine (random data, "now" moving between runs, cache warmed by a previous variant).

## Constraints

- Symfony 8.1, PHP 8.4, PostgreSQL 18 in Docker (`compose.yaml`), Doctrine ORM + DBAL + migrations already installed. Follow `AGENTS.md` and `CLAUDE.md` (Flex installs, attributes, autowiring, migrations for schema changes, `@Symfony` code style, no narrative comments).
- The schema is created by Doctrine migrations generated from the ORM mapping. The DBAL adapters use the same schema.
- The Doctrine recipe defaults (`src/Entity/`, `src/Repository/`, `App` attribute mapping in `config/packages/doctrine.yaml`) must be replaced by the hexagonal layout below.

## Domain

### Aggregates

- **Book** (aggregate root): title, price, genre, publication date, stock of copies, and its author(s) **referenced by ID**. Book does not know about discounts.
- **Author**: independent aggregate, can have many books.
- **Discount**: independent aggregate. It has a validity window (start and end dates) and a targeting rule: **a genre, one or more authors, or a publication-date range**. One discount can cover many books.
- **Purchase**: references a Book by ID; records the price actually paid (after discount) and the date. Removes one copy from stock for good.
- **Rental**: references a Book by ID; has a start date, a due date, a rental price (after discount), and a return date once returned. Takes a copy out of stock until returned.

### Business rules

1. A discount applies to a book when it is active at the reference date (start ≤ date ≤ end) and its targeting rule matches the book's genre, one of its authors, or its publication date.
2. **When several active discounts match a book, only the best one applies — never more than one per book**, for purchases and rentals alike.
3. A purchase or a rental is refused when the book has no copy left in stock. A purchase decrements stock permanently; a rental decrements it and the matching return increments it again.
4. Returning a rental sets its return date and puts the copy back in stock. No penalty for late returns.

### Ports

- Write side: one repository port per aggregate (Book, Author, Discount, Purchase, Rental), plus whatever port the application needs to find the best discount for a book.
- Write side, intent level (decided by the user): purchase, rental and return also go through **intent-level ports** such as "take a copy of this book if one is available" / "put a copy back". The variants use them differently:
  - **ORM naive** implements them with the classic cycle: load the Book aggregate, let the domain refuse when stock is 0 and change the stock, then flush.
  - **ORM optimized** and **DBAL** may perform the check and the change atomically in the database (DQL bulk `UPDATE ... WHERE stock > 0` for the ORM, a plain SQL `UPDATE ... WHERE stock > 0` for DBAL) and report whether a copy was taken.
  - The domain still owns rule 3: the out-of-stock refusal is expressed by the domain (exception or result type) in every variant, and the equivalence tests prove all three refuse exactly the same cases.
- Read side: a **catalogue read port** returning a flat view per book — book id, title, author names, genre, base price, best active discount (or none), final price — in two forms: full export and one page.
- The full-export contract returns an **iterable** of views, identical for every variant. Each variant fills it according to its own definition: ORM naive loads everything then returns it, ORM optimized streams with `toIterable()` and periodic `clear()`, DBAL streams with `iterateAssociative()`. The benchmark consumes the whole iterable inside the measurement.
- Each port has **three infrastructure adapters**: ORM naive, ORM optimized, DBAL.

## ORM mapping (decided)

The **domain classes are mapped directly by Doctrine ORM using XML mapping files kept in the infrastructure layer**. There are no separate Doctrine entity classes and no conversion step, because that would add a cost only the ORM side pays. The DBAL adapters build the same domain objects by hand from their rows, so both sides deliver identical objects.

Consequence to handle: "author(s) referenced by ID" must stay mappable by Doctrine without a JSON column (which would break SQL matching). A small child entity inside the Book aggregate (e.g. a book–author link holding the author ID) and its equivalent inside Discount for author targeting are acceptable; the exact shape is left to the builder.

## Scenarios

Each scenario is run for the three variants and the three catalogue sizes.

1. **Catalogue export** — every book in the catalogue as the read view described above (author names, best active discount, final price).
2. **Catalogue page** — the same view, one page of 50 books taken out of the full catalogue, with a stable sort order.
3. **Purchase** — load the book, find the best active discount, take a copy out of stock, record the purchase with the price paid.
4. **Rental** — same as purchase, but creates a rental with a due date and the discounted rental price.
5. **Return** — close an open rental and put the copy back in stock.

## Benchmark

- A **seeding console command** loads a catalogue of a given size (100 / 10,000 / 100,000 books) with authors, genres, discounts, past purchases and open rentals in fixed proportions, generated from a **fixed random seed** so every run and every variant sees identical data.
- A **benchmark console command** runs scenario × variant × size, performs a warm-up run then several measured runs, and prints a terminal table with the **median time, number of SQL queries and peak memory** for each cell.
- Isolation between measurements:
  - Each measurement starts with a cleared Doctrine identity map (no entity cached by a previous run or variant).
  - Write scenarios run inside a transaction that is rolled back, so stock and rows never drift between runs.
  - The reference date ("today") is fixed for the whole benchmark, so the set of active discounts never changes between runs.
- A variant that takes too long (likely the naive ORM exporting 100,000 books) is stopped after a time limit and shown as `timeout` instead of blocking the run.

## Tests

- **Equivalence tests**: for each scenario, on a small seeded dataset, the three variants return identical results (read views) or leave the database in an identical state (writes).
- Domain unit tests for the business rules, in particular best-discount selection and the out-of-stock refusal.

## Builder's calls (made during brainstorming, not confirmed by the user)

- **Discount value**: a discount is either a percentage or a fixed amount. "Best" means the one giving the **lowest final price**; the final price never goes below zero.
- **Money** is stored as integer cents.
- **A book has one or more authors.**
- **One targeting rule per discount** (a genre, *or* a set of authors, *or* a publication-date range), not combinations.
- **Rental price** is a fixed percentage of the book's price, and the rental duration is fixed (e.g. 14 days); the best discount applies to the rental price.
- The benchmark calls the application layer directly and selects the variant by swapping the adapters behind the ports.
- **Export contract** is an iterable for all variants; whether a variant streams follows its naive/optimized/DBAL definition (see Ports).
- **Naive ORM lookups**: since Book references authors by ID, the naive variant fetches each author through the Author repository and loads the active discounts to match them in PHP. No infrastructure-only association is added to make lazy-loading possible.
- **Rounding and ties**: percentage discounts are rounded to the nearest cent (half up); when two discounts give the same final price, the one with the smallest ID wins. One rule, shared by every variant, checked by the equivalence tests.
- The benchmark runs in the `prod` environment (no profiler, no debug data collection) against the database seeded for each size.

## Left to the builder

- Directory layout of the hexagonal structure, naming of ports, use cases and adapters.
- How the variant is selected at runtime (tagged services, `#[Target]`, a registry keyed by variant, …).
- Identifier strategy (e.g. UUID v7 vs integer identity), genre representation (enum vs table).
- Exact data proportions for seeding (authors per size, discounts count, purchases and rentals volume), which page the catalogue-page scenario reads, the number of measured runs and the timeout value.
- How measurements are isolated in practice (same process vs child process per measurement), how queries are counted (e.g. a DBAL middleware), and whether each catalogue size lives in its own database.
- The exact queries of each variant, as long as the naive / optimized definitions above are respected.
