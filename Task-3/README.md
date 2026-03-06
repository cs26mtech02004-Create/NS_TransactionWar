# TransactiWar — Setup Guide

**CS6903: Network Security · IIT Hyderabad 2025–26**

---

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed
- [Docker Compose](https://docs.docker.com/compose/) (included with Docker Desktop)

---

## Project Structure

```
transactiwar/
├── Dockerfile                  ← PHP 8.2 + Apache image
├── docker-compose.yml          ← Orchestrates web + db containers
├── docker/
│   └── entrypoint.sh           ← Startup script (waits for DB, runs setup)
├── sql/
│   └── schema.sql              ← ALL tables: users, sessions, transactions, activity_log
├── php/
│   ├── db.php                  ← PDO connection + helpers (include everywhere)
│   ├── create_accounts.php     ← Auto-creates 5 demo accounts
│   └── ... (your PHP pages)
└── README.md
```

---

## Quickstart (First Time)

```bash
# 1. Clone / unzip the project
cd transactiwar

# 2. Build and start both containers
docker compose up --build

# Wait until you see: "🚀 Starting Apache..."
# Then open your browser:
```

🌐 **App:** http://localhost  
🗄️ **MySQL:** localhost:3306 (use any DB client like DBeaver or TablePlus)

---

## Demo Accounts (auto-created on startup)

| Username | Email                          | Password     | Balance |
|----------|-------------------------------|--------------|---------|
| alice    | alice@transactiwar.local      | Password@123 | ₹100    |
| bob      | bob@transactiwar.local        | Password@123 | ₹100    |
| charlie  | charlie@transactiwar.local    | Password@123 | ₹100    |
| dave     | dave@transactiwar.local       | Password@123 | ₹100    |
| eve      | eve@transactiwar.local        | Password@123 | ₹100    |

---

## Database Connection Details

| Setting  | Value              |
|----------|--------------------|
| Host     | `db` (inside Docker) / `localhost` (outside) |
| Port     | `3306`             |
| Database | `transactiwar`     |
| User     | `tw_user`          |
| Password | `TW_StrongPass#2026` |
| Root PW  | `root_secret_2026` |

---

## Using db.php in Your PHP Files

```php
<?php
session_start();
require_once __DIR__ . '/db.php';

// Log the page visit (required by spec)
logActivity('mypage.php', $_SESSION['username'] ?? 'guest');

// Require login
auth_required();

// Get DB connection
$pdo = getDB();

// Safe output (always use h() on user data)
echo h($someUserInput);

// CSRF token in forms
// <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

// Verify CSRF on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    // ... handle form
}
```

---

## Common Commands

```bash
# Start in background
docker compose up -d

# View logs
docker compose logs -f web
docker compose logs -f db

# Stop containers
docker compose down

# Stop AND delete all DB data (fresh start)
docker compose down -v

# Open MySQL shell inside container
docker exec -it transactiwar_db mysql -u tw_user -pTW_StrongPass#2026 transactiwar

# Run PHP script inside container
docker exec -it transactiwar_web php /var/www/html/create_accounts.php
```

---

## Database Tables

| Table          | Purpose                                      |
|----------------|----------------------------------------------|
| `users`        | Accounts, balance, profile image, bio        |
| `sessions`     | Secure session tracking (optional)           |
| `transactions` | All money transfers + comments               |
| `activity_log` | Page visits: webpage, username, timestamp, IP|

---

## References

- PHP PDO Documentation: https://www.php.net/manual/en/book.pdo.php
- MySQL 8.0 Reference: https://dev.mysql.com/doc/refman/8.0/en/
- OWASP Top 10: https://owasp.org/www-project-top-ten/
- Docker Compose Docs: https://docs.docker.com/compose/
- PHP `password_hash()`: https://www.php.net/manual/en/function.password-hash.php
