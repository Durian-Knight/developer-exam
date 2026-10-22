# Written answers

## 5.1 Performance & Security

### 1. Making `GET /api/tasks` fast with 1,000,000+ tasks

- **Always paginate, never `->get()`.** The endpoint uses `paginate()` with `per_page` capped at 100 (`ListTasksRequest`). For very deep pages or infinite scroll I'd switch to **cursor/keyset pagination** (`cursorPaginate()` → `WHERE id < ? ORDER BY id DESC LIMIT n`), which stays O(limit) instead of O(offset) and skips the extra `COUNT(*)`.
- **Composite indexes that match the queries.** The migration adds `(assigned_to, status, id)` for "my tasks [+ status], newest first" and `(status, id)` for the admin status filter, plus `due_date` for sorting. I'd verify each common query with `EXPLAIN` and make sure there's no `filesort`/full scan.
- **Eager loading, only needed columns.** `with('assignee:id,name,email,role')` = 2 queries per page instead of 1 + N. `Model::shouldBeStrict()` in dev makes any accidental lazy load throw.
- **Search that scales.** `LIKE '%term%'` can't use a B-tree index. For real usage: a MySQL `FULLTEXT` index on `title` (`whereFullText`), or Laravel Scout with Meilisearch/OpenSearch.
- **Cheap counts.** `COUNT(*)` over millions of rows is the slow part of offset pagination. Options: `simplePaginate()` (no total), cursor pagination, or caching the total per filter combination for a short TTL.
- **Caching.** Cache hot, rarely changing lists (e.g. admin dashboard, first page per user) in Redis with tags/versioned keys, invalidated in a `Task` model observer on save/delete. Also cache config/routes/events in production (`php artisan optimize`) and enable OPcache.
- **Lean JSON.** Return only what the table needs (API Resources), enable gzip/brotli at Apache, and let the client use `per_page` sensibly.
- **Data lifecycle.** Soft-deleted rows add up; periodically prune them (`Prunable`) or archive old `done` tasks into a separate table/partition. At larger scale, read replicas for list queries (`'read' => [...]` in the DB config).
- **Measure first.** Telescope/Debugbar locally, slow query log + APM (e.g. Sentry/New Relic) in production, and a load test (k6) before/after each change.

### 2. Securing the Laravel + SPA setup

Threats → what I rely on:

- **SQL injection** → Eloquent/query builder with bound parameters everywhere; no raw SQL with user input. Sort column/direction are **whitelisted** (`Rule::in`) because column names can't be bound. LIKE wildcards in search are escaped.
- **Mass assignment** → explicit `$fillable` + only `$request->validated()` data is ever passed to `create()/update()`. `role` can only be set through the admin-only user endpoint.
- **Broken access control (IDOR)** → `TaskPolicy` on every single-task action + `Task::visibleTo()` query scope on the list; regular users can't assign tasks to others (validated in the Form Request). Feature tests cover "user B can't read/update/delete user A's task".
- **Authentication / token leakage** → Sanctum personal access tokens (stored hashed in the DB), **expiry** (`SANCTUM_TOKEN_EXPIRATION`), revoke on logout, generic "credentials incorrect" message (no user enumeration), login **rate limiting** (5/min per email+IP) and a global API rate limit. Passwords hashed with bcrypt (`'password' => 'hashed'` cast).
  - Trade-off: the token is in `localStorage`, so XSS could steal it. For a production internal app I'd prefer **Sanctum SPA (cookie) auth**: HttpOnly + Secure + SameSite cookies, with Laravel's **CSRF** protection (`/sanctum/csrf-cookie` + `X-XSRF-TOKEN`). Bearer tokens themselves aren't vulnerable to CSRF.
- **XSS** → API returns JSON only (`application/json`, `X-Content-Type-Options: nosniff`), Vue escapes `{{ }}` output by default and the app never uses `v-html` with user data. Add a **Content-Security-Policy** header at Apache.
- **Transport** → HTTPS everywhere (TLS at Apache/load balancer), HSTS, `APP_DEBUG=false` in production so stack traces/env never leak, `.env` outside the web root (Apache `DocumentRoot` is `backend/public`).
- **CORS** → restricted to the SPA origin(s) via `FRONTEND_URL` (in the Docker setup SPA + API share one origin, so CORS isn't even needed).
- **Validation everywhere** → Form Requests for all inputs (types, enums, max lengths, `exists` for FKs); DB constraints (FK, enum, NOT NULL) as a second line of defence.
- **Dependencies & ops** → `composer audit` / `npm audit` in CI, keep Laravel/PHP patched, least-privilege DB user, logs without secrets, backups.

## 5.2 TypeScript / Vue 3

### 1. Benefits of TypeScript in a Vue 3 SPA

- **API contracts in one place** (`src/types`): if the backend renames `assigned_to`, the compiler shows every component that breaks instead of finding it at runtime.
- **Typed props / emits / stores**: `defineProps<{...}>()` and `defineEmits<{...}>()` catch wrong prop types and wrong event payloads at build time (`vue-tsc` in CI).
- **Safer refactoring and better IDE support** (autocomplete, go-to-definition, rename across `.vue` files with Volar).
- **Fewer null/undefined bugs** with `strict` mode (e.g. `task.assignee?.name`).
- **Self-documenting code** for the team — the types explain what a composable/store returns.
- Vue 3's Composition API was designed with type inference in mind, so the overhead is small.

### 2. Strongly typed props with `<script setup lang="ts">`

```vue
<script setup lang="ts">
import { computed } from 'vue'

type TaskStatus = 'todo' | 'in_progress' | 'done'

interface Props {
  title: string
  status: TaskStatus
  dueDate?: string | null   // optional
  tags?: string[]
}

// Type-based declaration + defaults for optional props
const props = withDefaults(defineProps<Props>(), {
  dueDate: null,
  tags: () => [],
})

// Typed events: name -> payload tuple
const emit = defineEmits<{
  statusChange: [status: TaskStatus]
  delete: []
}>()

const isDone = computed(() => props.status === 'done')
</script>

<template>
  <div :class="{ 'text-decoration-line-through': isDone }">
    {{ props.title }} — due {{ props.dueDate ?? 'n/a' }}
    <button @click="emit('statusChange', 'done')">Mark done</button>
  </div>
</template>
```

`<TaskCard title="x" status="finished" />` is now a compile-time error. A real example in this repo: `frontend/src/components/TaskForm.vue`.

---

## 6.1 (Bonus) Apache 2.4 – serving Laravel 11 + the Vue SPA

Working config: [`docker/apache-vhost.conf`](docker/apache-vhost.conf). Key points:

- **Laravel:** `DocumentRoot` must be `backend/public` (never the project root, so `.env`, `vendor/` and `storage/` aren't web-accessible). Enable `mod_rewrite`; everything that isn't a real file goes to `index.php` (that's what `backend/public/.htaccess` does). In the vhost I use `AllowOverride None` and put the rules in the vhost itself (faster, no `.htaccess` lookups).
- **Authorization header:** Apache may strip it before PHP — `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` (or `CGIPassAuth On` for FPM) so Sanctum sees the Bearer token.
- **SPA on the same domain:** `/api/*` → Laravel front controller, `/assets/*` → `Alias` to the Vite `dist/assets` folder, **everything else → `dist/index.html`**. That last rule is the **history-mode fallback**: Apache has no file at `/tasks/123`, so it returns `index.html` and Vue Router resolves the route client-side.
- **Alternative (two subdomains):** `api.example.com` → `DocumentRoot backend/public`; `app.example.com` → `DocumentRoot frontend/dist` with
  ```apache
  RewriteEngine On
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^ /index.html [L]
  ```
  (also shipped as `frontend/public/.htaccess`), plus CORS for the SPA origin on the API.
- Caching: long `Cache-Control` for fingerprinted `/assets/*`, `no-cache` for `index.html`; `mod_deflate` for JSON/JS/CSS; TLS via `mod_ssl` or a reverse proxy.

## 6.2 (Bonus) Docker / CI/CD

**Docker** ([`docker/Dockerfile`](docker/Dockerfile), [`docker-compose.yml`](docker-compose.yml)):

- `app`: multi-stage build → Node builds the SPA, Composer installs `--no-dev` vendors, final image is `php:8.3-apache` with `pdo_mysql` + OPcache, the vhost above, and an entrypoint that runs migrations (and optional demo seeding).
- `db`: `mysql:8.0` with a named volume and a healthcheck; `app` waits for `service_healthy`.
- Config is 100% environment variables (12-factor); secrets would come from the orchestrator / CI secrets in real deployments.

**CI pipeline** ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)):

1. **Backend:** checkout → setup PHP 8.3 → `composer install` (cached) → `pint --test` (lint) → `php artisan test` (feature tests on SQLite in-memory; could also run against a MySQL service container).
2. **Frontend:** `npm ci` → `vue-tsc` type-check + `vite build`.
3. **Security:** `composer audit`, `npm audit --omit=dev` (optional gate).
4. **Package:** build the Docker image, tag with commit SHA, push to a registry.
5. **Deploy** (on `main`): pull the image on the server/cluster, run `php artisan migrate --force`, `php artisan optimize`, then swap containers (rolling / blue-green), and hit `/up` as a health check before routing traffic.
