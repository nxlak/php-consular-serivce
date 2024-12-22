<?php
// logout.php
include 'config.php';

// Разрушение сессии
session_unset();
session_destroy();

header("Location: index.php");
exit();
?>