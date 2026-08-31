# Stage A Deployment Guide, DIY Creator Starter Toolkit
### 2026-08-31 | v1 | toolkit.wyzcore.com | Hostinger shared hosting

This is the foundation stage. When it is deployed you can claim a code, set a
password, land on the dashboard, and see Gate 1 open with Gates 2 and 3 locked.
No tool content yet. That is Stage B.

Everything here was verified locally against a real MySQL-family database and a
live PHP server before hand-off. See `_OUTPUT/2026-08-31_Stage-A_Test-Report_v1.md`.

---

## 1. What you are uploading

The deployable tree lives in the repo under `deploy/`. It maps to Hostinger
like this:

```
Hostinger home for the subdomain
├── config/          <-  deploy/config/     (ABOVE public_html, secrets live here)
├── private/         <-  deploy/private/    (the ten PDFs, above web root)
└── public_html/     <-  deploy/public_html/ (the website itself)
```

The `deploy/sql/` and `deploy/tools/` folders are run once during setup and do
not need to stay on the server, though it is fine to keep `tools/` above the web
root for later batches.

> Important: `config/` and `private/` must sit BESIDE `public_html`, not inside
> it. That is what keeps your database password and the PDFs off the public web.

---

## 2. The four PHP values you asked about, and exactly where they go

All of it goes in **`deploy/config/db.php`**. Open it and replace four blanks:

| Blank in db.php | Where to find it on Hostinger | Example |
|---|---|---|
| `host` | Almost always `localhost` on Hostinger | `localhost` |
| `name` | hPanel > Databases > MySQL Databases, the database name | `u123456789_toolkit` |
| `user` | Same screen, the database user | `u123456789_toolkit` |
| `pass` | The password you set for that user | your password |

Two safe ways to fill it:

- **On the server (recommended).** Upload the files, then edit
  `config/db.php` in Hostinger's File Manager. Nothing sensitive ever leaves the
  server.
- **Send them to me.** Paste the four values in chat and I will hand you a
  filled `db.php` to upload. Your call.

Then open **`deploy/config/secrets.php`** and set three long random strings.
Generate them once, on the server or any PHP prompt:

```
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Run it three times, paste one into each of `code_pepper`, `csrf_salt`,
`ip_pepper`. **Set `code_pepper` once and never change it**, or every access
code you have imported stops working.

`config/mail.php` and `config/ai.php` stay as placeholders until Stage E. The
site runs fine without them. Email is written to a log table instead of sent.

---

## 3. Upload steps (Hostinger File Manager)

1. In hPanel, create the subdomain `toolkit.wyzcore.com` if it does not exist,
   and note the document root it gives you (its `public_html`).
2. Create a MySQL 8 database and user. Copy the name, user, and password.
3. Upload the contents of `deploy/public_html/` into the subdomain's
   `public_html`. Turn on "Show hidden files" so `.htaccess` uploads too.
4. Upload `deploy/config/` and `deploy/private/` to the folder ONE LEVEL ABOVE
   `public_html`. If Hostinger will not let you go above `public_html` for this
   subdomain, tell me and I will switch the include paths to a protected folder
   inside it instead.
5. Fill `config/db.php` and `config/secrets.php` as in section 2.

---

## 4. Set up the database

From hPanel use phpMyAdmin, or SSH if you have it.

1. Import `deploy/sql/schema.sql` into your new database. It creates 15 tables.
2. Import `deploy/sql/seed.sql`. It loads the seven payment-fee rows and five
   placeholder WyzAI coach rows.
3. Mint your development access codes. Over SSH, from the `config` folder's
   sibling `tools` folder:
   ```
   php tools/gen-codes.php 20 dev-test
   ```
   It prints 20 codes ONCE. Copy them now. Only the hash is stored, so they
   cannot be recovered later. If you cannot run PHP over SSH on your plan, tell
   me and I will give you an alternative that runs through a one-time admin page.

---

## 5. Confirm it works (the Stage A acceptance)

1. Visit `https://toolkit.wyzcore.com`. You should see the claim and login card.
2. Claim with your email and one of the 20 codes. Set a password of at least 10
   characters. You should land on the dashboard.
3. The dashboard should show Gate 1 "Get Clear" open, with Gate 2 "Build Your
   Offer" and Gate 3 "Price, Launch, Sell" locked.
4. In the address bar, type `https://toolkit.wyzcore.com/gate2/one-page-offer.php`.
   You should be redirected to the dashboard, not shown the page.
5. Open it on a phone. The login and dashboard should read cleanly with no
   sideways scrolling.

If all five hold, Stage A is live and I can deploy Stage B onto it.

---

## 6. Security posture already in place

- Access codes stored as a keyed HMAC, never plaintext. Passwords via
  `password_hash()`.
- Every API uses PDO prepared statements, emulation off.
- CSRF token required on every state-changing POST.
- Rate limiting on code verification and login, per IP and per email.
- Session cookies HttpOnly, Secure, SameSite=Strict.
- CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy on every page.
- Gate access enforced server-side. A locked URL redirects, it does not render.
- The ten PDFs sit above the web root and are unreachable by direct path.

---

## 7. Placeholders still to fill, and when they matter

| Placeholder | File | Needed by |
|---|---|---|
| MySQL name, user, password | `config/db.php` | now, to deploy Stage A |
| code_pepper, csrf_salt, ip_pepper | `config/secrets.php` | now |
| WyzQuest agency id | `config/app.php` | Stage B live |
| The five WyzAI codes | seeded rows, admin screen | Stage B live |
| EmailIt SMTP | `config/mail.php` | Stage E |
| AI provider and key | `config/ai.php` | Stage E |

Nothing above blocks Stage A from going live except the database credentials and
the three secrets.
