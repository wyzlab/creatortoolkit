/* =====================================================================
   fee-calc.js  ·  the payment fee calculator module (Stage D)
   ---------------------------------------------------------------------
   ONE formula, one source. The fee table is delivered to the page from the
   server (generated from the payment_fees rows), and this module computes
   against it for instant feedback while typing. The stored value is still
   the server's, from /api/calc-fees.php. Never write the maths twice by hand.

   Placeholder in Stage A. The real implementation lands with the Pricing
   Confidence tool in Stage D and must match the PDF worked example to the
   peso (₱500 via GCash => ₱26.00 fee, ₱474 take-home).
   ===================================================================== */
export function computeFee(/* amount, method, feeTable */) {
  throw new Error('fee-calc: implemented in Stage D');
}
export default { computeFee };
