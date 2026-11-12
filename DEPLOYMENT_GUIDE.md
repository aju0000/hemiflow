# HemiFlow Agency OS - Live Deployment Guide

This package is pre-configured and ready to deploy on **Hostinger**, **cPanel**, **LAMP/LEMP**, or any PHP hosting environment.

---

## 🚀 Fast 3-Step Live Deployment (Hostinger / cPanel)

### Step 1: Upload & Extract
1. Open your hosting control panel (e.g., Hostinger **hPanel** -> **File Manager** or cPanel -> **File Manager**).
2. Navigate to your website's root directory:
   - For primary domain: `public_html/`
   - For a subdomain/subfolder: `public_html/hemiflow/`
3. Upload `hemiflow_live_deploy.zip` and click **Extract**.

---

### Step 2: Configure Database

#### Option A: Automated 1-Click Setup (Recommended for Hostinger & cPanel)
1. In your hosting panel, create a new MySQL database:
   - Note the **Database Name**, **Database Username**, and **Password**.
2. In your browser, navigate to:
   `https://yourdomain.com/setup_hostinger.php` (or `https://yourdomain.com/hemiflow/setup_hostinger.php`)
3. Enter your MySQL database credentials and click **Connect & Install Database**.
4. The script will automatically:
   - Connect to your MySQL server.
   - Import all database tables and schema (`hostinger_agency_os.sql`).
   - Seed default document templates and initial administrative records.
   - Update `db.php` with your live database configuration.
5. *(Security)* Once completed, delete `setup_hostinger.php` from your server or restrict access.

#### Option B: Manual phpMyAdmin Import
1. Create a MySQL database in your hosting panel.
2. Open **phpMyAdmin**, select the created database, click the **Import** tab, and upload `hostinger_agency_os.sql`.
3. Open `db.php` in File Manager and update:
   ```php
   define('DB_TYPE', 'mysql');
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_actual_db_name');
   define('DB_USER', 'your_actual_db_user');
   define('DB_PASS', 'your_actual_db_password');
   ```

#### Option C: Instant SQLite (Zero DB Setup)
If your PHP host supports SQLite PDO, HemiFlow is pre-loaded with `agency_os.db`. Simply keep `DB_TYPE` set to `sqlite` in `db.php` and make sure the file and directory have write permissions (`chmod 775`).

---

### Step 3: Default Login Credentials

#### Super Admin Login:
- **URL**: `https://yourdomain.com/login.php`
- **Username**: `admin`
- **Password**: `admin123`

#### Client Portal Login:
- **URL**: `https://yourdomain.com/client/login.php`

---

## 🔒 Security Best Practices for Live
- Delete or rename `setup_hostinger.php` after the initial setup.
- The included `.htaccess` file prevents public web access to `.db`, `.sql`, and directory listings.
- Ensure `uploads/` folder has write permissions (`chmod 755` or `775`).
