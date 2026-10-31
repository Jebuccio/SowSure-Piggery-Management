# SowSure

SowSure is a traditional server-rendered PHP 8 application for tracking sows, breeding history, 114-day expected farrowing dates, and care reminders. It uses Supabase Auth, PostgreSQL, PostgREST, Row Level Security (RLS), PHP sessions, HTML/CSS, and vanilla JavaScript. No Node.js build step is required.

The application follows the supplied SowSure SRS. Inventory, customer management, and unrelated concepts shown in the UI reference were intentionally excluded because they are outside the SRS.

## What is included

- Registration with full name, farm name, unique username, email, and strong password validation.
- Email-or-username login through Supabase Auth.
- Server-side PHP sessions; Auth tokens are never placed in `localStorage`.
- Sow search, status filters, create, detail, edit, and non-destructive archive.
- Breeding history with an authoritative PostgreSQL-generated farrowing date.
- Care scheduling, upcoming/overdue views, editing, and completion.
- Real-data dashboard and latest-record-per-sow gestation schedule.
- Profile settings and English text organized separately for future translation.
- CSRF protection, escaped output, safe errors, secure cookies, and RLS.
- Responsive mobile bottom navigation and desktop sidebar.

## Architecture and important rules

The browser sends forms to PHP. PHP validates them and calls Supabase over HTTPS with cURL. Ordinary database calls use the signed-in user's JWT, so PostgreSQL RLS enforces `auth.uid()` ownership.

The service-role key is used in exactly one server-side method: resolving a normalized username to the account email before calling Supabase Auth. It is never sent to browser JavaScript or HTML. Keep it only in `.env` or your host's secret manager.

PostgreSQL is authoritative for expected farrowing:

```text
expected_farrowing_date = breeding_date + 114 calendar days
```

`expected_farrowing_date` is a stored generated column, so edits recalculate automatically. PHP and JavaScript contain matching calculations only for validation, tests, and previews. Breeding values are `date`, not timestamps, to avoid timezone shifts.

Where a sow has multiple breeding records, dashboard and gestation views use the record with the latest `breeding_date`. Older history remains visible. Gestation status is:

- **Due soon:** expected date is today through 7 days away.
- **On track:** expected date is more than 7 days away.
- **Past expected date:** expected date is before today.

Tag numbers are unique **within one owner account**, not globally across farms.

## 1. Prerequisites

Install:

- PHP 8.1 or newer (PHP 8.3 is recommended).
- PHP cURL and mbstring extensions.
- Git.
- A free Supabase account/project.
- A modern browser.

### Windows

The easiest beginner option is XAMPP. Install XAMPP, open its `php.ini`, ensure these lines are enabled (remove a leading semicolon), then restart Apache:

```ini
extension=curl
extension=mbstring
```

Open Command Prompt and verify:

```powershell
php -v
php -m | findstr /I "curl mbstring"
git --version
```

If `php` is not found, use XAMPP's full path such as `C:\xampp\php\php.exe`, or add `C:\xampp\php` to your Windows `PATH`.

### macOS

In Terminal:

```bash
brew install php git
php -v
php -m | grep -E 'curl|mbstring'
```

### Ubuntu/Debian

In Terminal:

```bash
sudo apt update
sudo apt install php php-cli php-curl php-mbstring git
php -v
php -m | grep -E 'curl|mbstring'
```

## 2. Copy and configure the project

Open a terminal **inside the `sowsure` directory**:

```bash
cd path/to/your/repository/sowsure
cp .env.example .env
```

On Windows Command Prompt use:

```bat
cd C:\path\to\your\repository\sowsure
copy .env.example .env
```

Never commit `.env`. It is ignored by Git.

## 3. Create and configure Supabase

1. In Supabase, create a project and save the database password safely.
2. Open **SQL Editor**.
3. Copy all of `database/schema.sql` into a new query and run it.
4. Copy all of `database/policies.sql` into a second query and run it.
5. Run `database/verify_rls.sql`. Every listed table must show `rowsecurity = true`, and policies/triggers must be listed.
6. Open **Authentication → URL Configuration**.
7. Set the local Site URL to `http://localhost:8000`. Add it to allowed redirect URLs if required.
8. Open **Project Settings → API** and copy:
   - Project URL.
   - Public/anon key.
   - Service-role key (server secret; never share or commit it).
9. Put those values in `.env`:

```dotenv
SUPABASE_URL=https://your-project-ref.supabase.co
SUPABASE_ANON_KEY=your-anon-key
SUPABASE_SERVICE_ROLE_KEY=your-service-role-key
```

Supabase may have email confirmation enabled. SowSure handles both modes:

- If confirmation is disabled, registration creates a session and opens the dashboard.
- If confirmation is enabled, registration tells the user to confirm the email before signing in.

The `on_auth_user_created` trigger creates the profile in the same database transaction as the Auth user. Invalid or duplicate usernames therefore prevent a partial account.

## 4. Run locally

From the **`sowsure` directory**, run:

```bash
php -S localhost:8000 -t public
```

Open <http://localhost:8000>. Stop the server with `Ctrl+C`.

Only `public/` is served. Do not run with the project root as the document root because configuration and internal PHP files should not be web-accessible.

### XAMPP alternative

1. Copy the `sowsure` directory to `C:\xampp\htdocs\sowsure`.
2. For best security, configure an Apache virtual host whose `DocumentRoot` is `C:\xampp\htdocs\sowsure\public`.
3. Set `APP_URL` in `.env` to the URL of that virtual host.
4. Restart Apache in the XAMPP Control Panel.

## 5. Run tests and checks

From the **`sowsure` directory**:

```bash
php tests/run.php
sh tests/security_checks.sh
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

The first command tests date boundaries, leap years, username validation, and password rules. The shell check verifies protected routes and checks that secrets are not referenced by browser assets. The final command syntax-checks every PHP file.

## 6. Manual acceptance checklist

Use two different browsers or one normal and one private window for the two-user checks.

- [ ] Register User A; verify `auth.users` and one matching `profiles` row.
- [ ] If email confirmation is enabled, confirm the email and then sign in.
- [ ] Sign in with User A's email.
- [ ] Sign out, then sign in with User A's username.
- [ ] Try a wrong username, email, and password; all show the same generic failure.
- [ ] Open `/dashboard.php` while signed out; it redirects to login.
- [ ] Create User B. Confirm User B cannot see or update User A's records.
- [ ] Add a sow, search by name/tag, filter status, open it, edit it, and archive it.
- [ ] Add and edit breeding records. Check each expected date is exactly 114 calendar days later.
- [ ] Confirm older breeding history remains after adding a newer record.
- [ ] Add each care category and edit one.
- [ ] Try to create care yesterday; both PHP and PostgreSQL must reject it.
- [ ] Mark care complete; confirm it disappears from Upcoming and appears in Completed.
- [ ] Confirm dashboard totals, overdue care, and schedule use actual records.
- [ ] Check phone and desktop widths.
- [ ] Sign out and verify the old protected page cannot be reopened.
- [ ] Inspect page source and browser storage; no service-role key or Auth token should appear.

### Direct RLS verification

For the strongest test, create User A and User B and use each user's JWT with the Supabase REST API. A request using User B's JWT with a filter for User A's UUID must return no rows, and update attempts must affect zero rows. Never paste JWTs into source files or commit them.

## 7. Common setup errors

**`php: command not found`**  
Install PHP or add its folder to `PATH`. XAMPP users can run `C:\xampp\php\php.exe`.

**`Call to undefined function curl_init()`**  
Enable/install PHP cURL, restart the PHP/Apache process, then check `php -m`.

**“Supabase is not configured”**  
Create `.env` beside `.env.example`; verify the URL and keys have no extra quotes or spaces.

**Registration fails immediately**  
Run `schema.sql` before `policies.sql`. Check Supabase Database logs for the profile trigger. Verify full name, farm name, and username meet constraints.

**Email registration succeeds but login fails**  
Confirm the email if confirmation is enabled. Check spam and the Supabase Authentication email settings.

**Username login fails but email works**  
Set `SUPABASE_SERVICE_ROLE_KEY` in the server environment and restart PHP. Never place that key under `public/`.

**A date is rejected around midnight**  
The business rule uses `Asia/Manila`; ensure `APP_TIMEZONE=Asia/Manila`. PostgreSQL independently evaluates Manila time.

**`401` or session expiry**  
Confirm the anon key/project URL match. The app refreshes short-lived access tokens server-side; after the refresh token or 12-hour absolute session expires, sign in again.

**Duplicate tag or username**  
Tags are unique per farm owner; usernames are globally unique. Choose another value.

## 8. Git and GitHub for beginners

Run these commands from the **repository root** (the folder containing `sowsure`):

```bash
git status
git add sowsure
git commit -m "Build SowSure PHP application"
git branch -M main
git remote add origin https://github.com/YOUR-USERNAME/YOUR-REPOSITORY.git
git push -u origin main
```

If `origin` already exists, do not add it again. Check:

```bash
git remote -v
```

Before every push, verify no `.env` is staged:

```bash
git status
git check-ignore sowsure/.env
```

Never paste real keys into GitHub issues, source files, screenshots, or chat.

## 9. Hosting evaluation (do not deploy yet)

SowSure needs PHP 8, HTTPS, outbound HTTPS/cURL, server-side environment variables, and persistent session support. Supabase remains external, so the PHP host does not need a bundled database.

| Option | PHP approach | HTTPS & secrets | GitHub deploy | Budget considerations |
|---|---|---|---|---|
| Render Web Service | Included `Dockerfile` | Managed HTTPS and environment variables | Yes | Free availability/idle behavior and pricing can change; free services may sleep |
| Koyeb Web Service | Docker | Managed HTTPS and secrets | Yes | Has offered limited starter/free resources; verify current regional limits |
| Shared cPanel host | Native PHP 8 | HTTPS usually included; secret handling varies | Usually manual or Git-based | Often low fixed cost and no sleep; document root must be set to `public/` |
| InfinityFree-style free shared hosting | Native PHP | HTTPS available; environment variables and Git deploy are limited | Usually no | No cost, but weaker secret/deployment workflow makes it a poor fit for a service-role key |

**Preliminary beginner recommendation:** use Render with the included Dockerfile for the clearest GitHub deployment and environment-variable workflow, provided its current free-tier limits are acceptable. If always-on access matters, a low-cost cPanel PHP host that allows a custom document root and server environment variables may be better. Check the provider's current pricing and limits immediately before publishing; hosting offers change.

Do not publish until local tests and the two-user RLS checklist pass. Before any paid purchase or public deployment, get the project owner's approval.

Typical Render steps after approval:

1. Push the repository to a private GitHub repository.
2. Create a new Web Service from the repository.
3. Choose Docker and set the root directory to `sowsure`.
4. Add every `.env.example` variable in the host's Environment/Secrets screen; use real values there, not in Git.
5. Set `APP_ENV=production`, `APP_URL=https://your-hostname`, and keep `APP_TIMEZONE=Asia/Manila`.
6. Deploy and update Supabase Authentication's Site URL/allowed URLs to the HTTPS hostname.
7. Repeat the complete manual checklist in production.

## Backup and safety

Enable Supabase's available backup features for the selected plan and regularly export critical records. Archiving a sow does not delete breeding or care history. SowSure supports farm decisions but does not replace veterinary advice.
