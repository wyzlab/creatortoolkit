# Stage B Test Report, Gate 1
### 2026-08-31 | v1 | Ideal Client Avatar Kit, Course Clarity Framework, Will It Sell? Validation Check

Verified against MariaDB 10.11 and PHP 8.4, driving the real endpoints, and
rendered in Chromium at 390px to confirm the client engine.

## Acceptance criteria

| Criterion | Result |
|---|---|
| Finish the Avatar Kit and the Clarity Framework opens with the learner fields already filled, still editable | PASS. Browser screenshot shows Name, role, pain, and want carried from the Avatar, each marked "Carried from your earlier answers", each editable. |
| Finish all three and Gate 2 unlocks | PASS. gate_progress: Gate 1 completed, Gate 2 unlocked. Dashboard shows Gate 2 "Open". |
| Three PDFs become downloadable | PASS. pdf_unlocks holds the three Gate 1 tools. download-pdf streams the avatar PDF (200, application/pdf); a Gate 2 tool PDF returns 403. |
| The Clarity Coach code appears once | PASS. wyzai_code_claims has one gate_1 row, slots_issued = 1. A revisit and re-completion did not add a second. |
| Close the browser mid-tool and reopen: every answer is still there | PASS. Typed a value, waited past the 2s autosave debounce, reloaded the page, the value was still in the field. |

## Mechanism checks

| Check | Result |
|---|---|
| Carry-forward prefill resolves from the shared profile | PASS. Clarity learner fields resolve from avatar.name / avatar.role / avatar.urgent_problem / avatar.desired_outcome. |
| Every pre-filled field stays editable, edits write back to the profile | PASS. |
| Stale-field flow: change an upstream value, downstream is flagged | PASS. Editing avatar.urgent_problem set is_stale on clarity.learner_pain; filling that field cleared the flag. |
| A stale field is never silently overwritten | PASS. The value is shown with a dismissible "update this to match?" note; nothing changes until the learner accepts. |
| Validation verdict bands | PASS. commitments >= threshold gives GO; blank commitments gives PENDING (completes anyway, per the two-to-four-week test); 0 after a test gives RESET; below threshold gives REFINE. |
| Result page carries Terms of Use and Proof of Authorship with the right product id | PASS. Validation result carried product_id 14642; Avatar 14636; Clarity 14578. |
| Autosave endpoint on 2s debounce and blur | PASS. |
| Gate close is one transaction and idempotent | PASS. Re-completing a tool in a closed gate did not re-close it or burn a second slot. |
| Gate summary page renders summary, coach code once, and unlocked PDFs | PASS. |
| Server re-checks gate and session on every tool API call | PASS. get-profile, save-progress, complete-tool all call api_require_login and api_require_gate. |
| Client engine renders on mobile (390px) | PASS. Screenshots for the Avatar and Clarity tools. |

## Architecture notes

- The tool definition is canonical in PHP (`inc/tooldefs.php`). The page emits
  it to the browser as JSON; the engine renders from it, and the server
  validates, scores, and builds results from the same definition. One source,
  no client/server drift. This deviates from the spec's `js/tools/[slug].js`
  file-per-tool layout on purpose, for the reason the spec itself gives about
  the fee formula: never write one rule in two places.
- The WyzAI coach codes are placeholder rows until the dedicated WyzQuest
  agency exists. The handover machinery is live; it hands out the placeholder
  code today and the gate summary says the code is being set up.
- Email is queued to email_log, not sent, until Stage E.
