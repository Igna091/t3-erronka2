<?php

declare(strict_types=1);

// An admin changes a product's description and price. index.php lets only admins open this
// page (lib/routes.php); the original let anyone. The change is a POST with the CSRF token, the
// values are checked, and the id is a number, used in a prepared statement.

$id = query_id('pic_id');
$find = db()->prepare('SELECT id, izena, deskripzioa, salneurria, pic FROM produktuak WHERE id = ?');
$find->execute([$id]);
$data = $find->fetch();
if (! is_array($data)) {
    echo '<h3>Ez dago produktu hori.</h3>';

    return;
}

$errors = [];
$deskripzioa = (string) $data['deskripzioa'];
if (is_post()) {
    [$update, $errors] = validate_product_update($_POST);
    $deskripzioa = $update['deskripzioa'];
    if (! csrf_valid($_POST['csrf'] ?? null)) {
        $errors['form'] = 'Eskaera ez da baliozkoa. Saiatu berriro.';
    }
    if ($errors === []) {
        db()->prepare('UPDATE produktuak SET deskripzioa = ?, salneurria = COALESCE(?, salneurria) WHERE id = ?')
            ->execute([$update['deskripzioa'], $update['salneurria'], $data['id']]);
        flash('Produktua eguneratu da.');
        redirect('index.php');
    }
}
?>
<div class="center">
    <fieldset class="narrow">
    <legend><b>Deskripzioa</b></legend>
    <br>
    <h4><?= e($data['izena']) ?> - <?= e(price($data['salneurria'])) ?></h4>
    <img src="images/<?= e($data['pic']) ?>" alt="<?= e($data['izena']) ?>"><br>
    <br>
    <?php foreach ($errors as $message) { ?><p class="error"><?= e($message) ?></p><?php } ?>
    <form action="index.php?action=description&amp;pic_id=<?= (int) $data['id'] ?>" method="post">
        <?= csrf_field() ?>
        <h5><label for="deskripzioa">Deskripzioa:</label></h5>
        <textarea id="deskripzioa" name="deskripzioa" cols="50" rows="10" maxlength="150" required><?= e($deskripzioa) ?></textarea><br>
        <br>
        <label for="salneurria">Salneurri berria:</label> <input type="text" id="salneurria" name="salneurria" inputmode="decimal" maxlength="8" placeholder="<?= e(number_format((float) $data['salneurria'], 2, ',', '')) ?>">
        <br>
        <br>
        <input type="submit" value="Aldatu">
    </form>
    </fieldset>
</div>
