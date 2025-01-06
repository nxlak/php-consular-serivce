<?php
session_start();
session_unset();
session_destroy();

if (isset($_GET['timeout'])) {
    header("Location: login.php?timeout=1");
} else {
    header("Location: login.php");
}
exit();
?>