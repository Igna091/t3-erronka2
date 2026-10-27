<?php

declare(strict_types=1);

// The catalogue, or the products that match the search word. The word goes to MySQL as a
// parameter of a prepared statement, and everything shown is escaped.

$testua = is_string($_GET['keyword'] ?? null) ? mb_substr(trim($_GET['keyword']), 0, 30) : '';
$pattern = like_pattern($testua);

$search = db()->prepare(
    "SELECT id, izena, deskripzioa, salneurria, pic FROM produktuak
     WHERE izena LIKE ? ESCAPE '!' OR deskripzioa LIKE ? ESCAPE '!' ORDER BY id"
);
$search->execute([$pattern, $pattern]);
$produktuak = $search->fetchAll();
$viewer = current_user();

if ($produktuak === []) {
    ?>
    <fieldset class="narrow-500">
    <legend><b>Ez dago produkturik katalogoan<?= $testua !== '' ? ' "' . e($testua) . '" deitzen denik' : '' ?></b></legend>
    <?php if ($viewer !== null && $viewer['is_admin']) { ?>
        <div class="center"><h3><b><a href="index.php?action=updel">Eguneratu</a> katalogoa.</b></h3></div>
    <?php } else { ?>
        <div class="center"><h3>Enpresaren hasierako orria</h3></div>
    <?php } ?>
    </fieldset>
    <?php
    return;
}
?>
<table class="catalogue">
<?php foreach ($produktuak as $data) { ?>
    <tr>
        <td class="picture">
            <fieldset>
            <br>
            <a href="images/<?= e($data['pic']) ?>"><img src="images/<?= e($data['pic']) ?>" alt="<?= e($data['izena']) ?>"></a><br>
            <br>
            </fieldset>
        </td>
        <td class="details">
            <fieldset>
                <legend><b>Izena</b></legend>
                <br>
                <?= e($data['izena']) ?> - <?= e(price($data['salneurria'])) ?>
                <br>
            </fieldset>
            <fieldset>
                <legend><b>Deskripzioa</b></legend>
                <br>
                <?= nl2br(e($data['deskripzioa'])) ?>
                <br>
            </fieldset>
            <br>
            <?php if ($viewer !== null && $viewer['is_admin']) { ?>
                <a href="index.php?action=description&amp;pic_id=<?= (int) $data['id'] ?>"><b>Deskripzioa/Salneurria aldatu</b></a>
            <?php } elseif ($viewer !== null) { ?>
                <img src="images/pngegg.png" alt="Saskia">
            <?php } ?>
        </td>
    </tr>
<?php } ?>
</table>
