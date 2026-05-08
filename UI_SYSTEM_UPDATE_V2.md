# Harvest UI System Update v2

This package includes a system-wide UI polish layer applied to the full Harvest source tree.

Updated files:
- `public/assets/css/app.css`
- `public/assets/js/app.js`
- `UI_SYSTEM_UPDATE_V2.md`

Coverage:
- Login page sizing, hero proportions, and form spacing
- Header/nav density, icon sizing, badges, search focus states
- Dashboard/stat cards and grid spacing
- Reports/print layouts and table readability
- Forms, buttons, labels, inputs, selects, textareas
- Mobile spacing and horizontal table handling
- Hover/focus transitions and accessibility readability

Install:
1. Backup your current `harvest` folder.
2. Replace it with this updated folder, or copy the two updated asset files into your current system.
3. Hard refresh browser cache: Ctrl + F5.
4. If CSS still looks old, clear browser cache or add a query string to the CSS include.

Notes:
- This is a non-breaking visual layer. It does not alter database logic or PHP workflows.
- It intentionally improves old/legacy pages too by styling common HTML patterns globally.
