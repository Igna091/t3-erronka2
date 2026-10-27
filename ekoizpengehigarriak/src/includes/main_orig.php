<?php

$images = array_diff(scandir("images"), array(".", ".."));

if(sizeof($images) == 0){
    echo "<fieldset style=width:500;>";
    echo "<legend><b>Ez dago produkturik katalogoan</b></legend>";
    if(isset($_SESSION['admin'])){
      echo "<div align=center><h3><b><a href=".$_SERVER['PHP_SELF']."?action=updel>Eguneratu</a> katalogoa.</b></h3></div>";
    }
    else{
      echo "<div align=center>
                <h3>Enpresaren hasierako orria</h3></div>";
    }
    echo "</fieldset>";
}
else{
?>
    <table width=1000 cellpadding=10 cellspacing=10 align=center>
    <?php
    foreach($images as $pic){
        $data = mysqli_query($conx,"SELECT * FROM produktuak WHERE pic LIKE '".$pic."'");
        $data = mysqli_fetch_array($data);
        ?>

        <tr>
            <td align=center valign=top width=40%>
                <fieldset>
                <br>
                <a href=<?php echo "images/".$pic; ?>><img src=images/<?php echo $pic; ?> border=1></a><br>
                <br>
                </fieldset>
            </td>
            <td valign=top width=60%>
                <fieldset>
                    <legend><b>Izena</b></legend>
                    <br>
                    <?php echo $data['izena']; echo " - ".$data['salneurria']. "€";?>
                    <br>
                </fieldset>
                <fieldset>
                    <legend><b>Descripzioa</b></legend>
                    <br>
                    <?php echo $data['descripzioa']; ?>
                    <br>
                </fieldset>
                <br>
                <a href=""><img src="images/pngegg.png"></a>
                <br>
                <?php
                if(isset($_SESSION['admin']) && ($_SESSION['admin']==1) && ($_SESSION['username'] == 'admin')){
                    ?>
                    <table width=100% cellpadding=2 cellspacing=2 align=center>
                    <tr><td width=50% align=left>
                    <a href=<?php echo $_SERVER['PHP_SELF']."?action=description&pic_id=".$data['ID']; ?>><b>Zer sartuko dot hemen?</b></a>
                    </td>
                    <td width=50% align=right><a href=<?php echo $_SERVER['PHP_SELF']."?action=description&del_id=".$data['ID']; ?>><b>Eta hemen?</b></a></td
                    </tr>
                    </table>
                    <?php
                }
                ?>
            </td>
        </tr>
    <?php

    }

}

?>

</table>