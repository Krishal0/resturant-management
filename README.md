# 🍽 NepDine – Restaurant Management System

A full-stack Restaurant Management System built with **PHP + MySQL** backend and a clean HTML/CSS frontend.

---

## ✅ Features

| Feature | Description |
|---|---|
| **Authentication** | Login / Sign-up with session-based auth, password hashing (bcrypt) |
| **Form Validation** | Server-side + client-side validation on all forms |
| **Menu Management** | Full CRUD – add/edit/delete menu items, toggle availability, grouped by category |
| **Waiter Management** | Full CRUD – add/edit/delete waiters with phone & email |
| **Table Management** | Full CRUD – visual table cards, change status (available/occupied/reserved) |
| **Order System** | Take orders per table, assign waiter, live price calculation |
| **Billing** | Generate bill with 13% VAT, select payment method, print-ready receipt |
| **Dashboard** | Live stats: occupied tables, open orders, today's revenue |

---

## 🗂 File Structure

```
resturant-management/
├── config.php          # DB connection + session helpers
├── db.sql              # Full database schema + seed data
├── setup_lamp.sh       # Auto-install LAMP on Ubuntu/Debian
│
├── login.php           # Login page
├── signup.php          # Registration page
├── logout.php          # Clears session
├── index.php           # Dashboard with live stats
├── menu.php            # Menu CRUD
├── waiter.php          # Waiter CRUD
├── table.php           # Table CRUD + status cards
├── orders.php          # Take/manage orders
├── billing.php         # Generate & print bills
│
├── includes/
│   └── sidebar.php     # Shared navigation sidebar
│
├── style.css           # Main stylesheet (incl. print styles)
├── login.css           # Login page styles
└── signup.css          # Sign-up page styles
```

---

## 🚀 Setup Instructions (LAMP on Ubuntu/Linux)

### Option A – Auto Script
```bash
cd resturant-management
bash setup_lamp.sh
```
Then open: **http://localhost/nepdine/login.php**

---

### Option B – Manual Steps

**1. Install LAMP**
```bash
sudo apt update
sudo apt install apache2 mysql-server php libapache2-mod-php php-mysql php-mbstring -y
sudo service apache2 start
sudo service mysql start
```

**2. Create the Database**
```bash
mysql -u root -p < db.sql
```

**3. Configure DB credentials** – Edit `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');   // your MySQL user
define('DB_PASS', '');       // your MySQL password
define('DB_NAME', 'nepdine_db');
```

**4. Link to Apache web root**
```bash
sudo ln -s /path/to/resturant-management /var/www/html/nepdine
```

**5. Open in browser**
```
http://localhost/nepdine/login.php
```

---

## 🔑 Default Login

| Email | Password |
|---|---|
| admin@nepdine.com | `Admin@123` |

---

## 🗃 Database Tables

| Table | Purpose |
|---|---|
| `users` | Admin / staff accounts |
| `waiters` | Waiter records |
| `restaurant_tables` | Tables with status tracking |
| `categories` | Menu categories |
| `menu_items` | Menu items with prices |
| `orders` | Order header (table + waiter) |
| `order_items` | Individual items per order |
| `bills` | Generated bills with totals |

---

## 🖨 Printing Bills
On the Billing page, click **Print Bill** — the sidebar and buttons are hidden automatically via CSS `@media print`.
