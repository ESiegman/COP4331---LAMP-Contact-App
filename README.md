# Contacts Manager — COP4331 LAMP Contact App

A full CRUD personal contacts manager built on the LAMP stack (Linux, Apache, MySQL, PHP). Users register, sign in, and manage their own contacts; admins manage user accounts. The browser talks to a JSON web API over AJAX (`fetch`), and every search runs as a real API call plus SQL query. Contacts are never bulk-loaded into the client.

**Live app:** https://lamp.esiegman.dev
**API reference:** [`api/README.md`](api/README.md)

## Features

**Users**

- Register and sign in from the front page
- See only their own contacts
- Search contacts by name, email, or phone (server-side)
- Add, edit (every field except the ID), and delete contacts
- Mark favorites, filter to favorites only, and sort by any column
- Change their own password

**Admins**

- See and search all users, and sort the list by name, username, role, or status
- View any user's contacts, with search
- Create user and admin accounts
- Disable (never delete) any user, including other admins, and re-enable them
- Reset any user's password

**Added beyond the requirements:** favorites, column sorting (contacts and users), favorites-only filter, account re-enable, and a self-service change-password flow.

### Default admin account

The database is seeded with one admin account (`db/seed_root.sql`, and the Docker init script for local dev):

| Login | Name                     | Password       |
| ----- | ------------------------ | -------------- |
| `root` | Application Administrator | `ChangeMe123!` |

Change this password after first login (Admin Panel → Reset password, or My Contacts → Change password).

## Requirements checklist

| Requirement                                        | Where                                                                        |
| -------------------------------------------------- | ---------------------------------------------------------------------------- |
| Admin and User roles with different views          | `Users.Role` enum; `admin.html` vs `contacts.html`; role checks in `AdminController` |
| Front page offers Sign In or Register              | `index.html` (sign-in form, link to `registration.html`)                     |
| Users only see their own contacts                  | `ContactModel::search` is scoped by `User_ID`; other users' IDs return `404` |
| Search contacts                                    | `GET contacts.search` with a SQL `LIKE` query                                |
| Edit all fields except ID; delete                  | `PUT contacts.update`, `DELETE contacts.delete`                              |
| Admin sees and queries all users and their contacts | `GET admin.users.search`, `GET admin.users.contacts`                         |
| Admin disables (not deletes) any user              | `PUT admin.users.disable` sets `Active = 0`; disabled users cannot sign in   |
| Admin changes passwords                            | `PUT admin.users.password`                                                   |
| Admin creates admins                               | `POST admin.users.create` with `Role: "Admin"`                               |
| Seeded `root` admin                                | `db/seed_root.sql`                                                           |
| Passwords hashed and salted                        | PHP `password_hash()` (bcrypt, per-password salt); verified with `password_verify()` |
| JSON over AJAX                                     | `js/client.js` (`apiCall`)                                                   |
| Domain name and HTTPS                              | Deployed on a DigitalOcean droplet behind a domain name. Apache and TLS configuration lives on the server, not in this repo. |

## Tech stack

- **Frontend:** static HTML, vanilla JavaScript, Bootstrap 5.3 and Bootstrap Icons (CDN), custom dark theme in `css/styles.css`
- **API:** PHP 8.2, a small front controller and router (`api/public/index.php`, `api/src/`), PDO with prepared statements, session-based authentication
- **Database:** MySQL 8.0 (`Users` and `Contacts` tables, `ON DELETE CASCADE` from users to contacts)
- **Testing:** PHPUnit 10.5 against a dedicated `ContactsAppDB_test` database; each test runs in a rolled-back transaction
- **Tooling:** Docker Compose (Apache/PHP, MySQL, phpMyAdmin), GitHub Actions (tests on PRs, rsync deploy on `main`)

## Project structure

```
.
├── index.html, registration.html      Sign in / register
├── contacts.html, admin.html          User and admin dashboards
├── css/styles.css                     Theme
├── js/                                client.js (API client), page scripts
├── images/                            Favicon, background, diagrams
├── api/
│   ├── public/index.php               Entry point, routes, CORS
│   ├── src/                           Auth, Controllers, Models, Support, Database
│   └── README.md                      API reference
├── db/                                schema.sql, seed_root.sql, seed_test.sql
├── docker/                            php-apache Dockerfile, MySQL init scripts
├── tests/                             PHPUnit suite
└── .github/workflows/                 php-tests.yml, deploy.yml
```

## First-time setup

Prerequisites:

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [Composer](https://getcomposer.org/)
- PHP 8.1+ installed locally (only needed to run tests or generate password hashes outside Docker)

```bash
git clone https://github.com/ESiegman/COP4331---LAMP-Contact-App.git
cd COP4331---LAMP-Contact-App
cp .env.example .env
```

Edit `.env` and set `DB_PASS` and `DB_ROOT_PASS`, then:

```bash
composer install
docker compose up --build
```

| Service      | URL                   | Notes                                                          |
| ------------ | --------------------- | -------------------------------------------------------------- |
| `web`        | http://localhost:8080 | PHP API (document root is `api/public`)                        |
| `db`         | localhost:3306        | `ContactsAppDB` (dev), `ContactsAppDB_test` (test)             |
| `phpmyadmin` | http://localhost:8081 | server `db`, user `ContactsAppUser`, password = your `DB_PASS` |

Health check:

```bash
curl "http://localhost:8080/index.php?ping=1"
```

### Running the frontend locally

Docker serves only the API. Serve the static pages separately, for example with the VS Code Live Server extension (`http://localhost:5500`). When the page is opened from `localhost` or `127.0.0.1`, `js/client.js` automatically sends API calls to `http://localhost:8080/index.php`, and the API's CORS settings allow the cross-port session cookie.

### Day-to-day

```bash
docker compose up
```

After changing `Dockerfile`, `docker-compose.yml`, or anything in `docker/mysql/`:

```bash
docker compose down -v
docker compose up --build
```

## Running tests

Start the Docker `db` service first, then:

```bash
composer test:local
```

This loads `.env` and runs PHPUnit against `ContactsAppDB_test`. The test database is created by `docker/mysql/02-init-test-db.sql` and `03-init-test-schema.sql`, which also seed `test_admin` and `test_user`.

## Database schema changes

```bash
mysqldump --no-data --no-tablespaces -u ContactsAppUser -p ContactsAppDB > db/schema.sql
```

Then update `docker/mysql/01-init-schema.sql` and `docker/mysql/03-init-test-schema.sql` to match. A fresh dump does not include their seed `INSERT` statements at the bottom (the `root` admin in `01`, the test users in `03`), so keep those when you paste the new schema in. Finally:

```bash
docker compose down -v
docker compose up --build
```

## Deployment

Pushing to `main` runs `.github/workflows/deploy.yml`, which installs production dependencies (`composer install --no-dev`) and syncs three bundles to the droplet over rsync:

| Bundle             | Contents                                   | Destination                      |
| ------------------ | ------------------------------------------ | -------------------------------- |
| Frontend           | HTML pages, `css/`, `js/`, `images/`, favicon | `DEPLOY_PATH` (web root)         |
| API entry point    | `api/public/`                              | `DEPLOY_PATH/api`                |
| App code           | `api/src/`, `vendor/`, Composer files      | `/var/www/contacts-app/` (outside the web root) |

The workflow needs these GitHub secrets: `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_PATH`. It then reloads Apache (non-fatal if that step fails).

On the server, PHP must have `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` in its environment (for example through Apache `SetEnv`). The production database is created from `db/schema.sql` plus `db/seed_root.sql`.

The deployed frontend calls the API at `/api/index.php` on the same domain.

## Branching and merge workflow

```bash
git checkout main
git pull origin main
git checkout -b yourname/short-description
# commit as you go
git push -u origin yourname/short-description
```

Open a PR into `main`. Required to merge:

- `php-tests` CI check passing

Squash and merge, then delete the branch.

## CI/CD

- `.github/workflows/php-tests.yml`: runs on every PR into `main`. Starts MySQL 8.0, loads `db/schema.sql` and `db/seed_test.sql`, then runs PHPUnit.
- `.github/workflows/deploy.yml`: runs on every push to `main`. Deploys to the droplet.

## AI Disclosure

This project was developed with assistance from generative AI tools:

**Claude Code (Anthropic)**

- **Tool**: Claude Code Sonnet 5.5
- **Dates**: September 17 - October 1, 2026
- **Scope**: The API's automated PHPUnit test suite; JavaScript and HTML for the frontend; CSS styling for the web page design; 25 rows of test data used to populate the database; Polishing this README
- **Use**: Code generation and review (tests, frontend markup and scripts, styling) and generating sample data

All AI-generated or AI-edited content was reviewed, tested, and modified by the team to meet the assignment requirements. The final implementation reflects the team's understanding of the concepts.
