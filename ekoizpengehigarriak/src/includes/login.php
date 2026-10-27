<?php

declare(strict_types=1);

// Logging in: a prepared statement, password_verify() against an Argon2id hash, a limit on wrong
// passwords, the same message whether the email or the password was wrong, and a new session id.

if (current_user() !== null) {
    echo '<div class="center"><h5>Saioa hasita duzu jada.</h5></div>';

    return;
}

$error = '';
$username = '';
if (is_post()) {
    $username = post_string('username');
    if (! csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Eskaera ez da baliozkoa. Saiatu berriro.';
    } else {
        $result = attempt_login($username, post_string('password'));
        if ($result === 'ok') {
            redirect('index.php');
        }
        $error = $result === 'throttled'
            ? 'Saiakera oker gehiegi. Itxaron ' . LOGIN_FAILURE_MINUTES . ' minutu eta saiatu berriro.'
            : 'Erabiltzailea edo pasahitza ez da zuzena.';
    }
}
?>
<div class="center">
    <fieldset class="narrow">
    <legend><b>Login</b></legend>
        <?php if ($error !== '') { ?><p class="error"><?= e($error) ?></p><?php } ?>
        <form action="index.php?action=login" method="post">
            <?= csrf_field() ?>
            <br>
            <label for="username">Username/Email:</label> <input type="email" id="username" name="username" value="<?= e($username) ?>" maxlength="254" autocomplete="username" required><br>
            <label for="password">Password:</label> <input type="password" id="password" name="password" maxlength="128" autocomplete="current-password" required><br>
            <br><input type="submit" value="Login"><br>
        </form>
    </fieldset>
</div>
