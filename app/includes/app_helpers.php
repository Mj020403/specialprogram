<?php

// Compatibility loader. The old giant helper file was split into smaller files.
// Keep this file so existing pages continue to work while code is now easier to find.
require_once __DIR__ . '/helpers/core.php';
require_once __DIR__ . '/helpers/accounts.php';
require_once __DIR__ . '/helpers/households.php';
require_once __DIR__ . '/helpers/operations.php';
require_once __DIR__ . '/helpers/golden_household.php';
require_once __DIR__ . '/helpers/branding.php';
require_once dirname(__DIR__, 2) . '/modules/users/roles/executive/dashboard_helpers.php';
require_once dirname(__DIR__, 2) . '/modules/family/portal_helpers.php';
