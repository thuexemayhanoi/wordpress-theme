# English section: editorial homepage and navigation

Website: https://blog.rentbikehanoi.com — existing Vietnamese articles and URLs are never migrated.

This is a two-language WordPress site using **one** Nguyen Ha Editorial theme. The new English pages and categories are authored independently, not automatically translated.

## Safe setup (staging first)

1. Keep Nguyen Ha VI-EN Core **deactivated on production** while preparing the content and the staging site. Back up database and files.
2. On the staging copy, activate VI/EN Core 0.5.0. Open **Tools → VI/EN Links**.
3. Create a page titled in English (for example, “Motorbike Rental in Hanoi”), give it slug `en` and an English excerpt and introductory body. Keep the page draft until QA; **this page's title becomes the English H1, its excerpt the lead, and its body the introductory section**. The plugin's default `[nh_vien_posts]` placeholder is replaced by a visual article grid by the Editorial theme.
4. Create pages `about`, `prices`, `contact` as children of the `en` page, with independent English copy and original canonical/Yoast metadata. Price, vehicle, phone, address and rental policies must match the live business data.
5. Create **empty** English category parents and children in WordPress → Posts → Categories. Register the parent IDs first, then each child ID under Tools → VI/EN Links. Do not rename existing Vietnamese categories.
6. Author English posts as **drafts**; register their IDs in VI/EN Links **before** publishing. Assign only English categories. Existing Vietnamese published posts cannot be relabeled English.
7. Create menus with WordPress menu slugs `primary-en` and `footer-en`. If absent, the English side uses a small English-only fallback menu; it does not expose Vietnamese menu items. English footer columns have their own widget areas `footer-en-1` through `footer-en-4`, and fall back to the verified shared business contact data.
8. Enable English routes **only in staging** using `NH_VIEN_ENABLE_EN_ROUTES` and the plugin's explicit rewrite-rule action. Test /en/, /en/about/, /en/category/…, /en/{post}/, Yoast canonical/hreflang/sitemaps, phone/menu/search/CTA, desktop/tablet/mobile and rollback.
9. Only with a backup and passing staging QA should production configuration explicitly set both `NH_VIEN_ENABLE_EN_ROUTES` and `NH_VIEN_ALLOW_PRODUCTION` and store the rewrite rules. Do not enable production as part of this code release.

## The English homepage

- Same responsive visual architecture as Vietnamese: hero/H1 + search → original English intro → featured English posts → English topic hubs → English topic sections → latest English cards → optional Contact CTA.
- The English homepage **does not** read the Vietnamese Customizer homepage headline, subline, or Vietnamese content.
- Shared logos, design tokens and the companion business profile remain the same.
- English footer uses English-only widgets/links and shared NAP data.
- The homepage may show only recent posts; older English articles remain discoverable through their English categories and the Yoast sitemap.
- Keep Yoast's existing `/sitemap_index.xml`; do not introduce a second sitemap or bulk-change existing Vietnamese URLs.

## Release scope

This repository change adds code only. It does **not** create English pages, categories or 500 posts, activate the plugin, set production flags or change any Vietnamese post/page/category.
