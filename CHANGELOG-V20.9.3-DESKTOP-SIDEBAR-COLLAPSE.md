# Ara CMS V20.9.3 — Desktop Sidebar Collapse

## UX fix
- Desktop admin sidebar is expanded by default with icon + label navigation.
- Added an explicit collapse / expand toggle on desktop.
- Collapse state is persisted with `localStorage` across admin pages and refreshes.
- Collapsed desktop sidebar becomes compact icon-only navigation with hover labels.
- Visual Builder no longer forces the sidebar into icon-only mode automatically.
- Mobile remains a drawer sidebar with full icon + label navigation; desktop collapse state is ignored on mobile.
- Switching back to desktop restores the saved desktop collapse preference.

## Scope
This release only changes admin sidebar behavior. Builder/content/template functionality is otherwise unchanged.
