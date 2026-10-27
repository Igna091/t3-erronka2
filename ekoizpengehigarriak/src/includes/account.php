<?php

if(!isset($_SESSION['admin'])){
  header("Location: ".$_SERVER['PHP_SELF']);
}

if(isset($_GET['changepass'])){

  if($_POST['newpass'] != $_POST['confnewpass']){
    header("Location: ".$_SERVER['PHP_SELF']."?action=account");
  }else{
    $oldpass = $_SESSION['password'];
    $newpass = md5($_POST['newpass']);
    mysqli_query($conx,"UPDATE users SET password='".$newpass."' WHERE password='".$oldpass."'");
    session_destroy();
    header("Location: ".$_SERVER['PHP_SELF']);
  }

}
elseif(isset($_GET['adduser']) && $_SESSION['username']=='admin'){

    $newuser = $_POST['newuser'];
    $newuserpass = md5($_POST['newuserpass']);

  mysqli_query($conx,"INSERT INTO users (username,password) VALUES ('".$newuser."','".$newuserpass."')");

  header("Location: ".$_SERVER['PHP_SELF']."?action=account");

}
elseif(isset($_GET['deleteuser']) && $_SESSION['username']=='admin'){

  if($_GET['deleteuser']==$_SESSION['username']){
    header("Location: ".$_SERVER['PHP_SELF']."?action=account");
  }
  else{
      mysqli_query($conx,"DELETE FROM users WHERE username='".$_GET['deleteuser']."'");
    header("Location: ".$_SERVER['PHP_SELF']."?action=account");
  }

}
else{

  ?>

  <div align=center>
    <table width=1000 cellpadding=10 cellspacing=10>
        <tr>
            <td valign=top align=right>
                <fieldset style=width:300;>
                <legend><b>Change Password</b></legend>
                <form action=<?php echo $_SERVER['PHP_SELF']."?action=account&changepass=1"; ?> method=POST>
                    New Password: <input type=password name=newpass><br>
                    Confirm New Password: <input type=password name=confnewpass><br>
                    <br><div align=center><input type=submit value=Change></div>
                </form>
                </fieldset>
            </td>
            <?php
            if ($_SESSION['username']=='admin')
            {
            ?>
            <td valign=top align=left>

                <fieldset style=width:300;>
                <legend><b>Add User</b></legend>
                <form action=<?php echo $_SERVER['PHP_SELF']."?action=account&adduser=1"; ?> method=POST>
                    New user's username: <input type=text name=newuser><br>
                    New user's password: <input type=text name=newuserpass><br>
                    <br><div align=center><input type=submit value=Add></div>
                </form>
                </fieldset><br>

                <fieldset style=width:300;>
                <legend><b>Delete User</b></legend>
                <table cellpadding=2 cellspacing=2 width=100%>
                    <?php
                        $users = mysqli_query($conx,"SELECT username FROM users");
                        while($user = mysqli_fetch_array($users)){
                            echo "<tr>";
                            echo "<td align=left class=box>";
                              if($user['username']==$_SESSION['username']){
                                echo "<b>".$user['username']."</b>";
                              }else{
                                echo $user['username'];
                              }
                            echo "</td>";
                            echo "<td align=right class=box width=60>";
                              if($user['username']==$_SESSION['username']){
                                echo "<del>[delete]</del>&nbsp;";
                              }else{
                                echo "<a href=".$_SERVER['PHP_SELF']."?action=account&deleteuser=".$user['username'].">[delete]</a>&nbsp;";
                              }
                            echo "</td>";
                            echo "</tr>";
                        }
                    ?>
                </table>
                </fieldset>
            </td>
                <?php
            }
                ?>
        </tr>
    </table>
  </div>
  <?php
}

?>