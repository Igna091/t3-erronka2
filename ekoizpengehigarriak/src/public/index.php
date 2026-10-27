<?php

declare(strict_types=1);

// The only PHP file the web server runs (DocumentRoot is this public/ folder). It starts the
// session, picks the page from the allowlist in lib/routes.php, checks who may see it, and draws
// the page inside the shop's header and footer. The pages themselves are in ../includes/.

require __DIR__ . '/../lib/app.php';

// An error is logged for the admins and shown to the visitor as one plain sentence, never with
// SQL, paths or a stack trace
set_exception_handler(static function (Throwable $error): void {
    error_log((string) $error);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="eu"><head><meta charset="utf-8"><title>Errorea</title>'
        . '<link rel="stylesheet" href="style.css"></head><body><div class="center"><h3>'
        . 'Errore bat gertatu da. Saiatu berriro geroago.</h3><p><a href="index.php">Hasiera</a></p></div></body></html>';
});

// Pages can show personal details, so no copy is kept in caches
header('Cache-Control: no-store');

if (start_session(config()['session_idle_minutes'])) {
    flash('Saioa iraungi da. Hasi saioa berriro.', 'error');
}

$action = $_GET['action'] ?? null;
$route = route_for($action);
$user = current_user();

if ($route === null) {
    http_response_code(404);
} elseif (! may_open($route, $user)) {
    if ($user === null) {
        flash('Hasi saioa orri hori ikusteko.', 'error');
        redirect('index.php?action=login');
    }
    // Logged in, but not an admin: the page doesn't exist for them
    http_response_code(404);
    $route = null;
}

// The profile photo is a picture, not a page
if ($route !== null && $route['file'] === 'photo.php') {
    require __DIR__ . '/../includes/photo.php';
    exit;
}

// Buffered, so a page can still redirect after the header is drawn
ob_start();
$enpresa = config()['enpresa'];
$flash = take_flash();
$keyword = is_string($_GET['keyword'] ?? null) ? $_GET['keyword'] : '';

?>
<!DOCTYPE html>
<html lang="eu">
<head>
<meta charset="utf-8">
<title><?= e($enpresa) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="center">
<table class="banner">
<tr>
<td>
<img src="logo.png" height="110" width="115" alt="">
</td>
<td>
<hr>
<h1><?= e($enpresa) ?></h1><br>
<hr>
</td>
</tr>
</table>
<hr class="rule">

<?php if ($user !== null) { ?>
<h3>Ongi Etorri <?= e($user['username']) ?>
 | <a href="index.php?action=account">Kontua</a>
<?php if ($user['is_admin']) { ?> | <a href="index.php?action=updel">Igo/Ezabatu</a><?php } ?>
 | <form class="inline" method="post" action="index.php?action=logout"><?= csrf_field() ?><button type="submit" class="link">Saioa itxi</button></form>
 | <a href="index.php">Hasiera</a></h3>
<?php } else { ?>
<h3><a href="index.php?action=register">Erregistratu</a> | <a href="index.php?action=login">Saioa hasi</a> | <a href="index.php">Hasiera</a></h3>
<?php } ?>

<form name="search" method="get" action="index.php" id="search">
    <input type="hidden" name="action" value="search">
    <input type="search" name="keyword" value="<?= e($keyword) ?>" maxlength="30" aria-label="Bilatu">
    <input type="submit" value="Bilatu">
</form>

<hr class="rule">
<br>

<?php if ($flash !== null) { ?>
<p class="flash flash-<?= $flash['type'] === 'error' ? 'error' : 'ok' ?>"><?= e($flash['text']) ?></p>
<?php } ?>

<?php
if ($route === null) {
    echo '<h3>Ez da orria aurkitu.</h3>';
} else {
    require __DIR__ . '/../includes/' . $route['file'];
}
?>
</div>
<br>
<hr class="rule">
<div class="center">
    <b><?= e($enpresa) ?> <?= e(config()['version']) ?></b> | <a href="index.php?action=about">Enpresari buruz</a><br>
</div>
</body>
</html>
<?php
ob_end_flush();
