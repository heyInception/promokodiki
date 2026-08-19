# Navigation Menu Design

## Goal

Add one-level promocode categories to the primary navigation and rebuild the mobile navigation to match the approved Figma layout.

## Confirmed behavior

- Work directly on `main`.
- Keep existing WordPress-managed primary-menu items.
- Treat the Figma label “Категории” as “Магазины”.
- The “Промокоды” label links to the promocode archive; a separate arrow toggles its categories.
- Add all non-empty root `promocode_category` terms, alphabetically, with no descendants.
- Use term meta `shops-category-image-id`; fall back to `img/categories/default.png`.
- Apply the categories on desktop and mobile.
- On desktop, show the categories as a closed dropdown.
- At widths up to 1229px, show a full-width panel below the header, lock page scrolling, and close it via the close button, Escape, or navigation.
- On mobile, start the promocode categories expanded by default.
- Add a Customizer checkbox under `Внешний вид → Настроить → Мобильное меню` to disable the default-expanded state.
- Render the mobile-only “Добавить наш сайт в избранное” action with iOS/Android instructions.
- Add replaceable local SVG placeholders for the top-level mobile menu icons.
- Preserve the user's existing `style.css` changes.

## Figma reference

- File: `h5Na6n0VIwtiPF69F6BSB3`
- Page: `52:6193`
- Mobile menu item: `474:2711`
- Breakpoint retained from the theme: `1229px`

## Accessibility

- The menu and submenu toggles expose `aria-expanded` and `aria-controls`.
- Decorative images have empty alternative text; category images use the term name.
- Focus remains usable for keyboard navigation; Escape closes the active layer.
- Reduced-motion preferences disable nonessential transitions.

