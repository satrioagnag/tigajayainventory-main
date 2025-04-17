<?php
session_start();
session_destroy();
$_SESSION['message'] = "Anda telah logout.";
header("Location: ../index.html");
exit();
?>