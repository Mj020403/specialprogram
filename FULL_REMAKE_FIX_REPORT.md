# Full System Remake / Error Fix Report

This rebuilt package keeps the original `harvest/` structure and applies fixes for the visible dashboard warning and related status mismatch issues.

## Fixed
- Removed the `Undefined array key "Approved"` warning in `app/includes/helpers/golden_household.php`.
- Added safe support for both old and new program workflow statuses:
  - Pending / Pending Validation
  - Pending First Validation
  - Pending Orientation
  - Pending Final Validation
  - Pending Seminar
  - Pending Release
  - Approved
  - Active
  - Completed
  - Declined / Rejected
  - Inactive
- Updated dashboard/status counters so old database values and new workflow values do not break counts.
- Added a safe database upgrade script:
  - `database/upgrade_status_normalization_2026_04_26.sql`

## Checked
The changed PHP files were syntax checked with PHP lint:
- `app/includes/helpers/golden_household.php`
- `app/includes/helpers/core.php`
- `modules/users/roles/task_force/dashboard.php`
- `modules/users/roles/mayor/dashboard.php`
- `modules/agri/attendance/index.php`
- `modules/agri/reports/operational_summary.php`

## How to install
1. Backup your current `C:\xampp\htdocs\harvest` folder.
2. Backup your MySQL database.
3. Replace your current `harvest` folder with this rebuilt `harvest` folder.
4. Open phpMyAdmin and run:
   `harvest/database/upgrade_status_normalization_2026_04_26.sql`
5. Restart Apache/MySQL in XAMPP.
6. Reload:
   `http://localhost/harvest/modules/users/roles/task_force/dashboard.php`

