<?php
if($_SESSION['username'] != "admin@bdweb"){
  header("Location: ".$_SERVER['PHP_SELF']);
}

if(isset($_GET['pic_id'])){
    $filequery = mysqli_query($conx,"SELECT pic FROM produktuak WHERE id LIKE ".$_GET['pic_id']);
  $delfile = mysqli_fetch_array($filequery);
  unlink("images/".$delfile['pic']);
  mysqli_query($conx,"DELETE FROM produktuak WHERE id = ".$_GET['pic_id']);
  echo "<div align=center><h5>Produktua ezabatuta</h5></div><br>";
}

if(isset($_GET['upload'])){
    $path = "images/".basename($_FILES['upfile']['name']);
    $uploader = $_SESSION['username'];
    move_uploaded_file($_FILES['upfile']['tmp_name'], $path);
    mysqli_query($conx,"INSERT INTO produktuak (izena, deskripzioa, salneurria, pic, stock) VALUES ('"
        .$_POST['izena']."', '".$_POST['deskripzioa']."', ".$_POST['salneurria'].", '".$_FILES['upfile']['name']."', ".$_POST['stock'].")");
    echo "<div align=center><h5>Produktu \"".$_POST['izena']."\" txertatuta</h5></div><br>";
}

?>

<div align=center>
    <table width=1000 cellpadding=10 cellspacing=10 align=center>
        <tr>
            <td valign=top align=left>
                <fieldset style=width:300;>
                <legend><b>Produktu berria </b></legend>
                <form enctype=multipart/form-data action=<?php echo $_SERVER['PHP_SELF']."?action=updel&upload=1"; ?> method=POST>
                    Izena: <input type="text" name="izena"><br>
                    Deskripzioa: <input type="text" name="deskripzioa"><br>
                    Salneurria: <input type="text" name="salneurria"><br>
                    Stock: <input type="text" name="stock"><br>
                    Irudia aukeratu:<br>
                    <br>
                    <input name=upfile type=file><br>
                    <br>
                    <input type=submit value=Igo>
                </form>
                </fieldset>
            </td>
            <td valign=top align=left>
                <fieldset style=width:300;>
                <legend><b>Ezabatu</b></legend>
                <?php

                $delquery = mysqli_query($conx,"SELECT * FROM produktuak");
                $produktuak = array();
                while ($row = mysqli_fetch_array($delquery)) {
                    $produktuak[] = $row;
                }

                foreach($produktuak as $produktua){
                ?>
                    <p><?php echo $produktua['izena']; ?></p><br>
                <a href=<?php echo "images/".$produktua['pic']; ?>><img src="images/<?php echo $produktua['pic']; ?>" border=1></a><br>
                <a href=<?php echo $_SERVER['PHP_SELF']."?action=updel&pic_id=".$produktua['id']; ?>><b>Produktua ezabatu</b></a>
                <br><br>
                <?php
                }
                ?>
                </fieldset>
            </td>
        </tr>
    </table>
</div>
