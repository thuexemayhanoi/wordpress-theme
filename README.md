# Nguyễn Hà Editorial — WordPress Theme

A standalone, classic WordPress theme designed for blogs about travel, mobility and motorbike knowledge. Theme files are at the **root** of this repository so Deployer for Git can discover style.css.

## Install with Deployer for Git

1. In WordPress, go to **Deployer for Git → Install Theme**.
2. Provider: GitHub.
3. Repository URL: https://github.com/thuexemayhanoi/wordpress-theme
4. Branch: main. Public repository, so do **not** select Private.
5. Select **Install Theme** (installation only). **Do not activate on a live site until testing**.
6. Preview on a staging site or a suitable theme preview. If satisfied, back up before activation.

## Features

- Responsive three-column article cards and two-column magazine hero.
- WordPress post, page, single, search, category, archive, 404 and sidebar templates.
- Accessibility-friendly mobile menu, keyboard skip link and color mode toggle.
- Custom logo, two WP menus, one widget sidebar, featured images.
- Related posts and previous/next navigation limited to real published posts in overlapping categories.
- No external JS/CSS dependencies, no hardcoded price, no forced canonical, no noindex.
- Preserves existing post URLs and SEO plugins (Yoast remains responsible for metadata).

## Scope & caveats

- This is a **new theme**, not a clone of the previous site's custom theme. Some old-theme widgets, shortcodes or custom layout blocks may render differently.
- Static home pages can show their editor content inside a section; the new magazine hero is always drawn above it.
- No GitHub Pages deployment is necessary.
- No customer/contact data, business hours or pricing are hard-coded in the theme.
- For rollback: **Appearance → Themes → activate the previous theme**.
- Verify mobile, tablet, desktop, WP menus, homepage content, old page layouts, SEO metadata, pagination, accessibility and image sizing before switching production.
- Only source code is stored here. No API keys, hosting passwords or WordPress credentials.

## License

GNU GPL-2.0-or-later.
