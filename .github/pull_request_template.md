## What & why

<!-- What changes, and what problem it solves. Link the issue. -->

## How to verify

<!-- The exact steps a reviewer should take. -->

## Checklist

- [ ] `composer lint` passes
- [ ] `composer analyse` passes (PHPStan level 6)
- [ ] `composer test` passes, and a test covers this change
- [ ] `npm run build` succeeds (if `resources/` changed)
- [ ] Migrations are reversible and verified on PostgreSQL
- [ ] New queries on tenant-owned tables are tenant-scoped
- [ ] New `exists:`/`unique:` rules are pinned to `tenant_id`
- [ ] Docs updated (`docs/`, `README.md`, `CHANGELOG.md`)

## Risk

<!-- Blast radius, rollback plan, and anything that needs watching after deploy. -->
