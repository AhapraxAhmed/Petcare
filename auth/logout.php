<?php
session_start();

// remove all session variables
session_unset();

// destroy the session
session_destroy();

// redirect to login page
header("Location: ../index.php");
exit();
?>