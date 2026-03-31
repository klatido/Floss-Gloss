<?php
session_start();
session_unset();
session_destroy();

header("Location: ../auth/admin-login.php");
exit();
?>