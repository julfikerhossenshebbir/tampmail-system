# TempMail Pro — Complete Disposable Email API System

A full-featured, self-hosted **temporary email API** built with PHP. No Composer, no framework — just plain PHP 7.4+.

---

## 📁 File Structure

```
tempmail-pro/
├── .env                        ← Main configuration (edit this first!)
├── index.php                   ← User dashboard (login, register, dashboard)
├── admin.php                   ← Admin panel
├── tempmail.php                ← Public temp mail page (with API key)
├── docs.php                    ← API documentation
├── tester.php                  ← Interactive API tester
├── api/
│   └── index.php               ← REST API endpoint
├── includes/
│   ├── bootstrap.php           ← Loads all includes
│   ├── config.php              ← Reads .env, defines constants
│   ├── database.php            ← INI flat-file DB helper
│   ├── auth.php                ← Auth, OTP, rate limiting, domain logic
│   ├── mailer.php              ← SMTP mailer (no Composer)
│   ├── mailhandler.php         ← IMAP mail reader
│   └── logger.php              ← File logger
└── data/                       ← Auto-created. Store data files here.
    ├── api_keys.ini
    ├── global_email_index.ini
    ├── mail_servers.ini
    ├── api_usage.ini
    ├── otp_store.ini
    ├── rate_limits.ini
    └── app.log
```

---

## 🚀 Installation on cPanel / Shared Hosting

### Step 1 — Upload Files

1. Login to **cPanel → File Manager**
2. Navigate to `public_html/` (or a subdirectory like `public_html/tempmail/`)
3. Upload and extract the ZIP file
4. Make sure the `data/` folder has **write permission (755 or 777)**

   ```
   Right-click data/ → Change Permissions → 755
   ```

### Step 2 — Configure `.env`

Open `.env` in cPanel File Manager and edit:

```env
# Your site URL (no trailing slash)
APP_URL="https://yourdomain.com"

# Your timezone
APP_TIMEZONE="Asia/Dhaka"

# SMTP for sending OTP/verification emails (Gmail, Brevo, etc.)
SMTP_HOST="smtp.gmail.com"
SMTP_PORT=587
SMTP_SECURE="tls"
SMTP_USER="your-email@gmail.com"
SMTP_PASS="your-app-password"
SMTP_FROM_NAME="TempMail Pro"
SMTP_FROM_EMAIL="noreply@yourdomain.com"

# Turn system on/off (false = maintenance mode, blocks everything)
SYSTEM_ONLINE=true

# Require email verification on signup
REQUIRE_EMAIL_VERIFY=true

# Auto-approve new users without admin action
AUTO_APPROVE_USERS=false
```

> **Gmail SMTP:** Use an [App Password](https://myaccount.google.com/apppasswords), not your regular password. Enable 2FA first.

### Step 3 — PHP Requirements

Make sure your hosting has:
- **PHP 7.4+** (PHP 8.x recommended)
- **IMAP extension** enabled (`php-imap`)
- **OpenSSL extension** (usually enabled)

To check in cPanel: **Software → Select PHP Version → Extensions**
Enable: `imap`, `openssl`, `mbstring`

### Step 4 — First Run

1. Visit `https://yourdomain.com/index.php` → Register first user (auto-becomes **Admin**)
2. Login to admin panel: `https://yourdomain.com/admin.php`
3. Add your first **Mail Server** (IMAP)

---

## 📧 Adding Mail Servers (IMAP)

Go to **Admin Panel → Mail Servers → Add Server**

| Field | Example | Notes |
|-------|---------|-------|
| Server Name | Main Mail | Display name |
| Domain | jhs.one | The @domain for temp emails |
| IMAP Host | imap.stackmail.com | Your IMAP server |
| IMAP Port | 993 | 993 = SSL, 143 = plain |
| SSL | Yes | Use SSL for security |
| IMAP User | admin@jhs.one | Master inbox (catch-all) |
| IMAP Password | yourpassword | IMAP password |
| Visibility | Public / Private | Public = all users, Private = owner only |
| Owner | username | Only for Private servers |
| Status | Active | Must be Active to work |

> **Important:** The IMAP account must be a **catch-all inbox** that receives ALL emails for that domain.
> On StackMail, Mailgun, etc., configure a catch-all route to forward everything to this inbox.

### Public vs Private Domains

- **Public** — All API users can generate emails on this domain
- **Private** — Only the specified owner (and admin) can use this domain

---

## 🔑 API Usage

### Base URL
```
https://yourdomain.com/api/
```

### Authentication
```
GET /api/?key=YOUR_API_KEY&action=...
```

### Endpoints

#### `gen_email` — Generate a disposable email
```
GET /api/?key=KEY&action=gen_email
GET /api/?key=KEY&action=gen_email&domain=example.com
```
Response:
```json
{"status":"success","email":"abc123@example.com","password":"f3a2b1c0"}
```

#### `get_messages` — Fetch inbox
```
GET /api/?key=KEY&action=get_messages&email=abc123@example.com&password=f3a2b1c0
```

#### `read_message` — Read email content
```
GET /api/?key=KEY&action=read_message&id=42&email=abc123@example.com&password=f3a2b1c0
```

#### `get_domains` — List accessible domains
```
GET /api/?key=KEY&action=get_domains
```

#### `check_status` — Health check (no key needed)
```
GET /api/?action=check_status
```

---

## ⚙️ Domain Selection Logic

When `gen_email` is called:
1. If `domain=` param is given → use that domain (must be accessible)
2. If API key has **preferred domains** set → pick randomly from those
3. Otherwise → pick randomly from **any active public domain**

Set preferred domains per user in **Admin → Edit User → Preferred Mail Domains**
Or users can set their own in **Dashboard → Settings → Preferred Mail Domains**

---

## 🔒 Security Features

| Feature | Description |
|---------|-------------|
| CSRF Protection | All forms protected with CSRF tokens |
| Rate Limiting | Login attempts limited (configurable in .env) |
| Account Lockout | Auto-lock after failed login attempts |
| Email Verification | OTP sent on registration |
| Password Reset | OTP-based secure reset |
| Domain Lock | Restrict API key to specific origin domains |
| API Usage Limits | Per-key daily/weekly request limits |
| IMAP Disable | Disable a mail server = no emails created or received |
| Maintenance Mode | Set SYSTEM_ONLINE=false to block everything |

---

## 🛠️ Admin Panel Guide

URL: `https://yourdomain.com/admin.php`

### Users
- Add/edit/delete API users
- Set per-user rate limits (e.g. 100/day, -1=unlimited)
- Set preferred mail domains per user
- Activate/deactivate accounts
- Reset passwords

### Temp Mails
- View all generated temp emails
- Delete emails
- Reset email passwords

### Mail Servers
- Add IMAP servers (one per domain)
- Toggle Public/Private visibility
- Enable/Disable servers (disabled = no new emails, no message reading)

### Settings
- View current .env configuration
- Test SMTP connection
- Verify system status

---

## 🌐 Pages Overview

| URL | Description |
|-----|-------------|
| `/index.php` | User login/register/dashboard |
| `/index.php?page=tempmail` | User's temp mail inbox |
| `/tempmail.php` | Public temp mail page (needs API key) |
| `/admin.php` | Admin panel |
| `/docs.php` | API documentation |
| `/tester.php` | Interactive API tester |
| `/api/` | REST API endpoint |

---

## 🔧 Maintenance Mode

In `.env`:
```env
SYSTEM_ONLINE=false
MAINTENANCE_MESSAGE="We'll be back soon!"
```

This blocks:
- All API calls
- User login/registration
- Temp mail creation

Admin panel still accessible.

---

## 📝 .htaccess (Optional, for clean URLs)

Create `.htaccess` in root:
```apache
Options -Indexes
<Files ".env">
    Order allow,deny
    Deny from all
</Files>
<Files "data">
    Order allow,deny
    Deny from all
</Files>
```

> **IMPORTANT:** Protect the `data/` and `.env` files from public access!

---

## 🐛 Troubleshooting

| Problem | Solution |
|---------|----------|
| SMTP not working | Test in Admin → Settings → Test SMTP. Check port, SSL, app password |
| IMAP not connecting | Enable `php-imap` extension in cPanel PHP settings |
| Emails not arriving in inbox | Check IMAP catch-all is configured on your mail server |
| 500 errors | Set `APP_DEBUG=true` in .env temporarily |
| OTP not received | Check spam folder; verify SMTP settings in .env |
| API returns 403 | Check domain lock setting for your API key |
| Rate limit hit | Increase limit in Admin → Users → Edit |

---

## 📦 Requirements Summary

- PHP **7.4+** (8.x recommended)
- PHP Extensions: `imap`, `openssl`, `mbstring`, `json`
- Writable `data/` directory
- IMAP server with catch-all inbox
- SMTP server for verification emails

---

## 👤 Credits

Built with ❤️ — TempMail Pro v2.0
