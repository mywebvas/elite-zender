# Contributing

## Before you change anything

1. Read [`docs/README.md`](docs/README.md). It holds the **locked decision
   log** — row-scoped tenancy, Blade + Alpine + Tailwind 4, PostgreSQL 17,
   Redis 7, UUID v7 primary keys. These are settled; reopening one needs an
   explicit decision record, not a pull request that quietly does otherwise.
2. Read the module spec for the area you are touching (`docs/01`–`docs/09`).
3. Run `composer check` on a clean checkout so you know the baseline is green
   before you start.

## Local setup

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
make dev
```

## Definition of done

A change is finished when **all** of the following hold:

- [ ] `composer lint` passes (Pint, Laravel preset — run `composer fix`).
- [ ] `composer analyse` passes (PHPStan level 6, no new baseline entries).
- [ ] `composer test` passes, and the change is covered by a test that fails
      without it.
- [ ] `npm run build` succeeds if you touched anything under `resources/`.
- [ ] Any new migration is reversible and has been run against PostgreSQL, not
      just SQLite. SQLite's loose typing hides foreign-key type mismatches.
- [ ] Any new query against a tenant-owned table is either covered by the
      `HasTenant` global scope or explicitly justified in a comment.
- [ ] Any new `exists:`/`unique:` validation rule on a tenant-owned table is
      scoped with `->where('tenant_id', …)`.
- [ ] User-visible strings are written for a human, not for a developer.

## Conventions

**Commits** follow [Conventional Commits](https://www.conventionalcommits.org):
`feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`, `perf:`, `build:`.
Explain *why* in the body — the diff already shows the *what*.

**Comments** explain intent and non-obvious constraints. A comment that
restates the line below it is noise; a comment that records why a naive
implementation is wrong is worth its weight.

**Tests.** Feature tests use Pest's closure style. Anything that once broke in
production goes in `tests/Feature/Regression/` with a header explaining the
original failure.

**Types.** New code is fully typed: parameter types, return types and generics
on collections and relations. `mixed` needs a reason.

## Reporting security issues

Do not open a public issue — see [`SECURITY.md`](SECURITY.md).
