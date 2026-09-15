# COP4331 LAMP Contact App

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [Composer](https://getcomposer.org/)
- PHP 8.1+ installed locally (only needed to run tests or generate password hashes outside Docker)

## First-time setup

```bash
git clone https://github.com/ESiegman/COP4331---LAMP-Contact-App.git
cd COP4331---LAMP-Contact-App
cp .env.example .env
```

Edit `.env`, set `DB_PASS` and `DB_ROOT_PASS`.

```bash
composer install
docker compose up --build
```

| Service | URL | Notes |
|---|---|---|
| `web` | http://localhost:8080 | PHP API |
| `db` | localhost:3306 | `ContactsAppDB` (dev), `ContactsAppDB_test` (test) |
| `phpmyadmin` | http://localhost:8081 | server `db`, user `ContactsAppUser`, password = your `DB_PASS` |

```bash
curl "http://localhost:8080/index.php?ping=1"
```

Day-to-day:

```bash
docker compose up
```

After changing `Dockerfile`, `docker-compose.yml`, or anything in `docker/mysql/`:

```bash
docker compose down -v
docker compose up --build
```

## Running tests locally

```bash
composer test:local
```

## Database schema changes

```bash
mysqldump --no-data --no-tablespaces -u ContactsAppUser -p ContactsAppDB > db/schema.sql
```

Update `docker/mysql/01-init-schema.sql` and `docker/mysql/03-init-test-schema.sql` to match, then:

```bash
docker compose down -v
docker compose up --build
```

## API reference

[`api/README.md`](api/README.md)

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
- 1 teammate approval

Squash and merge, then delete the branch.

## CI/CD

- `.github/workflows/php-tests.yml`: runs on every PR
- `.github/workflows/deploy.yml`: runs on every push to `main`. Deploys to droplet.
