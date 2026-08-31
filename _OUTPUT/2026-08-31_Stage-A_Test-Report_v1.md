# Stage A Test Report, DIY Creator Starter Toolkit
### 2026-08-31 | v1

Verified locally against MariaDB 10.11 (MySQL 8 compatible DDL) and PHP 8.4's
built-in server, driving the real endpoints over HTTP with a cookie jar.

## Acceptance criteria

| # | Criterion | Result |
|---|---|---|
| 1 | A fresh database accepts the SQL without error | PASS. schema.sql created 15 tables, seed.sql loaded 7 fees + 5 coaches, no errors. |
| 2 | A test code claims, sets a password, lands on the dashboard | PASS. verify-code, then set-password returned `{ok, redirect:/dashboard.php}`, session established. |
| 3 | Dashboard shows Gate 1 open, Gates 2 and 3 locked | PASS. Gate 1 badge "Open", Gates 2 and 3 "Locked", verified in HTML and screenshots. |
| 4 | Typing a Gate 2 URL returns a redirect, not a page | PASS. `/gate2/one-page-offer.php` returned 302 to `/dashboard.php?locked=2`. Gate 3 likewise. |
| 5 | Renders correctly at 375, 768, 1280 | PASS. Screenshots captured at all three widths, no horizontal scroll, tap targets >= 48px. |

## Security checks

| Check | Result |
|---|---|
| CSRF required on POST | PASS. A POST without the token returned 419. |
| Access code stored as HMAC, never plaintext | PASS. `access_codes.code_lookup` holds the HMAC; `code_display` holds last 4 only. |
| Password via password_hash() | PASS. Verified `$2y$` hash in `users.password_hash`. |
| Rate limiting on verify-code and login | PASS. Sixth rapid attempt from one IP returned 429. |
| Generic error, no enumeration | PASS. Bad code, bad email, and bad password all return the same generic message. |
| Session cookie flags | PASS. `HttpOnly; SameSite=Strict` set. `Secure` sets under HTTPS (absent only on the local HTTP test). |
| Security headers | PASS. CSP with per-request nonce, X-Frame-Options, X-Content-Type-Options, Referrer-Policy present on every page. |
| No-session access redirects | PASS. `/dashboard.php` and gate tools without a session redirect to `/index.php?next=...`. |

## Behaviour checks

| Check | Result |
|---|---|
| Claim seeds everything in one transaction | PASS. 1 user, 1 user_profile (`{}`), 3 gate_progress rows (Gate 1 unlocked), code marked claimed. |
| Welcome Buddy WyzAI slot claimed once | PASS. 1 `wyzai_code_claims` row, `slots_issued` = 1. |
| A revisit never burns a second slot | PASS. Re-login left `slots_issued` at 1 (guaranteed by `uq_user_trigger`). |
| Welcome email queued, not sent | PASS. `email_log` has one `welcome` row with status `queued` (mailer disabled until Stage E). |
| Login, logout, re-login | PASS. |
| Password reset, single use | PASS. Valid token reset the password and logged in; reusing the token returned 400. |
| Optimistic profile version on save | Built in (`user_profile.version`, rejected on stale write). Exercised by the engine in Stage B. |

## Notes

- The mailer writes to `email_log` and does not send, by design, until EmailIt
  credentials arrive at Stage E.
- WyzAI coach codes are placeholder rows until the dedicated WyzQuest agency
  exists. The claim machinery works; it simply hands out placeholder codes for now.
- The fee calculator is a Stage D placeholder. The fee table it will read is
  already seeded and matches the Pricing worksheet values.
