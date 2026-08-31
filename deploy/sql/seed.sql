-- =====================================================================
--  DIY Creator Starter Toolkit  ·  Seed data
--  Run AFTER schema.sql.
--  Payment fee table values are the source of truth for the fee
--  calculator (client + server read the same rows). Technical Spec 3.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+08:00';

-- ─── PAYMENT FEES ────────────────────────────────────────────
-- rate_percent is a percentage (3.000 = 3%). min_fee NULL = no minimum.
-- fixed_fee is added on top. These mirror the Pricing Confidence worksheet.

INSERT INTO payment_fees
  (method_key, label, rate_percent, min_fee, fixed_fee, sort_order, updated_at) VALUES
  ('gcash',        'GCash e-wallet',                 3.000, NULL,  11.00, 1, NOW()),
  ('maya',         'Maya',                           2.000, NULL,  11.00, 2, NOW()),
  ('grabpay',      'GrabPay',                        2.000, NULL,  11.00, 3, NOW()),
  ('shopeepay',    'ShopeePay',                      2.500, NULL,  11.00, 4, NOW()),
  ('qrph',         'QR Ph',                          1.500, 15.00, 11.00, 5, NOW()),
  ('bank_va',      'Bank transfer, virtual account', 1.000, 15.00, 11.00, 6, NOW()),
  ('card_domestic','Domestic card',                  3.500, NULL,  11.00, 7, NOW())
ON DUPLICATE KEY UPDATE
  label=VALUES(label), rate_percent=VALUES(rate_percent), min_fee=VALUES(min_fee),
  fixed_fee=VALUES(fixed_fee), sort_order=VALUES(sort_order), updated_at=NOW();

-- ─── WYZAI COACH CODES ───────────────────────────────────────
-- One code per trigger, one coach each. The `code` values below are
-- PLACEHOLDERS. Replace them with the five real WyzAI codes once Yza
-- creates the dedicated WyzQuest agency (admin screen does this at Stage E).
-- slot_capacity / warn_threshold: adjust after the one-minute slot test.

INSERT INTO wyzai_codes
  (trigger_key, code, coach_name, slot_capacity, slots_issued, warn_threshold, created_at) VALUES
  ('login',   'PLACEHOLDER-WELCOME',     'Welcome Buddy',      500, 0, 450, NOW()),
  ('gate_1',  'PLACEHOLDER-CLARITY',     'Clarity Coach',      500, 0, 450, NOW()),
  ('gate_2',  'PLACEHOLDER-CREATION',    'Creation Coach',     500, 0, 450, NOW()),
  ('gate_3',  'PLACEHOLDER-CREDIBILITY', 'Credibility Coach',  500, 0, 450, NOW()),
  ('package', 'PLACEHOLDER-COMMUNITY',   'Community Designer', 500, 0, 450, NOW());
