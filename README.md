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

