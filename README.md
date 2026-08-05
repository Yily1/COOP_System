# 📘 Coop System — Role-Based PHP Application

A simple role-based PHP PDO web application with email verification, activity logging, and basic authentication. Supports three roles:

* **Admin**
* **Manager**
* **User**

This README explains how to run the project on **localhost** and how to **deploy to Hostinger using FTP**.

---

## 📂 Project Structure

```
coop-system/
│
├── app/
│   ├── admin/
│   │   └── dashboard.php
│   │
│   ├── auth/
│   │   ├── signout.php
│   │   └── verify-email.php
│   │
│   ├── manager/
│   │   └── dashboard.php
│   │
│   ├── user/
│   │   └── dashboard.php
│   │
│   └── users/
│       ├── dashboard.php
│       ├── user-create.php
│       ├── user-delete.php
│       ├── user-update.php
│       └── user-view.php
│
├── assets/
│   └── css/
│       └── style.css
│
├── config/
│   ├── config.php
│   └── functions.php
│
├── db/
│   └── schema.sql
│
├── includes/
│   └── activity-logger.php
│
├── tests/
│   ├── test-access.php
│   ├── test-login.php
│   └── test-mail.php
│
└── index.php   (login page)
```

> ⚠️ Note: `config/`, `db/`, `includes/`, `assets/`, and `tests/` live at the **project root**, sibling to `app/` — this matches the `require_once '../../config/config.php'` paths used inside `app/*/*.php`.

---

## ⚙️ Requirements

* PHP 8.0+
* MySQL / MariaDB
* Apache (XAMPP, WAMP, or Hostinger hosting)
* FTP Client (FileZilla)

---

# 🚀 Localhost Setup (XAMPP / WAMP)

### 1️⃣ Copy Project

Place project folder inside:

```
C:/xampp/htdocs/coop-system
```

---

### 2️⃣ Create Database

Open **phpMyAdmin**:

```
http://localhost/phpmyadmin
```

Create database and tables using:

```
db/schema.sql
```

Import the file or paste SQL into SQL tab and run.

---

### 3️⃣ Configure Database Connection

Edit:

```
config/config.php
```

Example:

```php
<?php
$host = 'localhost';
$dbname = 'coop_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
?>
```

---

### 4️⃣ Set Base URL

Inside `config.php`:

```php
define('BASE_URL', 'http://localhost/coop-system');
```

---

### 5️⃣ Start Server

Open browser:

```
http://localhost/coop-system
```

---

### 6️⃣ Default Accounts

| Role    | Email                                             | Password    |
| ------- | ------------------------------------------------- | ----------- |
| Admin   | [admin@example.com](mailto:admin@example.com)     | password123 |
| Manager | [manager@example.com](mailto:manager@example.com) | password123 |
| User    | [user@example.com](mailto:user@example.com)       | password123 |

---

### 7️⃣ Test Pages

```
http://localhost/coop-system/tests/test-login.php
http://localhost/coop-system/tests/test-mail.php
http://localhost/coop-system/tests/test-access.php
```

---

# 🌐 Hostinger Deployment (FTP)

---

## 1️⃣ Create Database in Hostinger

1. Login to Hostinger hPanel
2. Go to **Databases → MySQL Databases**
3. Create:

   * Database name
   * Username
   * Password
4. Open **phpMyAdmin** from hPanel
5. Import `schema.sql`

---

## 2️⃣ Update Config File

Edit:

```
config/config.php
```

Replace with Hostinger credentials:

```php
<?php
$host = 'localhost';
$dbname = 'hostinger_db_name';
$username = 'hostinger_user';
$password = 'hostinger_password';

define('BASE_URL', 'https://yourdomain.com/coop-system');

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
?>
```

---

## 3️⃣ Upload Files via FTP

Using FileZilla:

* Host: from Hostinger
* Username
* Password
* Port: 21

Upload entire **app** folder to:

```
public_html/coop-system
```

---

## 4️⃣ Access Live App

```
https://yourdomain.com/coop-system
```

---

# 🔐 Roles & Access Rules

| Role    | Accessible Area |
| ------- | --------------- |
| Admin   | /admin, /users  |
| Manager | /manager        |
| User    | /user           |

Unauthorized access redirects to login.

---

# 🧾 Activity Logging

All logins and important actions are stored in:

```
activity_logs
```

Fields logged:

* user_id
* email
* action
* status
* ip_address
* user_agent
* timestamp

---

# 🧪 Testing Checklist

* [ ] Login success
* [ ] Login failure
* [ ] Role redirection
* [ ] Email verification
* [ ] Access protection
* [ ] Activity logs stored

---

# 🔒 Security Recommendations

* Change default passwords
* Use HTTPS on production
* Disable error display:

```php
ini_set('display_errors', 0);
```

* Add reCAPTCHA (optional)
* Add rate limiting

---

# 📦 Backup Before Deployment

1. Zip project
2. Export database
3. Store offline copy

---

# 🧩 Troubleshooting

## Database Error

* Check credentials
* Confirm database exists

## Blank Page

Enable debugging temporarily:

```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## Email Not Sending

* Use Hostinger SMTP
* Check spam folder

---

# 📜 License

Free to use for educational and internal projects.

---
