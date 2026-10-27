<?php

declare(strict_types=1);

// Signing up. Every field is checked (lib/validation.php) and stored with a prepared statement;
// the password as an Argon2id hash. The photo is optional, checked and redrawn, and kept outside
// the web root (storage/perfiles), where only its owner can see it (action=photo).

if (current_user() !== null) {
    echo '<div class="center"><h5>Saioa hasita duzu jada.</h5></div>';

    return;
}

$labels = [
    'email' => 'Email/username',
    'firstname' => 'Izena',
    'lastname' => 'Abizena',
    'city' => 'Hiria',
    'stateProv' => 'Lurraldea',
    'country' => 'Herrialdea',
    'postcode' => 'Postakodea',
    'telephone' => 'Telefonoa',
];
$data = array_fill_keys(array_keys($labels), '');
$error = [];

if (is_post()) {
    $input = $_POST['data'] ?? [];
    [$data, $error] = validate_registration(is_array($input) ? $input : []);

    if (! csrf_valid($_POST['csrf'] ?? null)) {
        $error['form'] = 'Eskaera ez da baliozkoa. Saiatu berriro.';
    }

    $irudia = '';
    $hasPhoto = is_array($_FILES['imagen'] ?? null) && ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($error === [] && $hasPhoto) {
        try {
            $irudia = store_upload($_FILES['imagen'], config()['profiles_dir']);
        } catch (UploadRejected $rejected) {
            $error['imagen'] = $rejected->getMessage();
        }
    }

    if ($error === []) {
        try {
            db()->prepare(
                'INSERT INTO users (username, password, izena, abizena, hiria, lurraldea, herrialdea, postakodea, telefonoa, irudia)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $data['email'], hash_password($data['password']), $data['firstname'], $data['lastname'],
                $data['city'], $data['stateProv'], $data['country'], $data['postcode'], $data['telephone'], $irudia,
            ]);
            flash('Kontua sortu da. Orain saioa has dezakezu.');
            redirect('index.php?action=login');
        } catch (PDOException $duplicate) {
            // 23000: the email is already registered (users.username is unique)
            if ($duplicate->getCode() !== '23000') {
                throw $duplicate;
            }
            delete_image($irudia, config()['profiles_dir']);
            $error['email'] = 'Email hori erregistratuta dago jada.';
        }
    }
}

$field = static function (string $name, string $label, string $type, string $value, string $autocomplete) use ($error): void {
    ?>
    <p>
        <label for="<?= e($name) ?>"><?= e($label) ?>: </label>
        <input type="<?= e($type) ?>" id="<?= e($name) ?>" name="data[<?= e($name) ?>]" value="<?= e($value) ?>" autocomplete="<?= e($autocomplete) ?>" required>
        <?php if (isset($error[$name])) { ?><span class="error"><?= e($error[$name]) ?></span><?php } ?>
    </p>
    <?php
};
?>
<div class="content">
<br>
<div class="register">
    <h2>Erregistroa egin</h2>
    <br>
    <b>Sartu zure datuak.</b>
    <br>
    <?php if (isset($error['form'])) { ?><p class="error"><?= e($error['form']) ?></p><?php } ?>
    <form action="index.php?action=register" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php
        $field('email', $labels['email'], 'email', $data['email'], 'email');
        $field('firstname', $labels['firstname'], 'text', $data['firstname'], 'given-name');
        $field('lastname', $labels['lastname'], 'text', $data['lastname'], 'family-name');
        $field('city', $labels['city'], 'text', $data['city'], 'address-level2');
        $field('stateProv', $labels['stateProv'], 'text', $data['stateProv'], 'address-level1');
        $field('country', $labels['country'], 'text', $data['country'], 'country-name');
        $field('postcode', $labels['postcode'], 'text', $data['postcode'], 'postal-code');
        $field('telephone', $labels['telephone'], 'tel', $data['telephone'], 'tel');
        // Never sent back into the page: the browser's password field stays empty after an error
        $field('password', 'Pasahitza (gutxienez ' . PASSWORD_MIN_LENGTH . ' karaktere)', 'password', '', 'new-password');
        $field('password2', 'Pasahitza errepikatu', 'password', '', 'new-password');
        ?>
        <p>
            <label for="imagen">Irudia aukeratu (aukerakoa):</label>
            <input id="imagen" name="imagen" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
            <?php if (isset($error['imagen'])) { ?><span class="error"><?= e($error['imagen']) ?></span><?php } ?>
        </p>
        <p>
            <input type="reset" value="Clear" class="button">
            <input type="submit" value="Submit" class="button marL10">
        </p>
    </form>
</div>
</div>
