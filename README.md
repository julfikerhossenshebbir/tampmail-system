# TempMail Pro v2.1

> Self-hosted temporary email API — PHP 7.4+, no Composer, no framework.

---

## 📁 File Structure

```
tempmail-pro/
├── .env                    ← Configure this first!
├── .htaccess               ← Protects data/ and .env
├── index.php               ← User panel (login / register / dashboard)
├── admin.php               ← Admin panel
├── tempmail.php            ← Public temp mail page (needs API key)
├── docs.php                ← API documentation
├── tester.php              ← Live API tester
├── api/
│   └── index.php           ← REST API endpoint
├── includes/
│   ├── bootstrap.php
│   ├── config.php          ← Reads .env
│   ├── database.php        ← INI flat-file DB
│   ├── auth.php            ← Auth, OTP, rate limit, domain logic
│   ├── mailer.php          ← SMTP (OTP emails)
│   ├── mailhandler.php     ← IMAP reader
│   └── logger.php
└── data/                   ← Auto-created, write-protected
```

---

## 🚀 cPanel Installation

### 1. Upload

- Go to **cPanel → File Manager → public_html**
- Upload and extract the ZIP
- Set `data/` folder permission to **755**

### 2. Edit `.env`

Open `.env` and set at minimum:

```
APP_URL="https://yourdomain.com"
APP_TIMEZONE="Asia/Dhaka"
SMTP_HOST="smtp.gmail.com"
SMTP_PORT=587
SMTP_SECURE="tls"
SMTP_USER="your@gmail.com"
SMTP_PASS="your-app-password"
```

> **Gmail:** Enable 2FA → [Create App Password](https://myaccount.google.com/apppasswords) → use that as SMTP_PASS

### 3. PHP Extensions

In cPanel → **Select PHP Version → Extensions**, enable:
- `imap` ✅
- `openssl` ✅
- `mbstring` ✅

### 4. First Run

1. Visit `https://yourdomain.com/index.php` → **Register** (first user = auto Admin)
2. Go to `https://yourdomain.com/admin.php` → **Mail Servers → Add Server**
3. Done!

---

## 📧 Adding IMAP Mail Server (Admin Panel)

**Admin → Mail Servers → Add Server**

| Field | Description |
|-------|-------------|
| Server Name | Display name (e.g. "Main Server") |
| Domain | The @domain for temp emails (e.g. `mail.example.com`) |
| IMAP Host | Your IMAP hostname (e.g. `imap.stackmail.com`) |
| IMAP Port | 993 (SSL) or 143 (plain) |
| SSL | Yes for port 993, No for 143 |
| IMAP User | Master catch-all inbox email |
| IMAP Password | IMAP password |
| Visibility | **Public** = all users, **Private** = owner only |
| Owner | Username (for private servers only) |
| Status | Must be **Active** to work |

> **The FIRST active server you add = default domain.**
> If no server is added/active → temp mail creation is blocked with a clear error.

> **Catch-all required:** The IMAP account must receive ALL emails for the domain. Configure a catch-all route on your mail provider.

---

## 🔑 Domain Selection Logic (API)

When `?action=gen_email` is called:

| Scenario | Result |
|----------|--------|
| No IMAP server configured | ❌ Error: "No IMAP mail servers configured" |
| All servers disabled | ❌ Error: "All mail servers are currently disabled" |
| User has **preferred domains** set + no `?domain=` | ✅ Random from preferred |
| User has **preferred domains** set + `?domain=` in preferred | ✅ Use that domain |
| User has **preferred domains** set + `?domain=` NOT in preferred | ❌ Error: "Domain X not in your preferred domains. Allowed: ..." |
| User has **no preferred domains** + `?domain=` valid | ✅ Use that domain |
| User has **no preferred domains** + `?domain=` invalid | ❌ Error: "Domain X not available. Available: ..." |
| User has **no preferred domains** + no `?domain=` | ✅ Random from all accessible |

---

## 🌐 API Reference

**Base URL:** `https://yourdomain.com/api/`

### `gen_email`
```
GET /api/?key=KEY&action=gen_email
GET /api/?key=KEY&action=gen_email&domain=mail.example.com
```
Response:
```json
{"status":"success","email":"abc12345@mail.example.com","password":"f3a2b1c0"}
```

### `get_messages`
```
GET /api/?key=KEY&action=get_messages&email=abc12345@mail.example.com&password=f3a2b1c0
```

### `read_message`
```
GET /api/?key=KEY&action=read_message&id=42&email=abc12345@mail.example.com&password=f3a2b1c0
```

### `get_domains`
```
GET /api/?key=KEY&action=get_domains
```

### `check_status` *(no key needed)*
```
GET /api/?action=check_status
```

---

## ⚙️ Preferred Domains (Per API Key)

Set in **Admin → Edit User → Preferred Mail Domains** or **User Dashboard → Settings**.

- If set: API key can ONLY generate emails on those domains
- If blank: API key can use ANY accessible public domain
- Useful for apps that should only use specific domains

---

## 🔒 Security Features

| Feature | Details |
|---------|---------|
| Email Verification | OTP sent on registration |
| Password Reset | OTP-based via email |
| Rate Limiting | Configurable login attempt limits |
| Account Lockout | Auto-lock after failed attempts |
| CSRF Protection | All forms protected |
| Domain Lock | Restrict API key to specific origin domain |
| API Usage Limits | Per-key daily/weekly limits |
| IMAP Disable | Disable server → no email creation or reading |
| Maintenance Mode | `SYSTEM_ONLINE=false` blocks everything |
| Data Protection | `.htaccess` blocks public access to `data/` |

---

## 🛠️ Maintenance Mode

In `.env`:
```
SYSTEM_ONLINE=false
MAINTENANCE_MESSAGE="Back soon!"
```
Blocks: API, user login/register, temp mail creation.
Admin panel still accessible.

---

## 🐛 Troubleshooting

| Problem | Solution |
|---------|---------|
| "No IMAP servers configured" | Add a server in Admin → Mail Servers |
| SMTP not working | Admin → Settings → Test SMTP. Check credentials |
| IMAP won't connect | Check `php-imap` extension enabled in cPanel |
| Emails not in inbox | Verify catch-all is configured on mail provider |
| 500 errors | Set `APP_DEBUG=true` in `.env` temporarily |
| OTP not received | Check spam; verify SMTP settings |

---

## 📋 Pages

| URL | Page |
|-----|------|
| `/index.php` | User login / register / dashboard |
| `/index.php?page=tempmail` | User's temp mail inbox |
| `/tempmail.php` | Public temp mail page |
| `/admin.php` | Admin panel |
| `/docs.php` | API documentation |
| `/tester.php` | Live API tester |
| `/api/` | REST API endpoint |

---

**TempMail Pro v2.1** — PHP 7.4+ · No Composer · Self-hosted
