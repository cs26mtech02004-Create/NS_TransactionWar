# SecurePay — Secure PHP Banking Application

A full-stack banking web application built with PHP 8.2, MySQL 8, and Apache. Runs in Docker. Themed after retro Indian ATM interfaces (SBI/PNB era) with a clean phosphor-green terminal aesthetic.

---

## Feature Checklist

- [x] User registration with bcrypt password hashing
- [x] Login with rate limiting and timing-safe comparison
- [x] Session management with secure cookie flags
- [x] CSRF protection on all state-changing forms
- [x] Profile editing — name, bio, email, profile image
- [x] Secure image upload stored outside webroot
- [x] User search by username (includes own account)
- [x] Money transfers by username (internal IDs never exposed)
- [x] Transaction history with pagination
- [x] Activity logging to file + database
- [x] Admin panel with log viewer and filtering
- [x] Docker deployment with hardened containers
- [x] Random non-sequential public user IDs
- [x] Password visibility toggle on all password fields
- [x] Custom confirm modal (no browser alert dialogs)
- [x] POST-based logout with CSRF protection
- [x] Auto-redirect from `/` to login or dashboard

---

## Directory Structure

```
securepay/
├── .env                          ← Your secrets (never commit)
├── .env.example                  ← Template — copy to .env
├── .gitignore
├── .dockerignore
├── Dockerfile
├── docker-compose.yml
├── init.sql                      ← Database schema + grants
├── setup.sh                      ← One-command startup
├── index.php                     ← Redirects / → login or dashboard
│
├── docker/
│   ├── vhost.conf                ← Hardened Apache virtual host
│   └── security.ini              ← PHP hardening settings
│
├── config/
│   ├── db.php                    ← PDO connection (env vars only)
│   └── session.php               ← Secure session config + helpers
│
├── includes/
│   ├── auth_guard.php            ← Protects pages — redirects guests
│   ├── csrf.php                  ← Token generation and validation
│   ├── security_headers.php      ← HTTP security headers
│   ├── header.php                ← Shared HTML head + nav bar
│   ├── footer.php                ← Closes HTML, loads script.js
│   ├── logger.php                ← Activity logging (file + DB)
│   ├── upload_handler.php        ← Secure image upload validation
│   └── transfer_handler.php      ← Atomic money transfer logic
│
├── assets/
│   ├── style.css                 ← Retro ATM theme
│   └── script.js                 ← Password toggle, modal, counters
│
├── index.php
├── register.php
├── login.php
├── logout.php
├── dashboard.php
├── profile.php
├── profile_view.php
├── serve_image.php
├── search.php
├── transfer.php
├── transaction_history.php
├── admin_login.php
├── admin_logs.php
│
├── logs/                         ← Created automatically by Docker
└── private_uploads/              ← Created automatically by Docker
```

---

## Database Schema

### users
| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | Internal PK — never shown to users |
| public_id | VARCHAR(12) UNIQUE | 12-char hex, shown to users, used in URLs |
| username | VARCHAR(30) UNIQUE | Permanent, alphanumeric + underscore |
| email | VARCHAR(254) UNIQUE | |
| password_hash | VARCHAR(255) | bcrypt cost 12 |
| balance | DECIMAL(12,2) DEFAULT 100.00 | CHECK >= 0 |
| full_name | VARCHAR(100) NULL | |
| bio | TEXT NULL | |
| profile_image | VARCHAR(64) NULL | Filename only, not path |
| created_at | DATETIME | |
| updated_at | DATETIME | ON UPDATE CURRENT_TIMESTAMP |

### transactions
| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | |
| sender_id | INT UNSIGNED FK→users | RESTRICT on delete |
| receiver_id | INT UNSIGNED FK→users | RESTRICT on delete |
| amount | DECIMAL(12,2) | CHECK > 0 |
| comment | VARCHAR(500) NULL | |
| created_at | DATETIME | |

Constraints: `sender_id != receiver_id`

### login_attempts
Tracks failed logins per IP. Rate limit: 5 failures per IP per 15 minutes.

### activity_log
One row per page visit. Stores username, page, IP, timestamp.

### admins
Separate table from users. Admin credentials do not share the users table.

---

## Security Architecture

### Authentication

| Threat | Defence |
|--------|---------|
| Brute force | 5 attempts per IP per 15 min, then lockout |
| Username enumeration via timing | Dummy hash compared when user not found |
| Username enumeration via message | Single generic "Invalid credentials" message |
| Session fixation | `session_regenerate_id(true)` on login |
| Session hijacking | httponly + samesite=Strict cookie flags |
| CSRF on logout | Logout is POST-only with CSRF token |

### Passwords

- `password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12])`
- Minimum 8 chars, maximum 72 (bcrypt truncation limit), requires uppercase + number
- Visibility toggle is client-side only — server never sees the toggle state

### CSRF Protection

- `bin2hex(random_bytes(32))` token per session
- `hash_equals()` comparison (timing-safe)
- Single-use: regenerated after each validation
- All POST forms include `<?= csrf_input() ?>`
- GET requests (search) are read-only and excluded

### User IDs

| Old approach | Problem | New approach |
|---|---|---|
| Sequential INT (1, 2, 3…) | Attacker enumerates all accounts by incrementing | `bin2hex(random_bytes(6))` = 12-char hex |

- Internal `id` (INT) is used only for database foreign keys and JOINs — stays on the server
- `public_id` is used in all URLs and user-facing interfaces
- Transfer form accepts **username** — internal IDs never touch the browser

### Money Transfers

1. Sender ID from `$_SESSION['user_id']` — never from form input
2. Recipient resolved by username server-side
3. `BEGIN TRANSACTION` wraps all three operations
4. `SELECT ... FOR UPDATE` locks sender row (prevents race/double-spend)
5. `UPDATE ... WHERE balance >= amount` — atomic insufficient-funds check
6. `ROLLBACK` on any failure
7. Transfer to self explicitly rejected

### Image Upload

All checks in `includes/upload_handler.php`:

1. `$_FILES['error'] === UPLOAD_ERR_OK`
2. `filesize($tmp_name)` ≤ 2MB (not `$_FILES['size']` which can be forged)
3. `finfo(FILEINFO_MIME_TYPE)` on actual file bytes — not the filename
4. Extension whitelisted against MIME type
5. `getimagesize()` validates it is a real image
6. Random filename: `bin2hex(random_bytes(16)) . '.' . $ext`
7. Stored in `/var/www/private/uploads/` — outside Apache webroot
8. `chmod($path, 0640)` after move
9. Old image deleted on new upload

### Session Configuration (`config/session.php`)

```
session.cookie_httponly = 1     JS cannot read the session cookie
session.cookie_samesite = Strict  Cookie not sent on cross-origin requests
session.cookie_secure   = 0     Set to 1 on HTTPS production
session.use_strict_mode = 1     Server rejects unknown session IDs
session.gc_maxlifetime  = 1800  30-minute idle timeout
```

### HTTP Security Headers

Set in `includes/security_headers.php` and duplicated at Apache level in `vhost.conf`:

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; object-src 'none'; frame-ancestors 'none'
Cache-Control: no-store, no-cache, must-revalidate  (on all PHP/HTML)
```

### Apache Hardening (`docker/vhost.conf`)

- `DirectoryIndex login.php` — visiting `/` lands on login
- `Options -Indexes -FollowSymLinks` everywhere
- `AllowOverride None` on `config/` and `includes/` — even if `.htaccess` is deleted, these dirs stay blocked at vhost level
- `Require all denied` on `config/`, `includes/`, `/var/www/private`

### PHP Hardening (`docker/security.ini`)

```
expose_php         = Off
display_errors     = Off
log_errors         = On
allow_url_fopen    = Off
allow_url_include  = Off
open_basedir       = /var/www/html:/var/www/private:/tmp
disable_functions  = exec,passthru,shell_exec,system,proc_open,...
```

### Container Security (`docker-compose.yml`)

```yaml
security_opt:
  - no-new-privileges:true
cap_drop:
  - ALL
cap_add:
  - NET_BIND_SERVICE      # app container only (port 80)
```

MySQL container adds `CHOWN`, `DAC_OVERRIDE`, `SETGID`, `SETUID` — minimum required.
MySQL has **no `ports:` mapping** — unreachable from outside Docker.

---

## Running the Application

### 1. Create the env file

```bash
cp .env.example .env
# Edit .env — set DB_PASS and MYSQL_ROOT_PASSWORD to strong random values
# openssl rand -base64 32
```

`.env` fields:

```
DB_NAME=securepay
DB_HOST=db
DB_PORT=3306
DB_USER=spuser
DB_PASS=<strong password>
MYSQL_ROOT_PASSWORD=<different strong password>
```

### 2. Start

```bash
chmod +x setup.sh && ./setup.sh
# or:
docker compose up --build -d
```

### 3. First-time database reset

If you are upgrading from a previous version (schema changed — `public_id` column added):

```bash
docker compose down -v   # WARNING: deletes all data
docker compose up --build -d
```

### 4. Create admin account

```bash
# Generate bcrypt hash
docker exec securepay_app php -r \
  "echo password_hash('YourAdminPassword!', PASSWORD_BCRYPT, ['cost'=>12]);"

# Insert into admins table
docker exec -it securepay_db mysql -u root -p
# USE securepay;
# INSERT INTO admins (username, password_hash) VALUES ('admin', '<hash>');
```

### 5. Access

| URL | Page |
|-----|------|
| http://localhost:8080/ | Auto-redirects to login |
| http://localhost:8080/register.php | Create account |
| http://localhost:8080/login.php | Sign in |
| http://localhost:8080/dashboard.php | Balance + recent transactions |
| http://localhost:8080/profile.php | Edit profile + upload avatar |
| http://localhost:8080/search.php | Find users |
| http://localhost:8080/transfer.php | Send money |
| http://localhost:8080/transaction_history.php | Full history |
| http://localhost:8080/admin_login.php | Admin sign in |
| http://localhost:8080/admin_logs.php | Activity log viewer |

### 6. Stop

```bash
docker compose down        # keeps database
docker compose down -v     # wipes database
```

---

## File Reference

### config/db.php
PDO connection. `ATTR_EMULATE_PREPARES => false` forces true prepared statements. `ERRMODE_EXCEPTION` so errors throw rather than silently fail. Credentials from `getenv()` only.

### config/session.php
Sets all session security flags before `session_start()`. Provides `regenerate_session()` (called on login) and `destroy_session()` (three-step: clear array + delete server file + expire cookie).

### includes/auth_guard.php
One-liner protection for any page. Saves the attempted URL to `$_SESSION['redirect_after_login']` so the user is returned there after authenticating.

### includes/csrf.php
`generate_csrf_token()` — creates token and stores in session. `csrf_input()` — outputs hidden input field. `verify_csrf_token()` — validates with `hash_equals()`, dies on failure. Token is single-use.

### includes/header.php
Sends security headers, logs the page visit, renders nav bar. Logout is a `<form method="POST">` button styled as a nav link — not an anchor tag. This prevents the stale-token problem that broken the old `?csrf_token=` GET approach.

### includes/transfer_handler.php
`process_transfer($pdo, $sender_id, $post)`. Accepts `$post['to_username']`, resolves to internal ID with a database lookup. Wraps the three-step transfer (deduct + credit + log) in a transaction with `FOR UPDATE` row locking.

### includes/upload_handler.php
`handle_profile_upload($file, $old_filename)`. Five-layer validation. Stores files in `/var/www/private/uploads/` which Apache cannot serve. Returns `['success', 'filename', 'error']`.

### includes/logger.php
`log_activity($pdo)`. Called automatically from `header.php` on every page load. Sanitises log values (strips newlines, pipes, null bytes). Rotates at 10MB. Writes to both file and `activity_log` DB table.

### index.php
Single-purpose: checks session, redirects to `/dashboard.php` or `/login.php`. Ensures visiting the root URL always shows something useful.

### register.php
Generates `public_id` with `bin2hex(random_bytes(6))` — collision-checked before insert. Validates username regex, email `filter_var`, password rules. Sets balance to `100.00` in INSERT — never from user input.

### login.php
Rate-limits by `REMOTE_ADDR` (not X-Forwarded-For). Compares dummy hash when user not found (timing attack prevention). Calls `regenerate_session()` on success.

### logout.php
POST-only. Validates CSRF token from `$_POST`. Calls `destroy_session()`. GET requests are silently redirected — logout via URL bar does nothing.

### search.php
Username LIKE search. `addcslashes($term, '%_')` prevents wildcard injection. Results limited to 20. Includes own account in results (marked "(you)"). SEND button passes username, not ID.

### transfer.php
Shows confirm modal before submitting (no browser `alert()`). Recipient field is username text input. Balance displayed from fresh DB query.

### profile_view.php
URL parameter is `?id=public_id` (12-char hex). Validated with regex before use. Redirects to own `profile.php` if viewing self.

### serve_image.php
Proxy for private uploads. Auth-gated. Validates filename against `/^[a-f0-9]{32}\.(jpg|jpeg|png|gif|webp)$/`. Streams file with correct `Content-Type`. Sends `X-Content-Type-Options: nosniff`.

### admin_logs.php
Separate `$_SESSION['is_admin']` check — admin session is independent of user session. Dynamic WHERE with bound parameters. All output escaped. Raw log tail: last 200 lines via `SplFileObject`.

---

## Test Checklist

### Registration
- [ ] Register with valid data → success, Rs. 100 credited, redirected to login
- [ ] Register same username again → generic "already exists" error
- [ ] Register same email again → same generic error (no info leakage)
- [ ] Submit with weak password (no uppercase / no number / < 8 chars) → rejected
- [ ] Submit with mismatched passwords → rejected
- [ ] Check `users` table — `public_id` is 12 hex chars, not sequential integer

### Login
- [ ] Login with correct credentials → dashboard
- [ ] Login with wrong password → "Invalid credentials" (no mention of which field)
- [ ] Login with unknown username → same generic error, same response time
- [ ] Fail login 5 times from same IP → 15-minute lockout message
- [ ] After lockout, correct credentials still blocked → lockout enforced

### Password Visibility
- [ ] Eye button on password field → toggles type between password/text
- [ ] Eye button on confirm field → same
- [ ] Button works on login page and register page

### Logout
- [ ] Click LOGOUT button → session destroyed, redirected to login
- [ ] Press browser Back after logout → sees login page (cache headers working)
- [ ] Visit `/logout.php` directly in URL bar → redirected, NOT logged out (GET ignored)
- [ ] Only one LOGOUT button visible in nav

### Root Redirect
- [ ] Visit http://localhost:8080/ when not logged in → login page
- [ ] Visit http://localhost:8080/ when logged in → dashboard

### Profile
- [ ] Edit full name and bio → saved, reflected immediately
- [ ] Upload valid JPG/PNG → image appears on profile and in nav
- [ ] Upload a PHP file → rejected at MIME check
- [ ] Upload a file > 2MB → rejected at size check
- [ ] Upload file with renamed extension (shell.php renamed to shell.jpg) → rejected
- [ ] View own profile → redirected to profile.php (edit view)

### Search
- [ ] Search own username → own account appears, marked "(you)"
- [ ] Search another user → appears with VIEW and SEND buttons
- [ ] Own account shows VIEW only (no SEND button on self)
- [ ] SEND button goes to transfer.php with username pre-filled

### Transfers
- [ ] Transfer to valid user → balance decremented / recipient incremented
- [ ] Transfer with amount exceeding balance → "Insufficient balance"
- [ ] Transfer to self → rejected
- [ ] Transfer to nonexistent username → "User not found"
- [ ] Transfer triggers confirm modal (not browser alert)
- [ ] Cancel in modal → form not submitted, balance unchanged
- [ ] Check transaction_history — transaction recorded correctly

### Security
- [ ] Visit `/dashboard.php` when not logged in → redirect to login
- [ ] Visit `/config/db.php` directly → 403 Forbidden
- [ ] Visit `/includes/csrf.php` directly → 403 Forbidden
- [ ] Remove CSRF token from transfer form (browser devtools) → 403
- [ ] POST to `/logout.php` without CSRF token → 403
- [ ] Access `http://localhost:8080/` → lands on login (not directory listing)
- [ ] Check response headers: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`

### Admin
- [ ] Login at `/admin_login.php` with admin credentials → logs panel
- [ ] Filter logs by username, IP, date → results update
- [ ] Wrong admin password 3 times → admin rate lockout
- [ ] Visit `/admin_logs.php` without admin session → redirected

---

## Attack Surface Summary

| Category | Attack | Mitigation |
|---|---|---|
| Injection | SQL injection | PDO prepared statements, `ATTR_EMULATE_PREPARES false` |
| Injection | LIKE wildcard injection | `addcslashes($term, '%_')` |
| Injection | Log injection | Strips `\n \r \v \f \0 \|` from log values |
| Injection | Path traversal in uploads | Random filename, `basename()` on all paths |
| XSS | Stored/reflected XSS | `htmlspecialchars()` on all output, CSP header |
| XSS | Cookie theft via XSS | `httponly` session cookie flag |
| CSRF | Forged POST requests | CSRF tokens on all forms including logout |
| CSRF | Forged logout | Logout is POST-only — GET does nothing |
| Auth | Brute force login | 5 attempts/IP/15 min rate limit in DB |
| Auth | Username enumeration (timing) | Dummy hash compared on unknown user |
| Auth | Username enumeration (message) | Generic error message for all failures |
| Auth | Session fixation | `session_regenerate_id(true)` on login |
| Auth | Session hijacking | httponly + samesite=Strict cookies |
| Auth | Stale session after logout | Three-step destroy: array + file + cookie |
| Auth | Cached page after logout | `Cache-Control: no-store` on all PHP |
| Auth | Forced logout via GET | Logout ignores GET requests |
| IDOR | Viewing other users' data | Session ID used in all WHERE clauses |
| IDOR | Enumerating accounts by ID | Sequential INT replaced with random public_id |
| Transfer | Sender spoofing | Sender from `$_SESSION`, never from POST |
| Transfer | Race condition / double-spend | `FOR UPDATE` row lock + transaction |
| Transfer | Negative/zero amount | Validated: must be 0.01–10,000 |
| Transfer | Float precision error | `round($amount, 2)` + `DECIMAL(12,2)` in DB |
| Upload | Malicious file execution | Files stored outside webroot |
| Upload | MIME spoofing | `finfo` reads magic bytes, not filename |
| Upload | Oversized upload | `filesize($tmp_name)` — not `$_FILES['size']` |
| Upload | Path traversal filename | Filename discarded, random hex generated |
| Upload | PHP execution via upload | `open_basedir` + outside webroot + `disable_functions` |
| Container | Privilege escalation | `no-new-privileges:true`, `cap_drop: ALL` |
| Container | Direct DB access from internet | No `ports:` on MySQL service |
| Container | Sensitive files in webroot | Dockerfile copies only PHP app files |
| Headers | Clickjacking | `X-Frame-Options: DENY` |
| Headers | MIME sniffing | `X-Content-Type-Options: nosniff` |
| Headers | Information leakage | `expose_php Off`, `display_errors Off` |
| Headers | DNS prefetch leakage | `x-dns-prefetch-control: off` meta tag |
