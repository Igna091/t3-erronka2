<?php

declare(strict_types=1);

// The user's account: their photo and a new password; admins also add and delete users. Every
// change is a POST with the CSRF token, and index.php has already checked that someone is
// logged in (lib/routes.php).

$user = current_user();
if ($user === null) {
    redirect('index.php?action=login');
}
$errors = [];

if (is_post()) {
    if (! csrf_valid($_POST['csrf'] ?? null)) {
        $errors['form'] = 'Eskaera ez da baliozkoa. Saiatu berriro.';
    } elseif (post_string('op') === 'password') {
        $find = db()->prepare('SELECT password FROM users WHERE id = ?');
        $find->execute([$user['id']]);
        $problem = password_problem(post_string('newpass'), post_string('confnewpass'), $user['username']);
        if (! password_verify(post_string('oldpass'), (string) $find->fetchColumn())) {
            $errors['oldpass'] = 'Uneko pasahitza ez da zuzena.';
        } elseif ($problem !== null) {
            $errors['newpass'] = $problem;
        } else {
            // By id, not by the old hash; and a new session_version ends every other session
            db()->prepare('UPDATE users SET password = ?, session_version = session_version + 1 WHERE id = ?')
                ->execute([hash_password(post_string('newpass')), $user['id']]);
            $version = db()->prepare('SELECT session_version FROM users WHERE id = ?');
            $version->execute([$user['id']]);
            log_in($user['id'], (int) $version->fetchColumn());
            flash('Pasahitza aldatu da. Beste saio guztiak itxi dira.');
            redirect('index.php?action=account');
        }
    } elseif (post_string('op') === 'adduser' && $user['is_admin']) {
        $newUser = mb_strtolower(trim(post_string('newuser')));
        $newName = trim(post_string('newname'));
        $problem = password_problem(post_string('newuserpass'), post_string('newuserpass2'), $newUser);
        if (! is_email($newUser)) {
            $errors['newuser'] = 'Email helbidea ez da zuzena.';
        } elseif ($newName === '' || mb_strlen($newName) > 60 || ! is_plain_text($newName)) {
            $errors['newname'] = 'Izenak 1 eta 60 karaktere artean izan behar ditu.';
        } elseif ($problem !== null) {
            $errors['newuserpass'] = $problem;
        } else {
            try {
                db()->prepare('INSERT INTO users (username, password, izena) VALUES (?, ?, ?)')
                    ->execute([$newUser, hash_password(post_string('newuserpass')), $newName]);
                flash('Erabiltzailea sortu da.');
                redirect('index.php?action=account');
            } catch (PDOException $duplicate) {
                if ($duplicate->getCode() !== '23000') {
                    throw $duplicate;
                }
                $errors['newuser'] = 'Email hori erregistratuta dago jada.';
            }
        }
    } elseif (post_string('op') === 'deleteuser' && $user['is_admin']) {
        $id = post_id('user_id');
        if ($id === null || $id === $user['id']) {
            $errors['delete'] = 'Ezin duzu zure kontua ezabatu.';
        } else {
            $find = db()->prepare('SELECT irudia FROM users WHERE id = ?');
            $find->execute([$id]);
            $photo = $find->fetchColumn();
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            if (is_string($photo) && $photo !== '') {
                delete_image($photo, config()['profiles_dir']);
            }
            flash('Erabiltzailea ezabatu da.');
            redirect('index.php?action=account');
        }
    }
}

$problem = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        echo '<p class="error">' . e($errors[$key]) . '</p>';
    }
};
?>
<div class="center">
    <?php $problem('form'); ?>
    <table class="account">
        <tr>
            <td class="right">
                <?php if ($user['irudia'] !== '') { ?>
                    <fieldset class="narrow">
                    <legend><b>Zure irudia</b></legend>
                    <img src="index.php?action=photo" alt="Zure profileko irudia" class="photo">
                    </fieldset><br>
                <?php } ?>
                <fieldset class="narrow">
                <legend><b>Change Password</b></legend>
                <form action="index.php?action=account" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="op" value="password">
                    <label for="oldpass">Current Password:</label> <input type="password" id="oldpass" name="oldpass" autocomplete="current-password" required><br>
                    <?php $problem('oldpass'); ?>
                    <label for="newpass">New Password:</label> <input type="password" id="newpass" name="newpass" minlength="<?= PASSWORD_MIN_LENGTH ?>" maxlength="<?= PASSWORD_MAX_LENGTH ?>" autocomplete="new-password" required><br>
                    <label for="confnewpass">Confirm New Password:</label> <input type="password" id="confnewpass" name="confnewpass" autocomplete="new-password" required><br>
                    <?php $problem('newpass'); ?>
                    <br><div class="center"><input type="submit" value="Change"></div>
                </form>
                </fieldset>
            </td>
            <?php if ($user['is_admin']) { ?>
            <td class="left">
                <fieldset class="narrow">
                <legend><b>Add User</b></legend>
                <form action="index.php?action=account" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="op" value="adduser">
                    <label for="newuser">New user's email:</label> <input type="email" id="newuser" name="newuser" maxlength="254" required><br>
                    <?php $problem('newuser'); ?>
                    <label for="newname">New user's name:</label> <input type="text" id="newname" name="newname" maxlength="60" required><br>
                    <?php $problem('newname'); ?>
                    <label for="newuserpass">New user's password:</label> <input type="password" id="newuserpass" name="newuserpass" minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required><br>
                    <label for="newuserpass2">Repeat it:</label> <input type="password" id="newuserpass2" name="newuserpass2" autocomplete="new-password" required><br>
                    <?php $problem('newuserpass'); ?>
                    <br><div class="center"><input type="submit" value="Add"></div>
                </form>
                </fieldset><br>

                <fieldset class="narrow">
                <legend><b>Delete User</b></legend>
                <?php $problem('delete'); ?>
                <table class="users">
                    <?php foreach (rows('SELECT id, username FROM users ORDER BY username') as $row) { ?>
                    <tr>
                        <td class="box"><?= (int) $row['id'] === $user['id'] ? '<b>' . e($row['username']) . '</b>' : e($row['username']) ?></td>
                        <td class="box action">
                            <?php if ((int) $row['id'] === $user['id']) { ?>
                                <del>[delete]</del>
                            <?php } else { ?>
                                <form class="inline" action="index.php?action=account" method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="op" value="deleteuser">
                                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="link">[delete]</button>
                                </form>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } ?>
                </table>
                </fieldset>
            </td>
            <?php } ?>
        </tr>
    </table>
</div>
