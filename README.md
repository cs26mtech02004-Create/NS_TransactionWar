# TransactiWar
CS6903 Network Security · IIT Hyderabad 2025-26

---

## How to Run

```bash
docker compose up --build
```

App is live at **http://localhost:8083**

To stop:
```bash
docker compose down
```

To reset database completely:
```bash
docker compose down -v && docker compose up --build
```

---

## Pages

| URL | Description |
|-----|-------------|
| `/register.php` | Create new account (Task 1) |
| `/login.php` | Login (Task 1) |
| `/profile.php` | Edit your profile, bio, image (Task 2) |
| `/view_profile.php?id=X` | View another user's profile (Task 2) |
| `/search.php` | Search users by name or ID (Task 3) |
| `/transfer.php` | Send money to another user (Task 3) |
| `/history.php` | View transaction history (Task 3) |

---

## Demo Accounts

All accounts: password `Password@123`, balance ₹100

| Username | User ID |
|----------|---------|
| alice    | 1 |
| bob      | 2 |
| charlie  | 3 |
| dave     | 4 |
| eve      | 5 |

---

## Security Features

- Passwords hashed with bcrypt (cost=12)
- Prepared statements on all DB queries (SQLi protection)
- CSRF tokens on every form
- `htmlspecialchars()` on all output (XSS protection)
- `session_regenerate_id()` on login (session fixation protection)
- `FOR UPDATE` DB lock on transfers (race condition protection)
- Secure file upload with type + size validation
- Activity logging on every page

---

## References
- PHP PDO: https://www.php.net/manual/en/book.pdo.php
- OWASP Top 10: https://owasp.org/www-project-top-ten/
- Docker Compose: https://docs.docker.com/compose/
- bcrypt: https://www.php.net/manual/en/function.password-hash.php
