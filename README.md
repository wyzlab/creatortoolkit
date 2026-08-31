# DIY Creator Starter Toolkit

An interactive, gated web app that replaces ten downloadable PDFs with one
connected journey. The learner's answers carry forward across three gates.

WyzLab Studio Originals, presented under WyzCore Academy. Deployed to Hostinger
shared hosting on its own subdomain, `toolkit.wyzcore.com`.

## Repository layout

```
deploy/                     the deployable application
├── config/                 ABOVE public_html on the server (secrets, app settings)
│   ├── db.php  mail.php  ai.php  secrets.php  app.php
├── private/assets/         the ten source PDFs, above the web root
├── public_html/            the website
│   ├── index.php  set-password.php  reset.php  dashboard.php
│   ├── gate1/ gate2/ gate3/   guarded tool pages (interactive from Stage B)
│   ├── results/ admin/
│   ├── inc/                bootstrap, db, guard, csrf, ratelimit, mailer, wyzai,
│   │                       head, footer, result-blocks, tools registry
│   ├── api/                auth + tool + admin endpoints
│   ├── css/ js/ images/
│   └── .htaccess
├── sql/                    schema.sql, seed.sql
└── tools/                  gen-codes.php (mint access codes, run once)

specs/                      the approved specs we have, plus reconstructed notes
_source/text/               extracted text of the ten source PDFs (reference)
_OUTPUT/                    dated deliverables: deployment guide, test reports, screens
```

## Build stages

| Stage | Scope | Status |
|---|---|---|
| A | Foundation: schema, auth, guard, engine core, CSS, dashboard | Done, verified |
| B | Gate 1 tools: Avatar Kit, Clarity Framework, Validation Check | Done, verified |
| C | Gate 2 tools: Offer, Idea Sparker, 20-Point, Starter Kit | |
| D | Gate 3 tools: Pricing, Launch Checklist, Discovery Script | |
| E | Full package, admin, live email, AI synthesis, deliverability | |

## The tool engine

`js/tool-engine.js` owns everything common to the ten tools: step rendering,
autosave (2s debounce plus blur), prefill and the stale-field flow, validation,
scoring, the progress bar, completion, and the result view. Each tool is a
config object in `js/tools/[slug].js`, not a program. Three scoring types cover
all ten: `checklist`, `banded`, `none`. The Pricing tool adds one calculator.

## Running it

Fill `config/db.php` and `config/secrets.php`, import `sql/schema.sql` and
`sql/seed.sql`, mint codes with `tools/gen-codes.php`, then serve `public_html`
with PHP. See `_OUTPUT/2026-08-31_Stage-A_Deployment-Guide_v1.md`.

## Security model

Keyed-HMAC access codes, hashed passwords, PDO prepared statements throughout,
CSRF on every POST, per-IP and per-email rate limiting, HttpOnly/Secure/Strict
session cookies, a strict CSP, and server-side gate enforcement. Credentials and
PDFs live above the web root. Details in the Technical Spec, section 6.
