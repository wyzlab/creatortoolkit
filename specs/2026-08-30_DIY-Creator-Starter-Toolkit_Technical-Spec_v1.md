# DIY Creator Starter Toolkit, Interactive Web App
## Approval Spec Part 2: Technical Specification
### v1 | 2026-08-30 | WyzLab Studio Originals, presented under WyzCore Academy

**Build Order step 4.** Schema, endpoints, gate logic, security model, email templates, and the shared tool engine. Approve this and I build the foundation plus Gate 1.

Companion to:
- `2026-08-30_DIY-Creator-Starter-Toolkit_Field-Map-and-Carry-Forward_v1.md`
- `2026-08-30_DIY-Creator-Starter-Toolkit_AI-and-WyzAI-Architecture_v1.md`

---

## 1. EVERYTHING LOCKED SO FAR

| Decision | Value |
|---|---|
| Database | Hostinger MySQL |
| Backend | PHP on Hostinger shared hosting |
| Access | Pre-generated code batch, plus a password set on first login |
| Gating | Three sequential gates. All tools in a gate required to open the next. |
| Gate wording | Get Clear / Build Your Offer / Price, Launch, Sell. Internal keys stay Clarity, Creation, Credibility. |
| Gate 1 order | Avatar Kit, then Clarity Framework, then Validation Check |
| 20-Point scoring | Points 1 to 19 score one each. Point 20 scores one only when 20a, 20b, 20c and 20d all tick. Maximum 20. |
| Avatar photo | Out of v1 |
| PDFs | Unlock per tool completed |
| Email | EmailIt, authenticated SMTP, via PHPMailer |
| AI | Pre-filled WyzAI prompt on every tool. Server-side synthesis at each gate close and on the full package. Four calls per buyer, hard ceiling. |
| WyzAI widget | `agents.wyzquestpro.com/role/widget.js`, embedded in the toolkit pages |
| WyzAI codes | Five, one coach each. Welcome Buddy at login, Clarity, Creation, Credibility at gate close, Community Designer at full completion. |
| Deployment | Its own subdomain |
| Results | Shown on screen and emailed |
| Contact | hello@wyzcore.com |

**Subdomain recommendation: `toolkit.wyzcore.com`.** Every one of the ten PDFs tells the reader to find things at wyzcore.com, the WyzAI chatbot lives in that world, and the checkout is there. Putting the toolkit on the wyzlabsolutions.com side splits the story across two domains for no gain. Say the word if you want `toolkit.wyzlabsolutions.com` instead. It is one config value and a DNS record either way, but it needs deciding before the WyzQuest allowlist entry and the SSL certificate.

---

## 2. TWO CORRECTIONS TO THE ORIGINAL BRIEF

Both are small and both matter.

**2.1 Access codes cannot use `password_hash()`.** The brief says to hash codes with `password_hash()`, same as passwords. That works for passwords, because you verify a hash you already found by email. It does not work for codes, because `password_hash()` salts every hash differently, so there is nothing to look the code up by. You would have to load every row and test each one.

The fix: codes get a deterministic keyed hash for lookup.

```
code_lookup = hash_hmac('sha256', normalize(code), CODE_PEPPER)
```
`CODE_PEPPER` is a long random string in `/config/secrets.php`, above `public_html`. `normalize()` uppercases, strips spaces, and strips hyphens, so `0-h3g-qks` and `0H3GQKS` both match. The column is unique and indexed, lookup is one query, and the plaintext code is still never stored. Passwords keep `password_hash()` exactly as the brief says.

**2.2 Tool pages must be `.php`, not `.html`.** The brief lists `gate1/ gate2/ gate3/` as HTML files. A static HTML file cannot check whether a gate is unlocked, so anyone who types `/gate3/pricing-confidence.html` reaches the page. A JavaScript guard is not a fix, because it runs after the page is already served.

The fix is small: the same files with a `.php` extension and a three line guard at the top.

```php
<?php require_once __DIR__ . '/../inc/guard.php'; require_gate(3); ?>
```

Content stays HTML. Nothing else changes. This is what makes the quality checklist line "gates cannot be skipped by URL manipulation" actually true rather than aspirational.

---

## 3. DATABASE SCHEMA

MySQL 8, `utf8mb4_unicode_ci`, InnoDB throughout.

```sql
-- ─── ACCOUNTS ────────────────────────────────────────────────

CREATE TABLE users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email           VARCHAR(190) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NULL,
  role            ENUM('learner','admin') NOT NULL DEFAULT 'learner',
  status          ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at      DATETIME NOT NULL,
  last_login_at   DATETIME NULL,
  INDEX idx_users_status (status)
) ENGINE=InnoDB;

CREATE TABLE access_codes (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code_lookup       CHAR(64) NOT NULL UNIQUE,   -- HMAC-SHA256, see 2.1
  code_display      VARCHAR(20) NULL,           -- last 4 only, for your admin view
  product_slug      VARCHAR(64) NOT NULL DEFAULT 'diy-creator-starter-toolkit',
  batch_label       VARCHAR(80) NULL,
  issued_to_email   VARCHAR(190) NULL,
  claimed_by_user_id INT UNSIGNED NULL,
  claimed_at        DATETIME NULL,
  expires_at        DATETIME NULL,
  status            ENUM('unclaimed','claimed','revoked','expired') NOT NULL DEFAULT 'unclaimed',
  created_at        DATETIME NOT NULL,
  INDEX idx_ac_status (status),
  FOREIGN KEY (claimed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE password_resets (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64) NOT NULL UNIQUE,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── THE CARRY-FORWARD OBJECT ────────────────────────────────

CREATE TABLE user_profile (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL UNIQUE,
  profile_json JSON NOT NULL,
  version      INT UNSIGNED NOT NULL DEFAULT 1,
  updated_at   DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

`version` increments on every write and is sent back to the client. A save that arrives with a stale version is rejected, so two open tabs cannot silently overwrite each other. On a phone-first audience where people leave tabs open for days, this is not theoretical.

```sql
-- ─── PROGRESS ────────────────────────────────────────────────

CREATE TABLE tool_sessions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  gate_number  TINYINT UNSIGNED NOT NULL,
  tool_slug    VARCHAR(64) NOT NULL,
  current_step TINYINT UNSIGNED NOT NULL DEFAULT 1,
  answers_json JSON NOT NULL,
  status       ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
  started_at   DATETIME NOT NULL,
  updated_at   DATETIME NOT NULL,
  completed_at DATETIME NULL,
  UNIQUE KEY uq_user_tool (user_id, tool_slug),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE gate_progress (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  gate_number     TINYINT UNSIGNED NOT NULL,
  tools_required  TINYINT UNSIGNED NOT NULL,
  tools_completed TINYINT UNSIGNED NOT NULL DEFAULT 0,
  unlocked_at     DATETIME NULL,
  completed_at    DATETIME NULL,
  UNIQUE KEY uq_user_gate (user_id, gate_number),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tool_results (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id   INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  tool_slug    VARCHAR(64) NOT NULL,
  result_json  JSON NOT NULL,
  result_html  MEDIUMTEXT NOT NULL,
  emailed_at   DATETIME NULL,
  created_at   DATETIME NOT NULL,
  INDEX idx_tr_user (user_id),
  FOREIGN KEY (session_id) REFERENCES tool_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE gate_results (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  gate_number   TINYINT UNSIGNED NOT NULL,  -- 0 = full package
  summary_json  JSON NOT NULL,
  summary_html  MEDIUMTEXT NOT NULL,
  ai_paragraph  TEXT NULL,
  emailed_at    DATETIME NULL,
  created_at    DATETIME NOT NULL,
  UNIQUE KEY uq_user_gate_result (user_id, gate_number),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pdf_unlocks (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id           INT UNSIGNED NOT NULL,
  tool_slug         VARCHAR(64) NOT NULL,
  unlocked_at       DATETIME NOT NULL,
  download_count    INT UNSIGNED NOT NULL DEFAULT 0,
  last_downloaded_at DATETIME NULL,
  UNIQUE KEY uq_user_pdf (user_id, tool_slug),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

```sql
-- ─── CONFIGURABLE DATA, NOT HARD-CODED ───────────────────────

CREATE TABLE payment_fees (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  method_key   VARCHAR(32) NOT NULL UNIQUE,
  label        VARCHAR(80) NOT NULL,
  rate_percent DECIMAL(6,3) NOT NULL,
  min_fee      DECIMAL(10,2) NULL,
  fixed_fee    DECIMAL(10,2) NOT NULL,
  sort_order   TINYINT UNSIGNED NOT NULL,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  updated_at   DATETIME NOT NULL
) ENGINE=InnoDB;

INSERT INTO payment_fees
  (method_key, label, rate_percent, min_fee, fixed_fee, sort_order, updated_at) VALUES
  ('gcash',        'GCash e-wallet',                 3.000, NULL,  11.00, 1, NOW()),
  ('maya',         'Maya',                           2.000, NULL,  11.00, 2, NOW()),
  ('grabpay',      'GrabPay',                        2.000, NULL,  11.00, 3, NOW()),
  ('shopeepay',    'ShopeePay',                      2.500, NULL,  11.00, 4, NOW()),
  ('qrph',         'QR Ph',                          1.500, 15.00, 11.00, 5, NOW()),
  ('bank_va',      'Bank transfer, virtual account', 1.000, 15.00, 11.00, 6, NOW()),
  ('card_domestic','Domestic card',                  3.500, NULL,  11.00, 7, NOW());

CREATE TABLE wyzai_codes (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trigger_key    ENUM('login','gate_1','gate_2','gate_3','package') NOT NULL,
  code           VARCHAR(40) NOT NULL,
  coach_name     VARCHAR(80) NOT NULL,
  slot_capacity  INT UNSIGNED NOT NULL DEFAULT 500,
  slots_issued   INT UNSIGNED NOT NULL DEFAULT 0,
  warn_threshold INT UNSIGNED NOT NULL DEFAULT 450,
  warned_at      DATETIME NULL,
  is_pooled      TINYINT(1) NOT NULL DEFAULT 0,
  status         ENUM('active','exhausted','retired') NOT NULL DEFAULT 'active',
  replaced_by_id INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL,
  INDEX idx_wc_trigger_status (trigger_key, status)
) ENGINE=InnoDB;

CREATE TABLE wyzai_code_claims (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  wyzai_code_id  INT UNSIGNED NOT NULL,
  trigger_key    VARCHAR(20) NOT NULL,
  claimed_at     DATETIME NOT NULL,
  UNIQUE KEY uq_user_trigger (user_id, trigger_key),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (wyzai_code_id) REFERENCES wyzai_codes(id)
) ENGINE=InnoDB;
```

`uq_user_trigger` is what guarantees a revisit never burns a second slot. The claim is the source of truth, `slots_issued` is a denormalised counter kept in step inside the same transaction.

```sql
-- ─── OPERATIONS ──────────────────────────────────────────────

CREATE TABLE email_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  email_type  VARCHAR(40) NOT NULL,
  to_address  VARCHAR(190) NOT NULL,
  subject     VARCHAR(255) NOT NULL,
  status      ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  sent_at     DATETIME NULL,
  error       TEXT NULL,
  created_at  DATETIME NOT NULL,
  INDEX idx_el_status (status)
) ENGINE=InnoDB;

CREATE TABLE ai_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  trigger_key VARCHAR(20) NOT NULL,
  model       VARCHAR(80) NULL,
  tokens_in   INT UNSIGNED NULL,
  tokens_out  INT UNSIGNED NULL,
  status      ENUM('ok','failed','skipped') NOT NULL,
  error       TEXT NULL,
  created_at  DATETIME NOT NULL,
  UNIQUE KEY uq_ai_user_trigger (user_id, trigger_key)
) ENGINE=InnoDB;

CREATE TABLE rate_limits (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier   VARCHAR(64) NOT NULL,   -- hashed ip, or hashed email
  action       VARCHAR(32) NOT NULL,
  attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  window_start DATETIME NOT NULL,
  locked_until DATETIME NULL,
  UNIQUE KEY uq_rl (identifier, action)
) ENGINE=InnoDB;
```

`uq_ai_user_trigger` in `ai_log` is the hard ceiling on AI spend. Four rows per user, maximum, enforced by the database rather than by trusting the endpoint.

---

## 4. PHP ENDPOINTS

All return JSON. All state-changing calls require a CSRF token. All use PDO prepared statements. No exceptions.

### 4.1 Auth

| Endpoint | Method | In | Out | Notes |
|---|---|---|---|---|
| `/api/verify-code.php` | POST | `email`, `code` | `{valid, needs_password}` | Rate limited, 5 per 15 min per IP and per email. Timing-safe compare. Same generic error for a bad code and a bad email, so it cannot be used to enumerate buyers. |
| `/api/set-password.php` | POST | `email`, `code`, `password` | `{ok, redirect}` | One transaction: create user, claim code, seed `user_profile`, unlock Gate 1, claim the Welcome Buddy code, queue the welcome email. Minimum 10 characters, no other rules, because complexity rules push people to reuse. |
| `/api/login.php` | POST | `email`, `password` | `{ok, redirect}` | Rate limited. Regenerates the session id. |
| `/api/logout.php` | POST | | `{ok}` | Destroys the session and clears the cookie. |
| `/api/request-reset.php` | POST | `email` | `{ok}` | Always returns ok, whether or not the email exists. |
| `/api/reset-password.php` | POST | `token`, `password` | `{ok}` | Token single use, 60 minute expiry. |

### 4.2 Tools

| Endpoint | Method | In | Out |
|---|---|---|---|
| `/api/get-profile.php` | GET | `tool_slug` | `{profile_version, prefill:{field:{value, source_tool, is_stale}}, answers, current_step}` |
| `/api/save-progress.php` | POST | `tool_slug`, `step`, `answers`, `profile_version` | `{ok, saved_at, profile_version}` |
| `/api/complete-tool.php` | POST | `tool_slug`, `answers`, `profile_version` | `{ok, result, pdf_unlocked, gate_complete}` |
| `/api/complete-gate.php` | POST | `gate_number` | `{ok, summary, ai_paragraph, wyzai_code, coach_name, next_gate_unlocked}` |
| `/api/get-results.php` | GET | | `{gates:[...], tools:[...], profile}` |
| `/api/calc-fees.php` | POST | `mode`, `amount`, `method_key` | `{headline, fee, take_home, all_methods:[...]}` |
| `/api/download-pdf.php` | GET | `tool_slug` | the file, or 403 |

**`save-progress.php` fires on a 2 second debounce after any change, and on blur.** Every step, every field. Progress surviving a closed browser is a checklist item, and autosave is the only way it is true.

**`calc-fees.php` is server-side on purpose.** The same maths also runs client-side for instant feedback while typing, but the stored value is the server's. Two implementations of one formula is a bug waiting to happen, so the client version is generated from the same fee table at page load rather than written twice by hand.

**`download-pdf.php` is the only route to a PDF.** The files sit in `/private/assets/` above the web root. The endpoint checks the session, checks `pdf_unlocks`, increments the counter, and streams the file. `.htaccess` blocks any direct path.

### 4.3 Admin

Behind `role = 'admin'`, on a separate rate limit.

| Endpoint | Purpose |
|---|---|
| `/api/admin/import-codes.php` | Paste or upload a batch of access codes. Hashes and inserts. Reports duplicates rather than failing the batch. |
| `/api/admin/wyzai-codes.php` | View slot counters, paste a replacement code, retire the old one. |
| `/api/admin/fees.php` | Edit the fee table when Xendit changes rates. |
| `/api/admin/stats.php` | Buyers, gate completion counts, drop-off by tool, failed emails, failed AI calls. |

The drop-off view is worth more than it sounds. It tells you which of the ten tools is losing people, which is the single most useful number this app can give you.

---

## 5. GATE LOGIC

```
Gate 1, Clarity      3 tools   ideal-client-avatar, course-clarity-framework, course-idea-validation
Gate 2, Creation     4 tools   one-page-offer, content-to-course-sparker, course-design-checklist,
                               filipino-creators-starter-kit
Gate 3, Credibility  3 tools   pricing-confidence, first-launch-checklist, discovery-call-script
```

**Unlock rules.**
- Gate 1 unlocks at account creation.
- Gate N+1 unlocks when `gate_progress.tools_completed == tools_required` for Gate N.
- Tools inside a gate can be done in any order. The recommended order is what the dashboard shows, and pre-fill quality degrades gracefully if someone jumps around.
- Every gate page and tool page carries the server-side guard from section 2.2. Every API call re-checks. Two layers, because one is not enough.

**Completion rules per tool.** A tool completes when every field marked required in the field map has a non-empty value. Scores and checklists never block completion, they shape the result page and the recovery path. The one exception is the Validation Check, which completes with `verdict = PENDING` when the user has not run their test yet, because that test takes two to four weeks and nobody should be stuck behind it.

**What happens on gate close.** One transaction:
1. Mark `gate_progress.completed_at`.
2. Build the gate summary from `user_profile` and the gate's `tool_results`.
3. Claim the WyzAI code for that trigger, insert into `wyzai_code_claims`, increment `slots_issued`.
4. Unlock the next gate.
5. Write `gate_results`.
6. Queue the gate email.
7. Fire the AI call. Outside the transaction, and failure is logged, never fatal.

**Revisiting.** Every completed tool stays open and editable. Editing a completed tool rewrites its result, updates the profile, and marks downstream fields stale per the edit propagation rule in the field map. It does not re-lock a gate, and it does not burn a second WyzAI slot.

---

## 6. SECURITY MODEL

| Control | Implementation |
|---|---|
| Credentials | `/config/db.php`, `/config/mail.php`, `/config/ai.php`, `/config/secrets.php`. All above `public_html`. Never readable over HTTP, never referenced from client JavaScript. |
| SQL | PDO, prepared statements, `ATTR_EMULATE_PREPARES = false`. |
| Passwords | `password_hash()` with the default algorithm. `password_needs_rehash()` on every login. |
| Access codes | HMAC-SHA256 with a server-side pepper. See 2.1. |
| Sessions | `HttpOnly`, `Secure`, `SameSite=Strict`. Id regenerated on login and on privilege change. Idle timeout 30 days, because this is a tool people return to over weeks. |
| CSRF | Per-session token, checked on every POST. |
| Rate limiting | 5 attempts per 15 minutes on `verify-code` and `login`, per IP and per email, then a 30 minute cooldown. Admin endpoints tighter. |
| HTTPS | Forced in `.htaccess`, plus HSTS once the certificate is confirmed good. |
| Output | Everything user-typed is escaped on render. Result HTML is built from a whitelist, never from raw input. This matters because result HTML gets emailed. |
| Headers | `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, and a CSP that allows `agents.wyzquestpro.com` for the widget and `fonts.googleapis.com` plus `fonts.gstatic.com` for type. |
| Uploads | None. The avatar photo is out, and that removes an entire category of risk. |

**One honest limitation.** Shared hosting means no control over the PHP version beyond what hPanel offers, and no server-level rate limiting. Everything above is application level. That is appropriate for this threat model, which is code sharing and casual URL poking, not a targeted attack.

---

## 7. EMAIL

PHPMailer over EmailIt SMTP. Every send writes to `email_log` before it is attempted, so a failure is visible rather than silent.

| Type | Trigger | Carries |
|---|---|---|
| `welcome` | Password set | What the three gates are, the Welcome Buddy code, and one link straight into Gate 1 |
| `gate_1_complete` | Gate 1 close | The Clarity summary, the AI paragraph, the Clarity Coach code, the three PDFs now unlocked |
| `gate_2_complete` | Gate 2 close | Same shape, Creation Coach, four PDFs |
| `gate_3_complete` | Gate 3 close | Same shape, Credibility Coach, three PDFs |
| `package_complete` | All three gates | The full package, the Community Designer code, and one clear next action |
| `password_reset` | Reset request | A single-use link, 60 minute expiry |
| `admin_slot_warning` | A WyzAI code hits its threshold | Which code, how many slots left, what to do |
| `admin_code_pool_low` | Under 25 unclaimed access codes | A nudge to load the next batch |

**Every learner email carries the Free Resource Terms of Use and the Proof of Authorship block**, with the per-tool product IDs from Appendix A of the field map.

**Deliverability, before we trust it.** Once you give me the EmailIt credentials I want to send test messages to a Gmail address, a Yahoo address, and one Outlook address, and confirm SPF, DKIM and DMARC are aligned for the sending domain. The code email is the product. It landing in spam is not a bug we can afford to discover from a support ticket.

**HTML plus plain text on every send.** Filipino mobile mail clients handle plain text more reliably than most people assume, and a text part improves spam scoring for free.

---

## 8. FILE STRUCTURE

```
/config/                      ABOVE public_html
├── db.php
├── mail.php
├── ai.php
└── secrets.php               CODE_PEPPER, CSRF salt

/private/
└── assets/                   the ten source PDFs, never web-reachable

public_html/                  the toolkit subdomain root
├── index.php                 login and code claim
├── set-password.php
├── reset.php
├── dashboard.php             the three-gate journey map
├── gate1/
│   ├── ideal-client-avatar.php
│   ├── course-clarity-framework.php
│   └── course-idea-validation.php
├── gate2/
│   ├── one-page-offer.php
│   ├── content-to-course-sparker.php
│   ├── course-design-checklist.php
│   └── filipino-creators-starter-kit.php
├── gate3/
│   ├── pricing-confidence.php
│   ├── first-launch-checklist.php
│   └── discovery-call-script.php
├── results/
│   ├── gate.php              gate summary
│   └── package.php           the full package
├── admin/
│   └── index.php
├── inc/
│   ├── guard.php             require_login(), require_gate()
│   ├── head.php              meta, fonts, CSS, CSP
│   ├── footer.php            WyzAI widget, terms, proof of authorship
│   └── result-blocks.php
├── api/                      the endpoints in section 4
├── css/
│   ├── style.css             tokens and layout
│   └── components.css
├── js/
│   ├── main.js
│   ├── auth.js
│   ├── tool-engine.js        the shared engine
│   ├── fee-calc.js
│   └── tools/[slug].js       ten config files
├── images/
└── .htaccess
```

---

## 9. THE TOOL ENGINE

This is the decision that makes ten tools finishable instead of ten separate builds.

`tool-engine.js` owns everything common: rendering a step from config, autosave, validation, the pre-fill and stale-field flow, scoring, the result page, and the progress bar. Each tool is a config object, not a program.

```js
// js/tools/one-page-offer.js
export default {
  slug: 'one-page-offer',
  gate: 2,
  title: 'One-Page Offer Template',
  productId: 14639,
  publishedOn: 'July 28, 2026',
  steps: [
    {
      title: 'Who it is for',
      fields: [
        { key: 'offer_name',    type: 'text',     label: 'Offer name', required: true },
        { key: 'offer_who',     type: 'textarea', label: 'Who is it for',
          hint: 'Name one specific person and the moment they are in.',
          prefillFrom: ['avatar.role', 'avatar.name'], required: true },
        { key: 'offer_problem', type: 'textarea', label: 'The problem',
          prefillFrom: 'avatar.urgent_problem', required: true },
      ],
      stuck: 'Picture one real person. Their job, their situation, the moment they are in.'
    },
    // ...
  ],
  scoring: {
    type: 'checklist', mode: 'all-required',
    items: ['check_one_page','check_specific_who','check_result_destination',
            'check_price_one_number','check_say_one_breath'],
    pass:  'You have an offer you can sell by tonight.',
    fail:  'Name the unticked item and link to the field that fixes it.'
  },
  writesToProfile: {
    'offer.name': 'offer_name', 'offer.who': 'offer_who',
    'offer.problem': 'offer_problem', 'offer.result': 'offer_result',
    'offer.how': 'offer_how', 'offer.price': 'offer_price',
    'offer.proof': 'offer_proof'
  },
  wyzaiPrompt: { template: 'one-page-offer', fills: { /* ... */ } },
  result: { template: 'six-part-offer', printable: true }
};
```

**Three scoring types cover all ten tools:** `checklist` (all required, or banded by count), `banded` (the 20-Point and the Validation Check, with per-band verdict and recovery copy), and `none`. The Pricing tool adds one calculator module.

**Mobile is the constraint, not an afterthought.** One field group per screen, large tap targets, no horizontal scroll, autosave so a dropped connection costs nothing. Tested at 375, 768 and 1280. The Discovery Call Script gets extra attention, because its whole purpose is being held in one hand during a live call.

**Brand tokens.** I will read `WyzLab_Studios_Branding_Guidelines_REVISED_April14.md` before writing a line of CSS and derive every token from it. Nothing invented, no hard-coded hex outside `:root`.

---

## 10. BUILD SEQUENCE

**Stage A, foundation.** Schema SQL, config templates, `guard.php`, all auth endpoints, `tool-engine.js`, `style.css` and `components.css` from the brand file, login, set-password, and the dashboard journey map. Ships with placeholder WyzAI codes and the mailer stubbed to log rather than send.

**Stage B, Gate 1.** All three tools, the gate summary, PDF unlocking, the WyzAI code handover. This is the staging test, and it is the one that proves the carry-forward chain actually works, because the Avatar Kit feeding the Clarity Framework is the first real test of it.

**Stage C, Gate 2.** Four tools. The 20-Point Checklist is the largest single build in the app, ten steps and twenty-three inputs.

**Stage D, Gate 3.** Three tools, including the fee calculator and the call-script result page.

**Stage E, finish.** Full package page, admin screens, email templates live, AI synthesis wired, deliverability tested.

Each stage ships with its own Hostinger upload instructions and a deployment checklist.

---

## 11. WHAT IS STILL OPEN, AND WHAT IT BLOCKS

| # | Item | Blocks |
|---|---|---|
| 1 | Subdomain name, `toolkit.wyzcore.com` or the wyzlabsolutions one | DNS, SSL, WyzQuest allowlist. Needed before Stage A deploys, not before it is written. |
| 2 | Hostinger MySQL database name, user, password | Stage A deploy |
| 3 | The five WyzAI codes | Stage B live. Placeholders work until then. |
| 4 | WyzQuest domain allowlist entry | Stage B live |
| 5 | The one-minute slot test result | Sets `warn_threshold`. Not blocking. |
| 6 | EmailIt SMTP host, port, encryption, username, password, sender | Stage E. Mailer logs instead of sending until then. |
| 7 | AI provider and key | Stage E. Gate summaries render complete without it. |
| 8 | The access code batch, or a go-ahead for me to generate the format | Stage B live |

**Nothing on this list blocks me starting.** Stages A and B can be written in full against placeholders, which means the moment you have the database credentials there is something real to look at.

---

*WyzLab Solutions | From Sacrifice to Success. From Local to World-Class.*
