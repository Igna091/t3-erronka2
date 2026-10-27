<?php

declare(strict_types=1);

// Logging out: a POST with the CSRF token, so another site can't log a user out. The session
// is emptied and gets a new id.

if (is_post() && csrf_valid($_POST['csrf'] ?? null)) {
    log_out();
    flash('Saioa itxi da.');
}
redirect('index.php');
