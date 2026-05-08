<?php
require_once __DIR__ . '/app/bootstrap.php';
app_require('app/includes/auth.php');
if (isset($_SESSION['user_id'])) {
    redirect_by_role($_SESSION['role_code'] ?? 'task_force');
}
redirect_to('modules/users/auth/login.php');
