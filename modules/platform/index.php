<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
header('Location: ' . app_url('modules/users/auth/login.php'));
exit;
