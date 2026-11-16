# Contributing

Issues and pull requests are welcome at
[odiseoteam/sulu-site-assistant-bundle](https://github.com/odiseoteam/sulu-site-assistant-bundle).

## The test application

The bundle is developed against a real Sulu site: `tests/Application`, a kernel on Sulu's test
kernel with one webspace in English and Spanish, pages, articles and the assistant. With Docker:

```bash
make init          # PHP and MySQL containers, composer and npm install, the widget's build
make up / make down
make console c="debug:router" context=website
```

The model is never called in tests: the test application scripts its answers
(`tests/Application/Provider/ScriptedModelProvider.php`), and each test queues the calls it
expects. The suites build the bundle's tables from its migrations and fail when they do not match
the mapping; every test runs in a transaction that is rolled back.

To chat with a real model, set in `tests/Application/.env.local`:

```bash
TEST_APP_MODEL_PLATFORM=anthropic
ANTHROPIC_API_KEY=...
```

## Running the checks

```bash
make static        # php-cs-fixer, PHPStan (level max), Deptrac and the Composer dependency analyser
make phpunit       # unit, integration and functional suites
make vitest        # the widget's JavaScript
make assets        # rebuild public/widget.js and public/widget.css after changing assets/widget
make ci            # all of the above
```

PostgreSQL: `docker compose --profile postgres up -d postgres` and run the suites with
`DATABASE_URL=postgresql://postgres:postgres@postgres:5432/site_assistant?serverVersion=16`.

## What CI enforces

PHP 8.2, 8.3 and 8.4 on MySQL 8.4, plus a PostgreSQL 16 job, with:

- The containers of both contexts linted, the mapping in sync with the migrations.
- php-cs-fixer, PHPStan at level max, Deptrac and the Composer dependency analyser.
- The widget's Vitest suite, and `public/` built from the current sources.

## Conventions

- Comments explain **why**, not what.
- The capabilities only see ports, models, the site's context and the core; Deptrac enforces it.
- Texts a visitor reads go through the translator, in English and Spanish. Tool descriptions,
  prompts and skills are read by the model: changing them changes how it behaves, so run the evals.
- The admin's views are React components compiled by the host's admin build: they import only
  from `sulu-admin-bundle`, `react` and `mobx`.
