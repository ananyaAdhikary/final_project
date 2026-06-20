<?php
require_once 'config/database.php';

// Destroy all session data
session_destroy();

// Redirect to home page
redirect('index.html');
?>