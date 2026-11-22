# StudyMaze — Study & Productivity Management Website

A complete, fully functional, responsive, and secure Web Technologies project built with **HTML5, CSS3, vanilla JavaScript, PHP 8, and MySQL (PDO)**, designed to run locally using **XAMPP / phpMyAdmin** on macOS, Windows, or Linux.

StudyMaze provides an aesthetic, distraction-free study environment featuring customizable countdown & Pomodoro timers with refresh recovery, subject and task management, database-driven productivity analytics (Chart.js), an ambient sound controller, an achievements system, and 5 distinct themes in both Light and Dark modes.

---

## 🛠 Technology Stack
- **Frontend:** Semantic HTML5, CSS3 (CSS Custom Properties, Flexbox, CSS Grid), Vanilla ES6+ JavaScript.
- **Backend:** PHP 8.2+ (PDO prepared statements, CSRF token validation, strict session regeneration, bcrypt `password_hash()`, rate limiting).
- **Database:** MySQL / MariaDB (InnoDB, `utf8mb4_unicode_ci`, foreign keys with cascading delete/set null).
- **Mailing:** PHPMailer with SMTP support and automatic local development fallback logger (`logs/email_log.txt`).
- **Data Visualization:** Chart.js (local file with CDN fallback).
- **Iconography:** Lucide Icons (local file with CDN fallback, accessible ARIA attributes).
- **Audio:** Local ambient audio WAV files and Web Audio API procedural synthesis.

---

## 🚀 Setup Instructions (XAMPP & phpMyAdmin)

### Step 1: Project Location in XAMPP
Ensure the project folder is placed inside your XAMPP `htdocs` directory:
- **macOS:** `/Applications/XAMPP/xamppfiles/htdocs/StudyMaze/`
- **Windows:** `C:\xampp\htdocs\StudyMaze\`

### Step 2: Start Apache and MySQL
Open the **XAMPP Control Panel** and start both **Apache** and **MySQL**.

### Step 3: Database Import
1. Open [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/) in your web browser.
2. Create a database named `studymaze` with collation `utf8mb4_unicode_ci`.
3. Click the **Import** tab, choose the file:
   `/Applications/XAMPP/xamppfiles/htdocs/StudyMaze/database/database.sql`
   and click **Import**.
4. (Optional for existing databases): Run `database/migration.sql` to safely add email verification and password reset tables without dropping existing data.

### Step 4: Environment Configuration (Optional)
StudyMaze works out of the box with default XAMPP credentials (`root` with no password).
To customize your database connection or configure real SMTP email delivery:
1. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
2. Edit `.env` with your preferred credentials:
   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=studymaze
   DB_USER=root
   DB_PASS=

   # Real SMTP (e.g. Gmail App Password)
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USER=your_email@gmail.com
   SMTP_PASS=your_app_password
   MAIL_FROM=noreply@studymaze.local
   ```
*Note: In local development, if SMTP credentials are left blank, all verification codes and reset links are safely logged to `logs/email_log.txt` so testing never blocks.*

### Step 5: Test Database Connectivity
Run the built-in database health check in your browser or terminal:
- **Browser:** Visit [http://localhost/StudyMaze/test_db.php](http://localhost/StudyMaze/test_db.php)
- **Terminal:**
  ```bash
  /Applications/XAMPP/xamppfiles/bin/php /Applications/XAMPP/xamppfiles/htdocs/StudyMaze/test_db.php
  ```

---

## 🌐 Application URL
Open Safari, Chrome, or any browser and visit:
```text
http://localhost/StudyMaze/
```
Or navigate directly to the sign-in page:
```text
http://localhost/StudyMaze/login.php
```

---

## 🔐 Authentication, Security & Verification Workflows

### 1. Account Registration (`register.php`)
- Enter your full name, email, and a secure password (minimum 8 characters, letters & numbers).
- Client-side and server-side validation protect against duplicate emails, usernames, and weak passwords.
- A cryptographically secure 6-digit verification code is generated, hashed with SHA-256 in MySQL, and sent to your email (or logged to `logs/email_log.txt` in local mode).
- Unverified accounts cannot access protected dashboard features until verified.

### 2. Email Verification (`verify_email.php`)
- Enter the 6-digit code or click the automatic link in the email.
- Failed attempts are capped at 5 tries to prevent brute forcing.
- Resend requests are rate-limited to 1 per 60 seconds.
- Once verified, the user is automatically logged in and redirected to the dashboard.

### 3. Sign In (`login.php`)
- Sign in with either username or email and password.
- No demo credentials or prefilled forms.
- Passwords verified securely using PHP `password_verify()` with `PASSWORD_DEFAULT` (bcrypt).
- Rate-limiting prevents credential stuffing (maximum 10 failed attempts per 15 minutes per IP address).
- Session IDs are strictly regenerated on login to prevent session fixation.

### 4. Forgot / Reset Password (`forgot_password.php` & `reset_password.php`)
- Step 1: Submit your account email. Generic response prevents user enumeration.
- Step 2: Receive a cryptographically secure 6-digit reset code valid for 10 minutes.
- Step 3: Enter the 6-digit code and choose a new password. Previous reset codes are invalidated.

---

## 🎨 Themes & Accessibility
- **5 Aesthetic Themes:** StudyMaze Default (Orange), Studio Ghibli (Sage), Crimson Night (Red), Forest Study (Green), and Lavender Dream (Purple).
- **Appearance Modes:** Instant Light / Dark toggle persisted to user settings.
- **Dialogs & Toasts:** Replaced all native browser `alert()`, `confirm()`, and `prompt()` dialogs with custom accessible modal dialogs (`window.showConfirmModal`, `window.showPromptModal`) and non-blocking toast notifications.
- **WAVE & WCAG AA Compatibility:** Includes a skip-to-main-content link, `id="mainContent"` landmarks, programmatic input labels, accessible SVGs, and high-contrast color variables.

---

## 🧪 Automated Testing
Run the comprehensive end-to-end test suite:
```bash
/Applications/XAMPP/xamppfiles/bin/php /Applications/XAMPP/xamppfiles/htdocs/StudyMaze/tests/e2e_verification.php
```
All 20 verification tests validate schema integrity, password hashing, unverified account guards, email logging, verification code validation, password reset workflows, and rate limiting.
