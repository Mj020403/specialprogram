<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
app_require('app/includes/session.php');
app_require('modules/family/portal_helpers.php');
family_portal_logout();
header('Location: ' . app_url('modules/family/scan.php'));
exit;
