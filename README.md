# 🍔 OrderGo - College Canteen Management System

**Specathon 3rd Prize Winning Project**

OrderGo is a high-performance, unified **PHP + MySQL** digital cafeteria management portal designed to eliminate long queues, streamline counter operations, and provide transparent order tracking for college campuses.

---

## 🛠️ Architecture & Technology Stack

The application runs as a **single, unified PHP portal** requiring no separate Node.js processes, build tools, or complex proxy setups.

- **Backend Runtime:** PHP 8.0+ (Vanilla PHP with PDO, Sessions, and RESTful JSON endpoints)
- **Database:** MySQL 5.7+ / MariaDB (InnoDB engine, transactions, relational foreign keys)
- **Frontend:** Responsive HTML5 & Vanilla CSS Design System (Glassmorphism, dark/light theme)
- **Client Scripting:** Modern JavaScript ES6+ (Native Fetch API, LocalStorage state management)
- **Icons & QR Tech:** Lucide Icons CDN, QRCode.js, and HTML5-QRCode Camera Scanner CDN
- **Charts:** Chart.js CDN (Revenue curves, peak hour distribution, top item analytics)

---

## 📂 Project Directory Structure

```
OrderGo_Clean/
│
├── config/
│   ├── config.php            # Environment constants, session management, URL helper
│   └── database.php          # Singleton PDO connection & automatic database initialization
│
├── includes/
│   ├── header.php            # HTML head, Google Fonts, theme init, global apiUrl helper
│   ├── navbar.php            # Dynamic navigation bar with live cart counter & role badges
│   ├── footer.php            # Site footer & cache-busted script loader
│   ├── auth.php              # Role guards (require_login, require_role)
│   ├── helpers.php           # Price formatter, input sanitization, status badge renderers
│   └── admin_sidebar.php     # Admin portal navigation sidebar
│
├── assets/
│   ├── css/                  # Glassmorphism design system stylesheets
│   │   ├── main.css          # Core CSS system & utility classes
│   │   ├── home.css          # Hero section & feature highlights
│   │   ├── menu.css          # Menu items grid, budget roulette, review modal
│   │   ├── cart.css          # Order tray review & checkout UI
│   │   ├── orders.css        # Order history cards & status stepper
│   │   ├── staff.css         # Live kitchen orders grid & POS styles
│   │   ├── admin.css         # Admin tables, forms, metric cards
│   │   └── queue.css         # Fullscreen Canteen TV display board
│   ├── js/
│   │   ├── cart.js           # LocalStorage tray manager with instant navbar badge sync
│   │   ├── staff.js          # Live polling (3.5s), synthesized audio chime, status actions
│   │   ├── scanner.js        # Camera QR scanner & manual Order ID verification
│   │   └── analytics.js      # Chart.js renderers for 7-day revenue & peak hours
│   └── images/               # Campus food photos (23 high-res food images)
│
├── api/                      # Dynamic JSON REST endpoints
│   ├── orders.php            # Order creation with inventory locks, payment verification
│   ├── staff_orders.php      # Live kitchen order queue polling & status transitions
│   ├── qr_verify.php         # QR code verification endpoint for counter staff
│   ├── menu_toggle.php       # Instant availability and daily special toggles
│   └── feedback.php          # Customer review submission and retrieval
│
├── staff/
│   └── index.php             # Kitchen Management Console, Walk-in POS, QR Scanner
│
├── admin/
│   ├── index.php             # Admin overview with KPIs and recent campus orders
│   ├── menu.php              # Menu CRUD with image uploads and availability switches
│   ├── orders.php            # Campus order manager with status and date filters
│   └── analytics.php         # Visual business analytics (Revenue curve, peak hours)
│
├── uploads/                  # Directory for uploaded food images (.gitkeep protected)
├── index.php                 # Home landing page with daily specials
├── login.php                 # User login with 1-click demo buttons & role-based routing
├── register.php              # Student signup with roll number validation
├── logout.php                # Session termination
├── forgot-password.php       # 3-step OTP password recovery flow
├── menu.php                  # Full menu with instant search, category filters, budget roulette
├── cart.php                  # Tray checkout with Cash / UPI payment choices
├── payment.php               # Instant UPI payment simulation & transaction logging
├── orders.php                # Active and past student orders listing
├── order-detail.php          # Real-time status progress stepper + Pickup QR code
├── profile.php               # Student profile editor & personal dining stats
├── queue.php                 # Canteen counter TV queue board (Preparing / Collect)
├── install.php               # Database schema installer (CLI / Web)
├── seed.php                  # Database demo seeder (CLI / Web)
├── schema.sql                # Complete MySQL schema definition
├── .htaccess                 # Apache / XAMPP routing & security rules
└── .gitignore                # Git ignore rules
```

---

## ⚡ Quick Start & Installation

### Option 1: Using Built-in PHP Development Server

1. **Start MySQL:**
   Ensure MySQL/MariaDB is running (e.g., via XAMPP or standalone on `127.0.0.1:3306`).

2. **Initialize Database:**
   ```bash
   php install.php
   php seed.php
   ```

3. **Start the PHP Web Server:**
   ```bash
   php -S 127.0.0.1:8000
   ```

4. **Access the Portal:**
   Open [http://127.0.0.1:8000](http://127.0.0.1:8000) in your browser.

---

### Option 2: Using Apache / XAMPP

1. Place or link this folder inside your web root:
   `C:\xampp\htdocs\ordergo`
2. Open your browser and navigate to:
   `http://localhost/ordergo/install.php`
3. Run `http://localhost/ordergo/seed.php` to populate demo data.
4. Access the application at `http://localhost/ordergo/`.

---

## 🔑 Pre-Seeded Demo Accounts

The database comes pre-configured with active accounts for testing all user roles:

| Role | Email | Password | Access Area |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@ordergo.com` | `admin123` | [Admin Portal](/admin/index.php) + [Kitchen Staff](/staff/index.php) |
| **Kitchen Staff** | `staff@ordergo.com` | `staff123` | [Kitchen Management Console & POS](/staff/index.php) |
| **Student** | `rahul@college.edu` | `student123` | [Food Menu](/menu.php), [My Orders](/orders.php), [Profile](/profile.php) |
| **Student** | `priya@college.edu` | `student123` | [Food Menu](/menu.php), [My Orders](/orders.php), [Profile](/profile.php) |

*(Quick 1-click login buttons are also embedded directly into the [Login Page](/login.php) for instant access).*

---

## ✨ Key Features & User Workflows

### 1. Student Dining Workflow
- **Live Menu Browsing:** Filter by categories (Breakfast, Lunch, Snacks, Beverages, Desserts) with instant client-side search.
- **Budget Roulette:** Input an exact budget (e.g., ₹100) to receive an intelligent combination of matching items.
- **Cart & Tray Checkout:** Add items to tray, choose between **UPI Digital Payment** or **Cash at Counter**.
- **Live Status Tracker:** Monitor order state (`Pending` ➔ `Preparing` ➔ `Ready for Pickup` ➔ `Completed`).
- **Student Pickup QR Code:** Automatically generated on the order page for touchless counter pickup.
- **Item Reviews & Ratings:** Submit star ratings and feedback on ordered items.

### 2. Kitchen Staff Management Console (`/staff/index.php`)
- **Live 3.5s Polling Queue:** Instant notification with synthesized audio chime when new orders arrive.
- **One-Click Workflow Transitions:** Advance orders through `Start Preparing`, `Mark Ready`, and `Complete Pickup`.
- **Counter Walk-In POS:** Cash register modal for taking in-person orders from students without phones.
- **Instant QR Code Scanner:** Verify student phone screens using the integrated device camera or manual Order ID input.

### 3. Public Canteen TV Queue Board (`/queue.php`)
- Designed for counter TV displays with split columns (`Cooking / Preparing` vs `Please Collect at Counter`).
- Features a live digital clock, pulse animations, and auto-syncing every 4 seconds.

### 4. Admin Management Console (`/admin/index.php`)
- **KPI Metrics:** Track total campus revenue, active orders, student accounts, and daily completion rates.
- **Menu Item CRUD:** Create, edit, upload photos, and toggle availability or Daily Special tags.
- **Campus Orders Log:** Search and filter orders by status, payment method, or date range.
- **Interactive Analytics:** Interactive Chart.js graphs displaying 7-day revenue trends and peak hour distribution.

---

## 🔒 Configuration & Database Setup

Database configuration can be adjusted in [`config/database.php`](config/database.php):

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'ordergo');
define('DB_USER', 'root');
define('DB_PASS', '');
```

When connecting for the first time, `database.php` will automatically create the `ordergo` database if it does not already exist.

### Database Table Names (`odg_` Prefix)

All database tables are namespaced with the `odg_` prefix for multi-app safety and clean shared hosting:

| Table Name | Description |
| :--- | :--- |
| `odg_users` | User accounts and authentication credentials |
| `odg_students` | Student profiles and meal preferences |
| `odg_admins` | Canteen management administrator profiles |
| `odg_staff` | Kitchen staff counter profiles |
| `odg_menu_items` | Food menu catalogue, prices, stock, and categories |
| `odg_orders` | Orders, totals, live statuses, and QR tokens |
| `odg_order_items` | Individual line items per order |
| `odg_transactions` | Payment receipts and transaction logs |
| `odg_feedback` | Star ratings and customer food reviews |

---

*OrderGo — Developed for campus dining efficiency.*
