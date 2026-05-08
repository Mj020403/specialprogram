<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
app_require('app/includes/session.php');
session_unset();
session_destroy();
header('Location: /harvest/modules/users/auth/login.php');
exit;
