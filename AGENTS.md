# Repository Instructions

## Runtime And Entry Point

- Match CI with PHP 8.3 plus `pdo_mysql` and Node 20. The locked PHPUnit 11 toolchain requires PHP 8.2+, despite the README's broader PHP 8 claim.
- Serve `public/` as the document root. Requests enter `public/index.php` and select routes with `?r=home/index`; never expose the repository root.
- The web entry point parses `.env` itself, loads `app/helpers/utils.php`, and dispatches only routes registered in `config/routes.php`.
- Application classes are mostly global and wired with explicit `require_once` calls. The web entry point does not load Composer's autoloader, so do not assume the PSR-4 mapping alone makes a new class available.

## Request Wiring

- Each `config/routes.php` entry must declare `controller`, `action`, allowed `methods`, `public`, and `response` (`html` or `json`).
- Keep controller helpers protected/private. `tests/Unit/RoutingRegistryTest.php` requires every public controller method to be a registered action.
- POST routes receive global CSRF validation by default. Set `csrf => false` only when the action validates the token itself, as the JSON NUMA actions do.
- `database/schema.sql` is the canonical schema; there is no migration runner. `database/seed.sql` is optional demo data.

## Setup And Verification

From a clean checkout, mirror CI in this order:

```bash
npm ci
npm run build
composer install --no-interaction --prefer-dist
composer lint:design
composer test
vendor/bin/phpstan analyse
composer build:css
```

- `composer lint:design` is blocking: it rejects design-token drift, raw colors outside its allowlist, and Bootstrap icon classes.
- PHPStan reads `phpstan.dist.neon`; use `vendor/bin/phpstan analyse` without inventing a different path set.

## Tests And Database

- Run DB-free tests with `vendor/bin/phpunit --testsuite Unit`.
- Run integration tests with `vendor/bin/phpunit --testsuite Integration`; they require an isolated MySQL database named `benehom_test` and must never target application data.
- Run one file with `vendor/bin/phpunit tests/Unit/AssetHelperTest.php`; add `--filter testMethodName` to isolate one method.
- `phpunit.xml` is ignored and is the local override. `phpunit.xml.dist` defaults to `localhost:3307` with blank credentials, while CI supplies `127.0.0.1:3306`; configure the local file or environment rather than changing the distributed defaults for one machine.
- Integration setup creates only missing tables from `database/schema.sql`, then wraps each test in a transaction. Recreate affected test tables/database after changing an existing table definition or tests may run against stale structure.

## Generated And Indexed Assets

- Edit CSS only under `public/css/src/`. Local mode loads those files directly, but production serves `public/css/app.min.css`; run `composer build:css` and include the generated file with CSS changes.
- `npm run build` copies locked GSAP/Lenis files into `public/js/vendor/`. Include those generated vendor files after dependency changes; CI rebuilds both JS vendor assets and CSS and fails on drift.
- NUMA source knowledge lives in `knowledge/numa/*.md`; its runtime base prompt is `resources/numa/prompts/base.md`. After knowledge changes, `php bin/indexar-numa.php` writes embeddings to `numa_conocimiento` and requires test/sandbox DB plus embedding-provider credentials; the automated tests use fakes and do not require an external API.

## Agent Workflow

- Do not create commits. Commits are performed manually by the repository owner after reviewing the implementation.
- Do not implement work outside the explicitly requested scope.
- Do not treat documentation as proof that something is implemented; verify the actual code when implementation state matters.
- Preserve the existing architecture when it already satisfies the requirement. Avoid introducing new abstractions, dependencies, or infrastructure without a concrete need.
- Prefer the smallest change that fully satisfies the requested task without sacrificing correctness or maintainability.

## Task Completion

- Run verification appropriate to the modified area before considering the task complete.
- Prefer targeted tests during implementation, then run the broader relevant suite when appropriate.
- Report what was changed and which verification commands were executed.
- Do not claim a tool, dependency, test suite, or service is unavailable without checking the r-...epository configuration first.


## Codebase exploration

- MUST use `codebase-memory-mcp` first for codebase discovery, symbol lookup,
  references, architecture exploration, and targeted code retrieval.
- Use native `Glob`, `Grep`, and `Read` when the MCP does not provide
  sufficient or current information, or when exact file contents are needed
  before editing or verification.
- After locating relevant code through the MCP, inspect only the necessary
  files or ranges with native tools.
- Avoid reading entire files when targeted MCP retrieval is sufficient.