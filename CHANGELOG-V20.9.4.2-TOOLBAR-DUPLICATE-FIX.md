# Ara CMS V20.9.4.2 — Builder Toolbar Duplicate Fix

## Fix

Fixed duplicated **Header / Slider / Template** buttons in the Visual Builder toolbar.

### Root cause
The mobile `More` menu intentionally contains secondary copies of Header, Slider, and Template actions. On desktop, `.ce-more-menu` uses `display: contents`, causing those mobile copies to also render in the main desktop toolbar.

The copies also reused duplicate HTML IDs, which could cause event binding ambiguity.

### Changes
- Desktop keeps one visible Header, Slider, and Template button.
- Mobile `More` menu keeps its own copies.
- Mobile copies now use unique IDs:
  - `ceHeaderMoreBtn`
  - `ceSliderMoreBtn`
  - `ceTemplateMoreBtn`
- Shared action handlers are bound to both desktop and mobile controls.
- Site Settings and Lihat Situs remain available from the mobile More menu.

## Validation
- `node --check admin/assets/canvas-editor.js` passed.
- ZIP integrity checked.
