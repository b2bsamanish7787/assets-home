# Buzznation Assets Management System

A web-based IT asset management portal built with Core PHP and MySQL that enables admins, HR staff, and employees to track and manage company assets across their full lifecycle.

---

## Installation Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-org/assets-home.git
   cd assets-home
   ```

2. **Create MySQL database**
   ```sql
   CREATE DATABASE buzznation_assets CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Import the schema**
   ```bash
   mysql -u root -p buzznation_assets < database/schema.sql
   ```

4. **Configure the application**
   Edit `config/config.php` with your database credentials and site URL:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'buzznation_assets');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   define('SITE_URL', 'http://localhost/assets-home');
   define('SITE_NAME', 'Buzznation Assets Management System');
   ```

5. **Seed the default admin account** _(run once)_
   ```bash
   php database/seed.php
   ```

6. **Set upload folder permissions**
   ```bash
   chmod 755 assets/uploads/
   ```

7. **Configure mail settings** in `config/config.php`
   ```php
   define('MAIL_HOST', 'smtp.example.com');
   define('MAIL_PORT', 587);
   define('MAIL_USER', 'noreply@example.com');
   define('MAIL_PASS', 'your_mail_password');
   define('MAIL_FROM', 'noreply@example.com');
   define('MAIL_FROM_NAME', 'Buzznation Assets');
   ```

8. **Visit the application**
   Open your browser and navigate to `http://localhost/assets-home/login.php`

---

## Default Login

| Field    | Value                              |
|----------|------------------------------------|
| Email    | admin@buzznationmarketing.com      |
| Password | Admin@123                          |

> **Important:** Change the default admin password immediately after first login.

---

## Project Structure

```
assets-home/
├── admin/                          # Admin-only pages
│   ├── assets/
│   │   ├── assign.php              # Assign asset to employee
│   │   ├── history.php             # Asset assignment history
│   │   ├── index.php               # Asset listing & management
│   │   ├── return.php              # Return asset from employee
│   │   └── transfer.php            # Transfer asset between employees
│   ├── categories/
│   │   ├── create.php
│   │   ├── delete.php
│   │   ├── edit.php
│   │   └── index.php
│   ├── employees/
│   │   ├── create.php
│   │   ├── delete.php
│   │   ├── edit.php
│   │   └── index.php
│   ├── logs/
│   │   └── index.php               # Activity log viewer with filters
│   ├── notifications/
│   │   └── index.php               # Notification management
│   ├── reports/
│   │   └── index.php               # Reports with CSV export
│   ├── requests/
│   │   ├── action.php              # Approve/reject requests
│   │   └── index.php
│   ├── service/
│   │   ├── action.php              # Update service ticket status
│   │   └── index.php
│   └── dashboard.php
│
├── ajax/                           # AJAX & CSV export endpoints
│   ├── export_logs.php             # CSV export of activity logs
│   ├── export_report.php           # CSV export of asset reports
│   └── notifications.php           # Notification count/list/mark-read
│
├── assets/                         # Front-end static files
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── app.js
│   └── uploads/                    # User-uploaded files (bill images, etc.)
│
├── config/
│   ├── config.php                  # App constants (DB, SITE_URL, mail, etc.)
│   ├── db.php                      # PDO connection
│   └── functions.php               # Shared helpers (auth, CSRF, flash, etc.)
│
├── database/
│   ├── schema.sql                  # Full DB schema
│   └── seed.php                    # Seeds default admin user
│
├── employee/                       # Employee-facing pages
│   ├── assets/
│   │   ├── my_assets.php           # View own assigned assets
│   │   └── submit.php              # Submit a personally-owned asset
│   ├── requests/
│   │   ├── my_requests.php         # View own asset requests
│   │   └── new.php                 # Raise a new asset request
│   ├── service/
│   │   ├── my_service.php          # View own service tickets
│   │   └── new.php                 # Raise a new service ticket
│   ├── change_password.php
│   ├── consent.php                 # Asset ownership consent page
│   └── dashboard.php
│
├── hr/
│   └── dashboard.php               # HR dashboard (read-only reports)
│
├── includes/                       # Shared UI partials
│   ├── footer.php
│   ├── header.php
│   └── sidebar.php
│
├── index.php                       # Entry point (redirects by role)
├── login.php
├── logout.php
└── README.md
```

---

## Technology Stack

| Layer      | Technology                                      |
|------------|-------------------------------------------------|
| Backend    | Core PHP (no frameworks)                        |
| Database   | MySQL with PDO prepared statements              |
| Frontend   | HTML5, CSS3, Bootstrap 5, jQuery, AJAX          |
| Libraries  | DataTables, PHPMailer, SweetAlert2              |
| Server     | Apache / Nginx + PHP 8.x                        |

---

## Security Features

| Feature                         | Implementation                                      |
|---------------------------------|-----------------------------------------------------|
| CSRF protection                 | Tokens generated per session on all forms           |
| SQL injection prevention        | PDO prepared statements throughout                  |
| Password security               | bcrypt hashing via `password_hash()`                |
| Session hardening               | Session ID regenerated on login; timeout enforced   |
| File upload validation          | MIME type & extension whitelist checked server-side |
| XSS prevention                  | `htmlspecialchars()` on all user-supplied output    |
| Brute-force protection          | Rate limiting — max 5 failed login attempts         |

---

## Roles & Permissions

| Role         | Access Level                                                                 |
|--------------|------------------------------------------------------------------------------|
| **Admin**    | Full access — manage assets, categories, employees, requests, service, logs, reports, notifications |
| **HR**       | View employees, assets, and reports; export CSVs; read-only dashboards       |
| **Employee** | View and submit own assets; raise asset requests and service tickets; change password |

---

## Database Tables

| Table             | Purpose                                      |
|-------------------|----------------------------------------------|
| `users`           | All user accounts (admin, HR, employee)      |
| `categories`      | Asset category definitions                   |
| `assets`          | Asset inventory and assignment records       |
| `activity_logs`   | Audit trail of all system actions            |
| `notifications`   | In-app notifications per user or broadcast   |

---

## API / AJAX Endpoints

| Endpoint                         | Method | Auth         | Description                      |
|----------------------------------|--------|--------------|----------------------------------|
| `ajax/notifications.php?action=count`     | GET    | Any login    | Unread notification count        |
| `ajax/notifications.php?action=list`      | GET    | Any login    | Last 10 notifications            |
| `ajax/notifications.php?action=mark_read&id=X` | GET | Any login | Mark single notification read   |
| `ajax/notifications.php?action=mark_all_read`  | GET | Any login | Mark all notifications read     |
| `ajax/export_logs.php`           | GET    | Admin        | Download activity logs CSV       |
| `ajax/export_report.php`         | GET    | Admin / HR   | Download asset/employee report CSV |

---

## License

&copy; Buzznation IT Team. All rights reserved.