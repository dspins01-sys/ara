# V20.9.3.1 — Builder Sidebar Collapse Fix

## Fix
- Fixed Visual Builder (`content.php`) sidebar becoming visually broken when desktop sidebar collapse is active.
- Builder-specific `.builder-shell` desktop rules previously overrode the generic collapsed-state rules for the sidebar header and `.nav-label` elements.
- Collapsed Visual Builder now correctly shows the compact icon-only sidebar with hover labels, matching other admin pages.
- Expanded Visual Builder sidebar remains unchanged.
- Mobile drawer behavior remains unchanged.
