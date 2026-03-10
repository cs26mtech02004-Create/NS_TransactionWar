# TransactiWar — Task 3: User Search & Money Transfer
**CS6903 Network Security · IIT Hyderabad 2025-26**

---

## Project Structure

```
transactiwar/
├── Dockerfile
├── docker-compose.yml
├── docker/
│   └── entrypoint.sh          ← waits for DB, runs account setup, starts Apache
├── sql/
│   └── schema.sql             ← all 4 tables: users, sessions, transactions, activity_log
└── php/                       ← entire web root (copied into container at /var/www/html)
    ├── db.php                 ← PDO connection + helpers (getDB, logActivity, csrf, h)
    ├── create_accounts.php    ← auto-creates 5 demo accounts
    ├── fake_login.php         ← TESTING ONLY — delete before submission
    ├── search.php             ← Task 3a: search users by username or ID
    ├── transfer.php           ← Task 3b: send money (GET=form, POST=process)
    ├── lookup_user.php        ← AJAX: verify receiver user ID
    ├── history.php            ← Task 3c: transaction history
    ├── css/
    │   └── style.css
    ├── includes/
    │   ├── header.php
    │   └── footer.php
    └── uploads/
        └── profiles/          ← profile image storage
```

---

## Run with Docker

```bash
# First time — builds image and starts both containers
docker compose up --build

# Background mode
docker compose up -d --build

# View logs
docker compose logs -f web
docker compose logs -f db

# Stop
docker compose down

# Stop + delete all DB data (fresh start)
docker compose down -v
```

**App is live at:** http://localhost  
**phpMyAdmin (optional):** add the adminer service or connect any MySQL client to `localhost:3306`

---

## Test Without Login Page (fake_login.php)

Since login/register is Task 1 (teammate's work), use the test login page:

1. Visit http://localhost/fake_login.php
2. Click any Task 3 link — session is set automatically
3. Switch between users using the links at the bottom
4. **DELETE fake_login.php before final submission**

---

## Demo Accounts

| Username | Password     | Balance |
|----------|-------------|---------|
| alice    | Password@123 | ₹100   |
| bob      | Password@123 | ₹100   |
| charlie  | Password@123 | ₹100   |
| dave     | Password@123 | ₹100   |
| eve      | Password@123 | ₹100   |

---

## How db.php Works (for teammates)

```php
<?php
session_start();
require_once __DIR__ . '/db.php';

auth_required();   // redirects to /login.php if not logged in
logActivity('mypage.php', $_SESSION['username'] ?? 'guest');

$pdo = getDB();    // returns PDO connection

// Always sanitize output:
echo h($userInput);

// CSRF in forms:
// <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

// Verify on POST:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}
```

---

## References
- PHP PDO: https://www.php.net/manual/en/book.pdo.php
- MySQL 8.0: https://dev.mysql.com/doc/refman/8.0/en/
- OWASP Top 10: https://owasp.org/www-project-top-ten/
- Docker Compose: https://docs.docker.com/compose/
- password_hash(): https://www.php.net/manual/en/function.password-hash.php
