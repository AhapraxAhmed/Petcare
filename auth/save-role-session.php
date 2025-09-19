<?php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['oauth_role'] = $_POST['role'] ?? null;
    $_SESSION['oauth_shelter_name'] = $_POST['shelter_name'] ?? null;
}
