<?php

declare(strict_types=1);

// An admin adds and deletes products. index.php lets only admins open this page. Both changes
// are POSTs with the CSRF token; the original deleted on a plain link, which another site could
// make an admin's browser open.

$errors = [];
$form = ['izena' => '', 'deskripzioa' => '', 'salneurria' => '', 'stock' => ''];
$imagesDir = config()['images_dir'];

if (is_post()) {
    if (! csrf_valid($_POST['csrf'] ?? null)) {
        $errors['form'] = 'Eskaera ez da baliozkoa. Saiatu berriro.';
    } elseif (post_string('op') === 'upload') {
        foreach (array_keys($form) as $key) {
            $form[$key] = post_string($key);
        }
        [$product, $errors] = validate_new_product($_POST);
        if ($errors === []) {
            try {
                $pic = store_upload($_FILES['upfile'] ?? null, $imagesDir);
                db()->prepare('INSERT INTO produktuak (izena, deskripzioa, salneurria, pic, stock) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$product['izena'], $product['deskripzioa'], $product['salneurria'], $pic, $product['stock']]);
                flash('Produktu "' . $product['izena'] . '" txertatuta.');
                redirect('index.php?action=updel');
            } catch (UploadRejected $rejected) {
                $errors['upfile'] = $rejected->getMessage();
            }
        }
    } elseif (post_string('op') === 'delete') {
        $find = db()->prepare('SELECT pic FROM produktuak WHERE id = ?');
        $find->execute([post_id('pic_id')]);
        $pic = $find->fetchColumn();
        if (is_string($pic)) {
            db()->prepare('DELETE FROM produktuak WHERE id = ?')->execute([post_id('pic_id')]);
            // Another product can show the same picture (generikoa.jpg): only an unused one goes
            $used = db()->prepare('SELECT COUNT(*) FROM produktuak WHERE pic = ?');
            $used->execute([$pic]);
            if ((int) $used->fetchColumn() === 0) {
                delete_image($pic, $imagesDir);
            }
            flash('Produktua ezabatuta.');
        }
        redirect('index.php?action=updel');
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
    <table class="catalogue">
        <tr>
            <td class="left">
                <fieldset class="narrow">
                <legend><b>Produktu berria</b></legend>
                <form enctype="multipart/form-data" action="index.php?action=updel" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="op" value="upload">
                    <label for="izena">Izena:</label> <input type="text" id="izena" name="izena" value="<?= e($form['izena']) ?>" maxlength="30" required><br>
                    <?php $problem('izena'); ?>
                    <label for="deskripzioa">Deskripzioa:</label> <input type="text" id="deskripzioa" name="deskripzioa" value="<?= e($form['deskripzioa']) ?>" maxlength="150" required><br>
                    <?php $problem('deskripzioa'); ?>
                    <label for="salneurria">Salneurria:</label> <input type="text" id="salneurria" name="salneurria" value="<?= e($form['salneurria']) ?>" inputmode="decimal" maxlength="8" required><br>
                    <?php $problem('salneurria'); ?>
                    <label for="stock">Stock:</label> <input type="number" id="stock" name="stock" value="<?= e($form['stock']) ?>" min="0" max="99999" required><br>
                    <?php $problem('stock'); ?>
                    <label for="upfile">Irudia aukeratu:</label><br>
                    <br>
                    <input id="upfile" name="upfile" type="file" accept="image/jpeg,image/png,image/gif,image/webp" required><br>
                    <?php $problem('upfile'); ?>
                    <br>
                    <input type="submit" value="Igo">
                </form>
                </fieldset>
            </td>
            <td class="left">
                <fieldset class="narrow">
                <legend><b>Ezabatu</b></legend>
                <?php foreach (rows('SELECT id, izena, pic FROM produktuak ORDER BY id') as $produktua) { ?>
                    <p><?= e($produktua['izena']) ?></p><br>
                    <a href="images/<?= e($produktua['pic']) ?>"><img src="images/<?= e($produktua['pic']) ?>" alt="<?= e($produktua['izena']) ?>"></a><br>
                    <form action="index.php?action=updel" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="op" value="delete">
                        <input type="hidden" name="pic_id" value="<?= (int) $produktua['id'] ?>">
                        <button type="submit" class="link"><b>Produktua ezabatu</b></button>
                    </form>
                    <br>
                <?php } ?>
                </fieldset>
            </td>
        </tr>
    </table>
</div>
