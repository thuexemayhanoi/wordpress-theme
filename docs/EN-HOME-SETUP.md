# English section: editorial homepage and navigation

Website: https://blog.rentbikehanoi.com — existing Vietnamese articles and URLs are never migrated.

This is a two-language WordPress site using **one** Nguyen Ha Editorial theme. The new English pages and categories are authored independently, not automatically translated.

## Safe setup (staging first)

1. Keep Nguyen Ha VI-EN Core **deactivated on production** while preparing the content and the staging site. Back up database and files.
2. On the staging copy, activate VI/EN Core 0.5.1 (or later). Open **Tools → VI/EN Links**. Activating changes nothing: English routing stays off until it is switched on in step 8.
3. Create a page titled in English (for example, “Motorbike Rental in Hanoi”), give it slug `en` and an English excerpt and introductory body. Keep the page draft until QA; **this page's title becomes the English H1, its excerpt the lead, and its body the introductory section**. The plugin's default `[nh_vien_posts]` placeholder is replaced by a visual article grid by the Editorial theme.
4. Create pages `about`, `prices`, `contact` as children of the `en` page, with independent English copy and original canonical/Yoast metadata. Price, vehicle, phone, address and rental policies must match the live business data.
5. Create **empty** English category parents and children in WordPress → Posts → Categories. Register the parent IDs first, then each child ID under Tools → VI/EN Links. Do not rename existing Vietnamese categories.
6. Author English posts as **drafts**; register their IDs in VI/EN Links **before** publishing. Assign only English categories. Existing Vietnamese published posts cannot be relabeled English.
7. Create menus with WordPress menu slugs `primary-en` and `footer-en`. If absent, the English side uses a small English-only fallback menu; it does not expose Vietnamese menu items. English footer columns have their own widget areas `footer-en-1` through `footer-en-4`, and fall back to the verified shared business contact data.
8. Switch English routing on **only in staging**: **Tools → VI/EN Links → Switch English routing on** (it stores the rewrite rules in the same step; no file to edit). Test /en/, /en/about/, /en/category/…, /en/{post}/, Yoast canonical/hreflang/sitemaps, phone/menu/search/CTA, desktop/tablet/mobile and rollback (**Switch English routing off**). A staging copy that WordPress still calls `production` shows the production confirmation; tick it only after checking the copy.
9. Only with a backup and passing staging QA should an administrator switch English routing on in production (the same button, with the production confirmation; or, instead, set both `NH_VIEN_ENABLE_EN_ROUTES` and `NH_VIEN_ALLOW_PRODUCTION` in `wp-config.php` and press *Store the rewrite rules*). The decision is tied to the address of the site, so copying a staging database to production never switches production on. Setting `NH_VIEN_ENABLE_EN_ROUTES` to `false` in `wp-config.php` is the emergency brake. Do not enable production as part of this code release.

## The English homepage

- Same responsive visual architecture as Vietnamese: hero/H1 + search → original English intro → featured English posts → English topic hubs → English topic sections → latest English cards → optional Contact CTA.
- The English homepage **does not** read the Vietnamese Customizer homepage headline, subline, or Vietnamese content.
- Shared logos, design tokens and the companion business profile remain the same.
- English footer uses English-only widgets/links and shared NAP data.
- The homepage may show only recent posts; older English articles remain discoverable through their English categories and the Yoast sitemap.
- Keep Yoast's existing `/sitemap_index.xml`; do not introduce a second sitemap or bulk-change existing Vietnamese URLs.

## Release scope

This repository change adds code only. It does **not** create English pages, categories or 500 posts, activate the plugin, set production flags or change any Vietnamese post/page/category.
