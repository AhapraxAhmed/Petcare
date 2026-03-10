<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Destroy session and redirect
session_destroy();
redirect('../index.php');
?>
