# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

@AGENTS.md

## Project goal

Proof of concept that demonstrates the **performance difference between Doctrine ORM and Doctrine DBAL alone** on the same use cases.

- **Domain:** a classic bookstore — Books, Authors, Discounts, and book rental.
- **Database:** PostgreSQL, latest stable version.
- **Architecture:** hexagonal. The domain and application layers stay persistence-agnostic and define the repository ports; the comparison lives entirely in the infrastructure layer, with one ORM adapter and one DBAL adapter for each port. Both adapters must expose identical behavior, so a benchmark measures only the persistence strategy, never a functional difference.

Doctrine mapping must not leak into the domain: ORM mapping belongs to the ORM adapter (e.g. XML/attribute mapping on infrastructure-side classes), not to domain entities, otherwise the DBAL side would depend on ORM metadata.

## Architecture

Design and plan: `docs/superpowers/specs/2026-10-25-orm-vs-dbal-bookstore-benchmark-design.md`, `docs/superpowers/plans/2026-10-25-orm-vs-dbal-bookstore-benchmark.md`.

- `src/Domain/` — plain PHP aggregates (Book, Author, Discount, Purchase, Rental) and the best-discount rule (`DiscountPolicy`). Excluded from service autoregistration. Only `Doctrine\Common\Collections` and `Symfony\Component\Uid\Uuid` may be imported here.
- `src/Application/` — ports (`Port/`: Shelf, Pricing, Catalogue, PurchaseRepository, RentalRepository, Transaction, VariantAdapters), use cases (`UseCase/`: PurchaseBook, RentBook, ReturnRental) and `BookstoreFactory::for(Variant)`, which wires one variant's adapters behind the ports. Use cases and `Bookstore` are built by the factory, not by the container.
- `src/Infrastructure/Persistence/` — the domain classes are mapped by ORM **XML** files in `Doctrine/Mapping/` (no Doctrine attributes in the domain). Three adapter sets, one per `Variant`: `OrmNaive/` (repositories and lazy navigation only, no SQL/DQL), `OrmOptimized/` (DQL/QueryBuilder only, never native SQL or the raw connection), `Dbal/` (hand-written SQL, best discount computed in `BestDiscountSql`). `Orm/` holds the purchase/rental repositories shared by both ORM variants.
- `src/Infrastructure/Seeding/` — reproducible catalogue generator (seed 42, reference date 2026-06-15, 40 discounts at every size).
- `src/Infrastructure/Benchmark/` — `CellMeasurer` measures one scenario × variant × size (warm-up, median time, SQL query count via a DBAL middleware, peak PHP memory; writes run in a rolled-back transaction); `CellRunner` runs each cell in a child process in `prod` with a timeout; `BenchmarkTable` renders the result.

All three variants must return identical results: `tests/Integration/Persistence` (hand-built `ReferenceCatalogue` with exact expected values) and `tests/Integration/Seeding/SeededEquivalenceTest` (generated catalogues) run every variant through the `ImplementedVariants` data provider. Any change to an adapter must keep them green for all variants.

## Infrastructure

- PostgreSQL 18 runs in Docker (`compose.yaml`, service `database`, image `postgres:18-alpine`). Since Postgres 18 the image keeps its data under `/var/lib/postgresql`, so the volume is mounted there and **not** at `/var/lib/postgresql/data` as in older recipes.
- `compose.override.yaml` publishes the database on fixed host port `5432`, so plain `bin/console` and `bin/phpunit` connect through the `DATABASE_URL` in `.env` (`app` / `!ChangeMe!` @ `127.0.0.1:5432/app`, `serverVersion=18`).
- The test env uses the `app_test` database (`dbname_suffix` in `doctrine.yaml`).

## Commands

- First setup: `composer install`, `docker compose up -d --wait`, `bin/console doctrine:database:create --if-not-exists` (add `--env=test` for the test DB), `bin/console doctrine:migrations:migrate -n`
- Run the app: `symfony serve -d` (stop with `symfony server:stop`)
- Console: `symfony console <cmd>` or `bin/console <cmd>`
- Tests: `php bin/phpunit`; one test: `php bin/phpunit --filter <testName>`; one variant of the adapter tests: `--filter '@dbal'` (or `@orm-naive`, `@orm-optimized`). `TEST_TOKEN=2` switches to a second test database (`app_test2`) so two runs don't truncate each other's tables.
- Seed a catalogue: `bin/console app:catalogue:seed --size=10000 --no-debug` (debug mode runs out of memory at 100,000 books).
- Benchmark: `bin/console app:benchmark:prepare --sizes=100,10000,100000` (creates, migrates and seeds one `app_bench_{size}` database per size), then `bin/console app:benchmark --sizes=100,10000,100000 --runs=5 --timeout=120` (optional `--scenarios=`, `--variants=`). One cell alone: `bin/console app:benchmark:cell <scenario> <variant> <size> --runs=3 --no-debug` (prints JSON).
- Sanity checks: `bin/console lint:container`, `bin/console doctrine:schema:validate`, `bin/console debug:router`

## Structure

- `src/Kernel.php` is the stock `MicroKernelTrait` kernel; `src/` is auto-registered as services (`config/services.yaml`), minus the exclusions above. `BookstoreFactory` is made public in the test env only (`when@test`) so tests can fetch it.
- There is no HTTP layer: the comparison runs through the application layer from console commands.
- `config/reference.php` is the generated config reference for installed bundles — regenerated by Symfony, don't hand-edit.
