<?php
/**
 * POST /api/delete-offer.php  {id}  (form POST from My Offers)
 * Deletes one of the learner's saved offers, then returns to /offers/.
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
if ($id > 0) { delete_offer(db(), $uid, $id); }

redirect('/offers/');
