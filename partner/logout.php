<?php
require_once __DIR__ . '/../includes/functions.php';
// Only end the partner login; an admin login in the same browser stays.
unset($_SESSION['partner_id']);
session_regenerate_id(true);
redirect('index.php');
