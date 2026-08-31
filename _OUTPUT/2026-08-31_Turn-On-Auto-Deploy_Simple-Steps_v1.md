# Turn on auto-deploy: simple steps
### For Yza. No coding. Just follow in order. Each step is one small thing.

You will do three things:
- **A.** Tidy up the file you were editing (30 seconds).
- **B.** Give GitHub your Hostinger login, once, so the Deployer robot can reach your site (5 minutes, all clicking).
- **C.** Do the one-time server setup so the website has a database (10 minutes).

Then you approve the change and watch it go live.

---

## A. Cancel the file you were typing

You started making a file called `main.yml` by hand. You do not need to. I
already made the real files.

1. On that GitHub page, look at the top right.
2. Click the grey **Cancel changes** button.
3. If it asks "are you sure", confirm. Done.

---

## B. Give the Deployer robot your Hostinger login (once)

A "secret" in GitHub is a locked box. You put a password in, and only the
robots can read it. Nobody can see it again, not even you. We add four.

1. Go to your repository page:
   `https://github.com/wyzlab/creatortoolkit`
2. Click **Settings** (top menu, the gear).
3. On the left, click **Secrets and variables**, then **Actions**.
4. Click the green **New repository secret** button.
5. Add the first one:
   - **Name**: `HOSTINGER_SSH_HOST`
   - **Secret**: `145.223.105.83`
   - Click **Add secret**.
6. Click **New repository secret** again and add the next. Repeat for all four:

   | Name (type exactly) | Secret (value) |
   |---|---|
   | `HOSTINGER_SSH_HOST` | `145.223.105.83` |
   | `HOSTINGER_SSH_PORT` | `65002` |
   | `HOSTINGER_SSH_USER` | `u451105086` |
   | `HOSTINGER_SSH_PASSWORD` | your Hostinger SSH password |

   > The SSH password is the one you use to log in to your server. If you do
   > not know it: in Hostinger hPanel go to **Advanced > SSH Access**, find
   > **Password**, click **Change**, set a new one, and use that here.

When you are done you should see four secrets listed. That is all for Part B.

---

## C. First-time server setup (the website needs a database)

The robot updates your *code* automatically, but the very first time we also
need to create the database and tell the site its password. This is the one
slightly techy part. You do it once.

### C1. Let your server read the code (add a "deploy key")

1. Go to `https://github.com/wyzlab/creatortoolkit`
2. Click **Settings** > on the left click **Deploy keys** > **Add deploy key**.
3. **Title**: type `Hostinger`.
4. **Key**: paste the long `ssh-rsa AAAA...` text (the one from your Hostinger
   SSH page). 
5. Leave "Allow write access" **unticked**. Click **Add key**.

### C2. Create the database (in Hostinger, all clicking)

You already made this. It is `u451105086_creatortoolkit`. Keep its **password**
handy for the next steps.

### C3. Log in to your server and paste one block

1. On your computer, open the **Terminal** app (Mac) or **PowerShell**
   (Windows).
2. Copy this line, paste it, press Enter:
   ```
   ssh -p 65002 u451105086@145.223.105.83
   ```
3. It asks for your password. Type your Hostinger SSH password (you will not
   see it as you type, that is normal) and press Enter.
4. Now copy this whole block, paste it, press Enter. It downloads the code and
   creates your secret keys automatically:
   ```
   cd ~
   git clone git@github.com:wyzlab/creatortoolkit.git
   cd ~/creatortoolkit/deploy/config
   php -r 'printf("<?php\nreturn [\n  \"code_pepper\" => %s,\n  \"csrf_salt\"   => %s,\n  \"ip_pepper\"   => %s,\n];\n", var_export(bin2hex(random_bytes(32)),true), var_export(bin2hex(random_bytes(32)),true), var_export(bin2hex(random_bytes(32)),true));' > secrets.local.php
   cp db.local.php.example db.local.php
   ```
   If it asks "Are you sure you want to continue connecting", type `yes` and
   Enter.

### C4. Put your database password in one file

1. Open the database file to edit it. Paste this, press Enter:
   ```
   nano db.local.php
   ```
2. You will see a small text editor. Change these three lines to your real
   values (use the arrow keys to move, delete and retype):
   ```
   'name' => 'u451105086_creatortoolkit',
   'user' => 'u451105086_creatortoolkit',
   'pass' => 'YOUR_DATABASE_PASSWORD',
   ```
3. Save it: press **Ctrl + O**, then **Enter**. Exit: press **Ctrl + X**.

### C5. Load the database tables and make your test codes

1. Paste these two lines (press Enter after each). Type your database password
   when asked:
   ```
   cd ~/creatortoolkit/deploy
   mysql -u u451105086_creatortoolkit -p u451105086_creatortoolkit < sql/schema.sql
   mysql -u u451105086_creatortoolkit -p u451105086_creatortoolkit < sql/seed.sql
   ```
2. Make 20 test access codes:
   ```
   php tools/gen-codes.php 20 dev-test
   ```
3. **Copy the 20 codes it prints and paste them somewhere safe.** They are
   shown only once. You will use one to log in and test.
4. Type `exit` and Enter to leave the server.

### C6. Point your subdomain at the website folder (in Hostinger)

1. In hPanel, go to **Subdomains**.
2. Find `toolkit.wyzcore.com`. Open its settings.
3. Set the **document root** (the folder it shows) to:
   ```
   creatortoolkit/deploy/public_html
   ```
4. Save.

---

## D. Approve the change and watch it go live

1. Open the pull request: `https://github.com/wyzlab/creatortoolkit/pull/1`
2. Wait for the **green tick** next to the checks (the Checker robot). If you
   see a red X, tell me and I will fix it before you merge.
3. Click the green **Merge pull request**, then **Confirm merge**.
4. Click the **Actions** tab at the top. You will see **Deploy to Hostinger**
   running. When it turns green, your site is live.

## E. Test it

1. Go to `https://toolkit.wyzcore.com`
2. Enter your email and one of your 20 codes, set a password.
3. You should land on your dashboard with Gate 1 open. 

If any step looks different from this, take a screenshot and send it to me. I
will tell you exactly what to click next.

---

### From now on, this is your whole workflow
1. You ask me for a change or the next stage.
2. I build it, test it, and open a pull request.
3. You look at it, wait for the green tick, and click **Merge**.
4. The site updates itself. You refresh `toolkit.wyzcore.com` and it is there.
