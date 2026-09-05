<?php
/**
 * POST /api/restore-offer.php  {id}  (form POST from the trash in My Offers)
 * Brings a trashed offer back to the live list, then returns to /offers/.
 */

declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/guard.php';
require_once __DIR__ . '/../inc/offers.php';

require_login();
$user = current_user();
$uid  = (int)$user['id'];

require_post();
csrf_check();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) { restore_offer(db(), $uid, $id); }

redirect('/offers/');
