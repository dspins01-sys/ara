<?php require_once __DIR__.'/../app/Security.php'; start_secure_session(); $_SESSION=[]; session_destroy(); header('Location:login.php');
