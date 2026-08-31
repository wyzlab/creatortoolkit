# Connect GitHub to Hostinger, and set up the database
### 2026-08-31 | v1 | toolkit.wyzcore.com

This gets the toolkit onto your Hostinger subdomain straight from GitHub, and
sets up the database. It keeps the security model intact: your database
password and the ten PDFs stay above the web root, never reachable by URL.

The one thing to understand first: this app is not a flat folder of files. The
website lives in `deploy/public_html`, and `deploy/config` and `deploy/private`
must sit ONE LEVEL ABOVE the website folder. So the subdomain's document root
points at `deploy/public_html`, and Git clones the whole repo just above it.

---

## Part 1. Create the database (5 minutes)

1. hPanel > **Databases** > **MySQL Databases**.
2. Under "Create a New MySQL Database":
   - Database name: something like `toolkit`. Hostinger prefixes it, so the
     full name becomes `uXXXXXXXX_toolkit`.
   - Username: `toolkit` (becomes `uXXXXXXXX_toolkit`).
   - Password: generate a strong one and save it.
3. Create it. You now have three values: **database name, username, password**.
   The host is `localhost`. Keep these four for Part 4.

Leave the tables for now. We import them in Part 5.

---

## Part 2. Let Hostinger read your private GitHub repo

The repo `wyzlab/creatortoolkit` is private, so Hostinger needs read access
through a deploy key.

1. hPanel > **Advanced** > **SSH Access**. Turn SSH on if it is off, and copy
   your account's **public SSH key**. If there is no key there, hPanel's Git
   screen (Part 3) shows one to use, or you can generate one under SSH Access.
2. On GitHub, open the repo > **Settings** > **Deploy keys** > **Add deploy
   key**. Paste the Hostinger public key. Title it "Hostinger". Leave "Allow
   write access" unchecked (read-only is all it needs). Save.

That is the whole connection. Hostinger can now pull the repo over SSH.

> If your plan does not expose an SSH key for this, tell me and we will use the
> manual upload path in Part 6 instead. It reaches the same place.

---

## Part 3. Point Hostinger's Git at the repo

1. First, decide the branch. After you merge the open pull request into `main`,
   deploy from **`main`**. Until then you can deploy from the build branch
   `claude/diy-creator-toolkit-build-7l0kuo`. Deploying from `main` before the
   PR is merged gets you an empty site, so merge first.
2. hPanel > **Advanced** > **GIT**.
3. Fill in:
   - **Repository**: the SSH URL, `git@github.com:wyzlab/creatortoolkit.git`
   - **Branch**: `main` (or the build branch, per step 1)
   - **Directory / Install path**: a folder ABOVE your web root, not inside
     `public_html`. For example `creatortoolkit`. Hostinger clones the repo to
     `~/creatortoolkit`, so the website ends up at
     `~/creatortoolkit/deploy/public_html`.
4. Create. Hostinger clones the repo.
5. Turn on **Auto Deployment** on the same screen. Hostinger shows a webhook
   URL. Copy it, then on GitHub go to the repo > **Settings** > **Webhooks** >
   **Add webhook**, paste the URL, content type `application/json`, event
   "Just the push event". Now every push to the tracked branch redeploys.

---

## Part 4. Aim the subdomain at the website folder

The subdomain must serve `deploy/public_html`, not the repo root.

1. hPanel > **Websites** (or **Domains**) > **Subdomains**.
2. Find `toolkit.wyzcore.com`. Edit its **document root** (custom folder) to:
   `creatortoolkit/deploy/public_html`
   (the path is relative to your account home, matching the Git directory you
   chose in Part 3).
3. Save. Now:
   - `toolkit.wyzcore.com` serves `~/creatortoolkit/deploy/public_html`.
   - `~/creatortoolkit/deploy/config` and `~/creatortoolkit/deploy/private` sit
     one level above the web root, exactly as the app expects. The PHP includes
     resolve to them automatically. Nothing there is web-reachable.

> If Hostinger will not let you set the Git directory above `public_html` on
> your plan, tell me. There is a safe fallback: clone inside `public_html`, keep
> the same subdomain document root, and rely on the deny rules already shipped
> in `config/.htaccess` and `private/.htaccess`. I will confirm the paths for
> your exact layout.

---

## Part 5. Put your credentials in, and load the database

Your real credentials go in `*.local.php` files, which are gitignored, so a
future deploy never overwrites them.

Over SSH (or Hostinger's File Manager), in `~/creatortoolkit/deploy/config`:

1. Copy `db.local.php.example` to `db.local.php` and fill in the four database
   values from Part 1.
2. Copy `secrets.local.php.example` to `secrets.local.php`. Generate three
   random strings and paste one into each:
   ```
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```
   Run it three times. Set `code_pepper` once and never change it.

Then load the schema:

3. hPanel > **Databases** > **phpMyAdmin** > open your database.
4. **Import** tab > choose `deploy/sql/schema.sql` > Go. It creates 15 tables.
5. **Import** again with `deploy/sql/seed.sql`. It loads the fee table and the
   five WyzAI coach placeholder rows.

Mint your access codes (needs SSH):

6. `php ~/creatortoolkit/deploy/tools/gen-codes.php 20 dev-test`
   It prints 20 codes once. Copy them now. Only the hash is stored.

> No SSH on your plan? Tell me and I will add a one-time, admin-only web page
> that generates the codes, then we remove it. The gen script needs PHP, which
> a browser page can also run safely behind the admin login.

---

## Part 6. Manual upload alternative (if you skip Git)

If Git integration is not on your plan, upload over File Manager or FTP:

1. Upload the contents of `deploy/public_html/` into the subdomain's
   `public_html` (turn on "Show hidden files" so `.htaccess` goes too).
2. Upload `deploy/config/` and `deploy/private/` to the folder one level ABOVE
   `public_html`.
3. Do Part 5 for credentials and the database.

Same result, just without auto-deploy on push.

---

## Part 7. Confirm it works

1. Visit `https://toolkit.wyzcore.com`. You should see the claim and login card.
2. Claim with your email and one of the 20 codes, set a password, and you land
   on the dashboard with Gate 1 open.
3. Finish the three Gate 1 tools and Gate 2 unlocks, the three PDFs become
   downloadable, and the Clarity Coach handover appears.
4. Type `https://toolkit.wyzcore.com/gate2/one-page-offer.php`. You should be
   redirected to the dashboard, not shown the page.

If any step misbehaves, send me the screen and I will sort it.

---

## What I need from you to tailor this

- The four database values (name, user, password, host), if you want me to hand
  you a filled `db.local.php` rather than typing it on the server.
- Whether your plan has **SSH access** (it changes the deploy-key and
  code-minting steps).
- Confirm you want to deploy from `main` after merging the pull request, which
  is what I recommend.
