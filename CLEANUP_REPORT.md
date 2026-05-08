# Cleanup report

This package was cleaned with a conservative **static-analysis pass**.

What was removed from the project bundle:
- developer notes and upgrade notes
- one-off upgrade SQL files
- the very large historical `database/harvest.sql` dump
- the `docs/` folder
- `make_hash.php`

Database cleanup approach:
- started from the uploaded current dump `harvest (38).sql`
- kept tables and views that are referenced by PHP or JS in the current project
- removed tables/views with no runtime references, especially backup and retired structures

Kept database objects: 58
Removed database objects: 60

Removed database objects:
- app_menu_groups
- app_menu_items
- assistance_history
- backup_barangays_before_cleanup
- backup_family_members_before_cleanup
- backup_family_members_before_full_reset
- backup_households_before_cleanup
- backup_households_before_full_reset
- cbms_audit_logs
- cbms_consent_records
- cbms_financial_accounts
- cbms_food_security
- cbms_geo_records
- cbms_health
- cbms_household_answers
- cbms_household_records
- cbms_housing
- cbms_internet_access
- cbms_interviews
- cbms_member_answers
- cbms_member_profiles
- cbms_member_roster_snapshot
- cbms_pet_records
- cbms_public_safety
- cbms_shocks
- cbms_social_protection
- cbms_status_logs
- cbms_transport_access
- cbms_validation_flags
- cbms_vehicle_records
- cbms_wash
- event_invited_households
- event_target_rules
- family_access_logs
- family_crop_updates
- family_notifications
- family_portal_access
- family_progress_logs
- family_submission_files
- family_units
- household_animals
- household_group_members
- household_groups
- household_housing_profiles
- household_livelihood_profiles
- household_tags
- member_sector_tags
- module_user_access
- report_exports
- role_menu_access
- role_module_access
- route_assignments
- schema_migrations
- system_modules
- user_module_access
- v_dashboard_role_summary
- v_family_submission_review_queue
- v_family_units_detail
- v_household_family_summary
- v_module_population_summary

Important: this is a best-effort cleanup. Static analysis cannot prove that a table is unused in every future workflow, especially if a table is accessed manually or through ad hoc SQL outside the app.
