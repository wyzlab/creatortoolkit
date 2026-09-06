<?php
/**
 * inc/webhook-util.php — helpers for reading a wyzcore purchase payload.
 *
 * Shared by the live webhook (api/wyzcore-webhook.php) and the admin log viewer
 * (api/admin/webhook-log.php) so both interpret a delivery identically. Pure
 * functions over a decoded JSON array; no request or DB state.
 */

declare(strict_types=1);

/** Pull an email out of a payload, trying common shapes. */
function find_email(array $p): string
{
    $keys = ['email', 'customer_email', 'buyer_email', 'user_email', 'contact_email'];
    foreach ($keys as $k) {
        if (!empty($p[$k]) && is_string($p[$k])) { return normalize_email($p[$k]); }
    }
    foreach (['customer', 'buyer', 'user', 'data', 'order', 'contact'] as $group) {
        if (isset($p[$group]) && is_array($p[$group])) {
            $found = find_email($p[$group]);
            if ($found !== '') { return $found; }
        }
    }
    return '';
}

/** Pull an event/type string out of a payload. */
function find_event(array $p): string
{
    foreach (['event', 'type', 'event_type', 'topic', 'action', 'status'] as $k) {
        if (!empty($p[$k]) && is_string($p[$k])) { return $p[$k]; }
    }
    if (isset($p['data']) && is_array($p['data'])) { return find_event($p['data']); }
    return '';
}

/**
 * Collect every product/item title in the payload, trying common shapes:
 * a top-level product name, and arrays of line items each with a title/name.
 * Recurses into wrapper groups (data/order/...). Returns a flat list of titles.
 */
function find_products(array $p): array
{
    $titles = [];
    $titleKeys = ['product', 'product_name', 'product_title', 'item', 'item_name',
                  'item_title', 'title', 'name', 'plan', 'plan_name', 'offer', 'offer_name'];
    foreach ($titleKeys as $k) {
        if (!empty($p[$k]) && is_string($p[$k])) { $titles[] = $p[$k]; }
    }
    // Arrays of line items.
    foreach (['line_items', 'items', 'products', 'order_items', 'lines', 'cart'] as $k) {
        if (!empty($p[$k]) && is_array($p[$k])) {
            foreach ($p[$k] as $item) {
                if (is_string($item)) { $titles[] = $item; }
                elseif (is_array($item)) { $titles = array_merge($titles, find_products($item)); }
            }
        }
    }
    // Recurse into common wrapper objects.
    foreach (['data', 'order', 'sale', 'purchase', 'subscription', 'checkout'] as $group) {
        if (isset($p[$group]) && is_array($p[$group])) {
            $titles = array_merge($titles, find_products($p[$group]));
        }
    }
    return array_values(array_unique(array_filter($titles, 'is_string')));
}

/** Collect product ids from the payload (product_id, item id, line-item ids). */
function find_product_ids(array $p): array
{
    $ids = [];
    foreach (['product_id', 'product_code', 'item_id', 'sku', 'id'] as $k) {
        if (isset($p[$k]) && (is_string($p[$k]) || is_int($p[$k]))) { $ids[] = (string)$p[$k]; }
    }
    foreach (['line_items', 'items', 'products', 'order_items', 'lines', 'cart'] as $k) {
        if (!empty($p[$k]) && is_array($p[$k])) {
            foreach ($p[$k] as $item) {
                if (is_array($item)) { $ids = array_merge($ids, find_product_ids($item)); }
            }
        }
    }
    foreach (['data', 'order', 'sale', 'purchase', 'subscription', 'checkout'] as $group) {
        if (isset($p[$group]) && is_array($p[$group])) {
            $ids = array_merge($ids, find_product_ids($p[$group]));
        }
    }
    return array_values(array_unique(array_filter($ids, fn($v) => $v !== '')));
}

/**
 * Does this sale identify the DIY Creator Toolkit? True if any product id
 * matches toolkit_product_ids, OR any product title contains a configured
 * phrase (case-insensitive). If the payload carried NO product titles or ids we
 * do NOT assume it is the toolkit — better to ignore an unrecognised sale than
 * to grant wrongly.
 */
function purchase_is_toolkit(array $titles, array $ids = []): bool
{
    // Product id is the most reliable signal (titles can be renamed/translated).
    $wantIds = array_map('strval', APP['toolkit_product_ids'] ?? []);
    foreach ($ids as $id) {
        if (in_array((string)$id, $wantIds, true)) { return true; }
    }
    if (!$titles) { return false; }
    $needles = APP['toolkit_product_match'] ?? [];
    $needles[] = 'diy-creator-starter-toolkit';
    foreach ($titles as $t) {
        $hay = mb_strtolower((string)$t);
        foreach ($needles as $needle) {
            $needle = mb_strtolower(trim((string)$needle));
            if ($needle !== '' && strpos($hay, $needle) !== false) { return true; }
        }
    }
    return false;
}
