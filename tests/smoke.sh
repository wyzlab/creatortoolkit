#!/usr/bin/env bash
# smoke.sh — end-to-end check of the core journey against a running instance.
# Deterministic (curl + JSON asserts), no browser. Used by CI and runnable
# locally. Requires: a served app, a fresh DB with schema+seed, and one
# unclaimed access code.
#
#   BASE=http://127.0.0.1:8899 CODE=ABC-DEF-GHI bash tests/smoke.sh
#
# Exits non-zero on the first failed assertion.

set -euo pipefail

BASE="${BASE:-http://127.0.0.1:8899}"
CODE="${CODE:?set CODE to an unclaimed access code}"
EMAIL="${EMAIL:-smoke$(date +%s)@example.com}"
JAR="$(mktemp)"
PW="smoketest12345"

pass() { echo "  ok  $1"; }
fail() { echo "FAIL: $1"; exit 1; }

jqget() { python3 -c "import sys,json;d=json.load(sys.stdin);print(d$1)"; }

csrf() {
  curl -s -c "$JAR" -b "$JAR" "$BASE/index.php" -o /tmp/_idx.html
  grep -o 'csrf-token" content="[^"]*"' /tmp/_idx.html | sed 's/.*content="//;s/"//'
}

echo "smoke: $BASE  email=$EMAIL"
CT="$(csrf)"
[ -n "$CT" ] || fail "no CSRF token on index"
pass "index served, CSRF present"

post() { curl -s -b "$JAR" -c "$JAR" "$BASE/api/$1" -H 'Content-Type: application/json' -H "X-CSRF-Token: $CT" -d "$2"; }

# 1. verify-code
V="$(post verify-code.php "{\"email\":\"$EMAIL\",\"code\":\"$CODE\"}")"
[ "$(echo "$V" | jqget "['valid']")" = "True" ] || fail "verify-code not valid: $V"
[ "$(echo "$V" | jqget "['needs_password']")" = "True" ] || fail "verify-code needs_password false: $V"
pass "verify-code valid"

# 2. set-password (claims, creates account, logs in)
S="$(post set-password.php "{\"email\":\"$EMAIL\",\"code\":\"$CODE\",\"password\":\"$PW\"}")"
[ "$(echo "$S" | jqget "['ok']")" = "True" ] || fail "set-password failed: $S"
pass "claim + set-password"

# 3. dashboard: Gate 1 open, Gate 2 locked
D="$(curl -s -b "$JAR" "$BASE/dashboard.php")"
echo "$D" | grep -q "Get Clear" || fail "dashboard missing Gate 1"
echo "$D" | grep -q 'badge--open">Open' || fail "Gate 1 not open"
echo "$D" | grep -q 'badge--locked">Locked' || fail "Gate 2/3 not locked"
pass "dashboard: Gate 1 open, later gates locked"

# 4. URL guard: a Gate 2 tool must redirect, not render
G2="$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' "$BASE/gate2/one-page-offer.php")"
[ "$G2" = "302" ] || fail "gate2 tool did not redirect (got $G2)"
pass "gate2 URL redirects (302)"

# 5. complete the three Gate 1 tools
post complete-tool.php '{"tool_slug":"ideal-client-avatar","answers":{"avatar_name":"Smoke","avatar_quote":"q","role_business":"baker","top_goal":"launch","urgent_problem":"pricing","desired_outcome":"ten students","positioning":"I help X go from A to B"}}' >/dev/null
pass "avatar completed"
post complete-tool.php '{"tool_slug":"course-clarity-framework","answers":{"niche":"baking","learner_name":"Smoke","learner_role":"baker","learner_pain":"pricing","learner_want":"students","offer_from":"a","offer_to":"b"}}' >/dev/null
pass "clarity completed"
C3="$(post complete-tool.php '{"tool_slug":"course-idea-validation","answers":{"offer_type":"Online course","idea_sentence":"a class","audience":"bakers","outcome":"launch","threshold":"10","threshold_number":10,"commitments_number":12,"signals":{"sig_painful":true}}}')"
[ "$(echo "$C3" | jqget "['gate_complete']")" = "True" ] || fail "gate not complete after 3 tools: $C3"
[ "$(echo "$C3" | jqget "['result']['json']['verdict']")" = "GO" ] || fail "verdict not GO: $C3"
pass "validation completed, Gate 1 closed, verdict GO"

# 6. PDF unlock/lock
P1="$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' "$BASE/api/download-pdf.php?tool_slug=ideal-client-avatar")"
[ "$P1" = "200" ] || fail "avatar PDF not downloadable (got $P1)"
P2="$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' "$BASE/api/download-pdf.php?tool_slug=one-page-offer")"
[ "$P2" = "403" ] || fail "locked PDF was reachable (got $P2)"
pass "PDF unlock (200) and lock (403)"

rm -f "$JAR" /tmp/_idx.html
echo "SMOKE PASSED"
