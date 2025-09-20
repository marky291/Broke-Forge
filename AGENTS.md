# Repository Guidelines

## Project Structure & Module Organization
- `app/` Laravel application code (HTTP, Console, Middleware, Domain). Notable: `App/Terminal`, `App/Provision`.
- `resources/js/` React + TypeScript (Inertia pages, layouts, components, hooks). `resources/views/` base Blade layout.
- `routes/` HTTP, console, and feature routes. `config/` app configuration. `database/` migrations, factories, seeders.
- `public/` web root. `tests/` PHPUnit tests (Feature, Unit). `bootstrap/`, `storage/` framework runtime.

## Build, Test, and Development Commands
- Install: `composer install` and `npm ci`.
- Env: `cp .env.example .env && php artisan key:generate` then configure DB; run `php artisan migrate`.
- Dev (app + queue + logs + Vite): `composer dev`.
- Dev with SSR: `composer dev:ssr` (builds SSR and starts Inertia SSR server).
- Frontend only: `npm run dev`. Build assets: `npm run build` or `npm run build:ssr`.
- Tests: `composer test` (clears config, runs `php artisan test`).
- Lint/Format: `npm run lint`, `npm run types`, `npm run format`, PHP: `./vendor/bin/pint`.

## Coding Style & Naming Conventions
- PHP: PSR-12 via Laravel Pint (4-space indentation). Classes `StudlyCase`, methods/vars `camelCase`, config keys `snake_case`.
- JS/TS/React: ESLint + Prettier (2-space indentation). Prefer function components, hooks, and co-locate page-specific components.
- Filenames: TypeScript React components as `kebab-case.tsx` or `PascalCase.tsx` where appropriate.

## Testing Guidelines
- Framework: PHPUnit + Laravel test runner. Place tests under `tests/Feature/...` and `tests/Unit/...` named `*Test.php`.
- Cover controllers, middleware, policies, and critical UI flows via HTTP tests. Keep tests deterministic; use factories.
- Run locally with `composer test`; add tests for new behaviors and bug fixes.

## Commit & Pull Request Guidelines
- Commits: Use Conventional Commits (e.g., `feat:`, `fix:`, `chore:`); imperative mood; focused changes.
- PRs: Include clear description, linked issues, screenshots for UI, and notes on tests/impact. Keep PRs small and scoped.

## Security & Configuration Tips
- Never commit secrets. Manage env in `.env`; review `config/*.php` before deploying. Validate user input; prefer framework helpers.

## Agent-Specific Instructions
- Keep changes minimal and within scope. Follow existing patterns and this guide. Do not alter unrelated files or add licenses.
