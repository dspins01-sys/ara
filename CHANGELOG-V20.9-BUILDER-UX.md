# Ara CMS V20.9 — Builder UX

## Block insertion
- Add Block after the currently selected block.
- If no block is selected, append after the last block.
- The newly inserted block becomes selected automatically.
- The existing `after_id` persistence path is retained so Builder and SQLite order stay aligned.

## Active block indicator
- A floating arrow appears on the right side of the Builder.
- It tracks the vertical center of the selected block.
- It follows selection, scrolling, resizing and reordering.
- It is Builder-only and never becomes part of the live site content.

## Live site Back to Top
- The floating up arrow appears after approximately 300px of scroll.
- It is available in the middle of long pages, not only near the footer.
- Clicking it smoothly returns to the top.

## Docker fresh install
- Runtime `data/` and `public/uploads/` directories are prepared automatically by `docker-entrypoint.sh`.
- Apache/PHP receives write access without a manual host `chown` step for a fresh container start.
