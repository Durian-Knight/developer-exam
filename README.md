# Team Task Manager

Full-stack test exercise — **Laravel 11 (PHP 8.3, MySQL 8)** REST API + **Vue 3 / Vuetify 3 / TypeScript** SPA.

Author: Cao Anh Khoa

```
team-task-manager/
├── backend/        Laravel 11 API (Sanctum tokens, policies, form requests, API resources, feature tests)
├── frontend/       Vue 3 + Vuetify 3 + TypeScript SPA (Vite, Vue Router, Pinia, axios)
├── docker/         Dockerfile (PHP 8.3 + Apache 2.4), Apache vhost, entrypoint
├── docker-compose.yml
├── .github/workflows/ci.yml
└── ANSWERS.md      Written answers (performance, security, TypeScript, Apache, Docker/CI)
```

## Quick start (Docker — easiest)

```bash
docker compose up --build
```

Open **http://localhost:8080** (SPA and API on the same origin; API under `/api`).
Migrations and demo data run automatically.

## Demo accounts

All passwords are `password`.

| Email               | Role  | Tasks seeded |
|---------------------|-------|--------------|
| admin@example.com   | admin | 5            |
| alice@example.com   | user  | 20           |
| bob@example.com     | user  | 15           |

## Run locally (without Docker)

Requirements: PHP 8.3 (`pdo_mysql`, `pdo_sqlite`), Composer 2, MySQL 8, Node 20+.

### Backend

```bash
cd backend
composer install
cp .env.example .env              # set DB_* for your MySQL
php artisan key:generate
php artisan migrate --seed
php artisan serve                 # http://localhost:8000
```

Run the tests (SQLite in-memory, no MySQL needed):

```bash
php artisan test
```

> Tip: no MySQL handy? Set `DB_CONNECTION=sqlite` in `.env`, `touch database/database.sqlite`, then migrate.

### Frontend

```bash
cd frontend
npm install
npm run dev                       # http://localhost:5173 (proxies /api -> http://localhost:8000)
npm run build                     # vue-tsc type-check + production build into dist/
```

## API

All endpoints are under `/api` and return JSON. Authenticated endpoints need `Authorization: Bearer <token>`.

| Method | Endpoint           | Who          | Notes |
|--------|--------------------|--------------|-------|
| POST   | `/login`           | public       | `{email, password}` → `{token, token_type, user}`. Rate-limited (5/min). |
| POST   | `/logout`          | auth         | Revokes the current token → `204` |
| GET    | `/me`              | auth         | Current user |
| GET    | `/tasks`           | auth         | Paginated. Query: `status`, `assigned_to`, `search` (title), `sort_by` (`title,status,due_date,created_at,id`), `sort_dir`, `page`, `per_page` (≤100). Users only get their own tasks. |
| POST   | `/tasks`           | auth         | `title*`, `description`, `status`, `due_date` (`YYYY-MM-DD`), `assigned_to` → `201` |
| GET    | `/tasks/{id}`      | owner/admin  | |
| PUT    | `/tasks/{id}`      | owner/admin  | Partial updates allowed (any subset of the fields) |
| DELETE | `/tasks/{id}`      | owner/admin  | **Soft delete** → `204` |
| GET    | `/users`           | auth         | Admin: all users. User: only themselves (for the assignee dropdown). |
| POST   | `/users`           | admin        | `name, email, password, role` → `201` |

Status codes: `200/201/204` success · `401` no/invalid token · `403` not allowed · `404` not found (`{"message": "Resource not found."}`) · `422` validation (`{"message", "errors": {field: [..]}}`) · `429` rate-limited.

Example list response:

```json
{
  "data": [{ "id": 12, "title": "Write report", "description": null, "status": "in_progress",
             "due_date": "2026-11-01", "assigned_to": 2,
             "assignee": { "id": 2, "name": "Alice Nguyen", "email": "alice@example.com", "role": "user" },
             "created_at": "...", "updated_at": "..." }],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 40, "from": 1, "to": 15 }
}
```

## Design decisions & assumptions

**Data model**
- `status` and `role` are PHP backed enums (`App\Enums`) used in three places: MySQL `ENUM` column, Eloquent cast, and `Rule::enum()` validation — one source of truth.
- `tasks.assigned_to` is a FK to `users.id` with `RESTRICT` on delete (a user with tasks can't be removed by accident; reassign first).
- Composite indexes `(assigned_to, status, id)` and `(status, id)` match the list endpoint's filters + default sort.
- **Soft deletes** for tasks: deleted rows are kept (`deleted_at`) for audit/restore and are invisible to the API (404).

**Authorization**
- "Own task" = a task **assigned to** the user (the spec has no `created_by`). A regular user can create tasks only for themselves (`assigned_to` defaults to / must be their id), and can view/update/delete only their tasks.
- Admin bypasses checks via `TaskPolicy::before()`. The list endpoint is scoped with `Task::visibleTo($user)`, so a user passing `?assigned_to=<someone else>` just gets an empty list.
- User management is limited to list + create (admin), as allowed by the spec.

**Auth**
- Sanctum **personal access tokens** (Bearer). Simple for an SPA on any origin and easy to test with curl/Postman. Tokens expire after `SANCTUM_TOKEN_EXPIRATION` minutes (default 12h). For production I'd consider Sanctum's cookie-based SPA auth — see `ANSWERS.md`.

**API style**
- Form Requests for every input (`ListTasksRequest` also validates/whitelists query params), API Resources for output, `ForceJsonResponse` middleware + exception config so errors are always JSON.
- `PUT /tasks/{id}` accepts partial payloads (`sometimes` rules) — practical for the SPA (e.g. just changing status).

**Frontend**
- Server-side pagination, sorting, status filter and debounced title search with `v-data-table-server`; admins also get an assignee filter. Stale responses are ignored if filters change quickly.
- `TaskForm.vue` is one component for create (dialog on the list page) and edit (`/tasks/:id` page); server `422` errors are shown under the matching field, other errors in an alert; success via a global snackbar.
- `src/api/http.ts` is the only place that talks to axios: it adds the token, normalises errors, and on any `401` clears the session and redirects to `/login`.
- Route guards: `/tasks`, `/tasks/:id` require login; `/users` requires admin; `/login` redirects away if already logged in. App bar shows the user's name and role.
- Bonus: status chips (todo grey / in progress blue / done green), overdue dates highlighted in red, admin Users page.

## Notes

- `composer.lock` / `package-lock.json` are committed for reproducible builds; CI and Docker use `npm ci`.
- Code style is checked with `vendor/bin/pint --test` in CI.
- Tests use SQLite in-memory, so PHP needs the `pdo_sqlite` extension (e.g. `sudo apt install php8.3-sqlite3`). Alternatively run them against MySQL:
  `DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=testing DB_USERNAME=... DB_PASSWORD=... php artisan test`
