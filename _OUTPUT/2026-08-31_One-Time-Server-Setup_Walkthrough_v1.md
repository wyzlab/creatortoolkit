# One-time server setup: gentle walkthrough
### For Yza. You do this once. Follow one step at a time. I tell you what you will see.

You are going to:
1. Make sure your server can read the code (a one-click GitHub thing).
2. Open a black text window on your computer.
3. Type your server password to log in.
4. Paste three short blocks.
5. Copy your 20 codes.

That is the whole thing. Take it slow. If anything on your screen looks
different from what I describe, stop and send me a screenshot.

---

## Step 0. First, add the "deploy key" (one time, in the browser)

This lets your server download the code from GitHub.

1. Go to `https://github.com/wyzlab/creatortoolkit`
2. Click **Settings** (top, gear icon).
3. On the left, click **Deploy keys**.
4. If you already see a key named "Hostinger", you are done, skip to Step 1.
5. Otherwise click **Add deploy key**:
   - **Title:** `Hostinger`
   - **Key:** paste your long `ssh-rsa AAAA...` text (the one from your
     Hostinger SSH page).
   - Leave "Allow write access" **unticked**.
   - Click **Add key**.

---

## Step 1. Open the black window

- **On a Mac:** press **Command + Spacebar**, type `Terminal`, press **Enter**.
- **On Windows:** click the **Start** button, type `PowerShell`, press **Enter**.

A black (or dark blue) window opens with some text and a blinking cursor. This
is where you paste things.

---

## Step 2. Log in to your server

1. Copy this line exactly:
   ```
   ssh -p 65002 u451105086@145.223.105.83
   ```
2. Click in the black window, paste it, press **Enter**.
3. The very first time, it may say:
   *"Are you sure you want to continue connecting?"* Type `yes` and press
   **Enter**.
4. It says **`password:`**. Type your Hostinger SSH password and press
   **Enter**.
   - **Important:** you will NOT see anything as you type the password. No
     dots, no stars. That is normal and correct. Just type it and press Enter.
   - If you do not know this password: in Hostinger hPanel go to
     **Advanced > SSH Access > Password > Change**, set a new one, and use it.
5. When you see a line ending in `~$` (something like
   `u451105086@us-phx-web1102:~$`), you are logged in. 

---

## Step 3. Download the code and make your secret keys

Copy this **whole block** (all the lines together), paste it into the black
window, press **Enter**:

```
cd ~
[ -d creatortoolkit ] || git clone git@github.com:wyzlab/creatortoolkit.git
cd ~/creatortoolkit && git pull
cd ~/creatortoolkit/deploy/config
php -r 'printf("<?php\nreturn [\n  \"code_pepper\" => %s,\n  \"csrf_salt\"   => %s,\n  \"ip_pepper\"   => %s,\n];\n", var_export(bin2hex(random_bytes(32)),true), var_export(bin2hex(random_bytes(32)),true), var_export(bin2hex(random_bytes(32)),true));' > secrets.local.php
cat > db.local.php <<'PHP'
<?php
return [
  'host'    => 'localhost',
  'name'    => 'u451105086_creatortoolkit',
  'user'    => 'u451105086_creatortoolkit',
  'pass'    => 'PASTE_YOUR_DB_PASSWORD_HERE',
  'charset' => 'utf8mb4',
];
PHP
echo "STEP 3 DONE"
```

When you see **`STEP 3 DONE`**, move on.

> If instead you see **`Permission denied (publickey)`**, the deploy key in
> Step 0 is missing or wrong. Go back and add it, then paste this block again.
>
> If you see **`php: command not found`**, type `php8.2 -v` and press Enter. If
> that works, tell me and I will give you the block with `php8.2`.

---

## Step 4. Type in your database password

1. Paste this and press **Enter**:
   ```
   nano ~/creatortoolkit/deploy/config/db.local.php
   ```
2. A simple editor opens showing the file. Use the **arrow keys** to move to
   the line:
   ```
   'pass'    => 'PASTE_YOUR_DB_PASSWORD_HERE',
   ```
3. Delete `PASTE_YOUR_DB_PASSWORD_HERE` (keep the two single quotes) and type
   your real database password in its place.
4. Save: press **Ctrl + O**, then **Enter**.
5. Exit: press **Ctrl + X**.

You are back at the `~$` prompt.

---

## Step 5. Load the database and make your codes

Paste these three lines one block, press **Enter**:

```
cd ~/creatortoolkit/deploy
mysql -u u451105086_creatortoolkit -p u451105086_creatortoolkit < sql/schema.sql
mysql -u u451105086_creatortoolkit -p u451105086_creatortoolkit < sql/seed.sql
```

- After each `mysql` line it asks for a password: type your **database**
  password (the same one from Step 4) and press Enter. Nothing shows as you
  type, that is normal.

Then make your 20 access codes:

```
php tools/gen-codes.php 20 dev-test
```

It prints 20 codes like `ABC-2KD-9MN`. **Select them, copy them, and paste them
into a note somewhere safe.** They are shown only once. You will use one to log
in and test your site.

Then type `exit` and press **Enter** to leave the server.

> If `mysql: command not found` appears, don't worry: you can load the database
> the browser way instead. Tell me and I will walk you through phpMyAdmin
> (upload `schema.sql` then `seed.sql`). You would still run the
> `php tools/gen-codes.php` line over SSH for the codes.

---

## Step 6. Point your subdomain at the website (in Hostinger, browser)

1. In hPanel, open **Subdomains**.
2. Find `toolkit.wyzcore.com` and open its settings.
3. Set its **document root** (folder) to:
   ```
   creatortoolkit/deploy/public_html
   ```
4. Save.

---

## Done. Now test it

1. Go to `https://toolkit.wyzcore.com`
2. Enter your email and one of your 20 codes, choose a password.
3. You should see your dashboard with Gate 1 open.

If any screen looks different from these steps, screenshot it and send it to
me. I will tell you the next click.
