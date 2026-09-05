<?php
/**
 * POST /api/delete-offer.php  {id, permanent?}  (form POST from My Offers)
 * Moves one of the learner's offers to the trash (so it can be restored later),
 * or erases it for good when permanent=1 (used by the trash's "Delete forever").
 * Returns to /offers/.
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
if ($id > 0) {
    if (!empty($_POST['permanent'])) {
        purge_offer(db(), $uid, $id);
    } else {
        delete_offer(db(), $uid, $id);
    }
}

redirect('/offers/');
