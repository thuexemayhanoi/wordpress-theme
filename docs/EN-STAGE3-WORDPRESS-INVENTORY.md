# Stage 3 — English editorial draft inventory (2026-10-20)

**WordPress:** https://blog.rentbikehanoi.com  
**Theme:** Nguyen Ha Editorial 2.0.1  
**English plugin installed on production:** Nguyen Ha VI-EN Core **0.4.0, inactive** at verification. Repository source has 0.5.0 from PR #5 and theme English presentation from PR #6.  
**Production state:** Preparatory EN pages are **draft**, categories are **empty**. **Do not assume staging exists or that /en/ is publicly live.**

## Draft English pages in the production WordPress database

| WordPress ID | Intended URL after safe activation | Title | State | Parent ID |
|---|---|---|---|---|
| 1758 | `/en/` | Motorbike Rental in Hanoi \| Nguyen Ha | draft | 0 |
| 1759 | `/en/about/` | About Nguyen Ha Motorbike Rental | draft | 1758 |
| 1760 | `/en/prices/` | Motorbike Rental Prices in Hanoi | draft | 1758 |
| 1761 | `/en/contact/` | Contact Nguyen Ha in Hanoi | draft | 1758 |
| 1762 | `/en/rental-guide/` | How to Rent a Motorbike in Hanoi | draft | 1758 |
| 1763 | `/en/faq/` | Motorbike Rental FAQ for Hanoi Visitors | draft | 1758 |
| 1764 | `/en/motorbikes/` | Motorbikes and Scooters for Rent in Hanoi | draft | 1758 |

All seven page IDs, draft status and parent relationships were verified through authenticated WordPress REST. These are original English editorial pages, not automatic translations. Their draft WordPress preview links are *not* the final public /en/ URLs.

## English category tree in WordPress — no articles assigned yet

- **Motorbike Rental in Hanoi** — category **46**, slug `hanoi-motorbike-rental`, parent 0.
  - **Manual Motorbikes** — **49**, `manual-motorbikes`, parent 46.
  - **Automatic Scooters** — **50**, `automatic-scooters`, parent 46.
  - **50cc Motorbikes** — **51**, `50cc-motorbikes`, parent 46.
  - **Electric Bikes & Scooters** — **52**, `electric-bikes-scooters`, parent 46.
- **Hanoi Travel & Routes** — category **47**, slug `hanoi-travel-routes`, parent 0.
  - **Hanoi Neighbourhoods** — **53**, `hanoi-neighbourhoods`, parent 47.
  - **Suggested Motorbike Routes** — **54**, `hanoi-motorbike-routes`, parent 47.
- **Riding Advice & Safety** — category **48**, slug `riding-advice-safety`, parent 0.
  - **Rental Tips & Paperwork** — **55**, `rental-tips-paperwork`, parent 48.
  - **Road Rules & Rider Safety** — **56**, `road-rules-rider-safety`, parent 48.

All eleven category IDs, parent relationships, slugs and zero article counts were verified. They were created alongside, **not in place of**, existing Vietnamese categories. Because the live 0.4.0 plugin remains inactive, these categories **have not yet been registered as English** through VI-EN Core 0.5.0. **Do not assign or publish any English articles before registration and staging QA.**

## Nguyen Ha — authoritative content used for drafting

Never copy the NAP or deposit/delivery policies of the **Nguyen Tu** website to this **Nguyen Ha** blog.

- Business: **Thuê xe máy Nguyễn Hà / Nguyen Ha Motorbike Rental**
- Address: **Ngõ 5 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội** (near Gia Lam Bus Station)
- Phone/Zalo/WhatsApp: **0334 699 969** / **+84 334 699 969**
- Hours: **09:00–21:00 daily**
- Official Vietnamese source pages: `/gioi-thieu/` (ID 14), `/bang-gia/` (ID 19), `/lien-he/` (ID 25).
- Rental prices as listed on the Vietnamese price page at preparation time: manual motorbikes VND 150–200k/day, 700–800k/week, 1–1.2m/month; automatic scooters VND 150–200k/day, 600k–1m/week, 1.2–2m/month; electric models variable/day, 600–800k/week, 1.5–1.8m/month; 50cc 200k/day, 1m/week, 2m/month.
- One day is 24 hours; shorter use is charged as a full day. Cash security deposit VND 2–5m, separate from rent. Original IDs are not retained after deposit and payment.
- Delivery/collection only for weekly/monthly agreements: within 10km of Hoan Kiem Lake VND 50k per trip, beyond quoted individually; no Noi Bai Airport service.
- Late return 1–5h: VND 30k/hour; from 6h: an additional rental day. Early return does not refund unused rent.
- These are **editorial snapshots**, not a dynamic pricing feed. If business terms change, review the drafted English text against the authoritative business profile and Vietnamese terms *before publication*.

## Candidate VI ↔ EN page relationships (not yet configured)

- VI `/gioi-thieu/` ID 14 ↔ EN draft `/en/about/` ID 1759.
- VI `/bang-gia/` ID 19 ↔ EN draft `/en/prices/` ID 1760.
- VI `/lien-he/` ID 25 ↔ EN draft `/en/contact/` ID 1761.
- VI root `/` ↔ EN root `/en/` once the English homepage is truly live.
- VI `/hoi-dap/` ID 24 ↔ EN `/en/faq/` ID 1763 **only after semantic equivalence is reviewed**.
- EN rental-guide (1762) and motorbikes (1764) may stay standalone; do not invent hreflang counterparts.

No Page↔Page links have been configured in production; plugin 0.4.0 remains inactive.

## Staging test and rollout gate — NOT completed

1. Establish a genuine Hostinger staging copy (or an equivalent isolated WordPress test site) and a tested backup/restore path. **No connected staging site was available at this handoff.**
2. Import the new EN drafts and eleven empty terms to the staging copy, or recreate them if the copy predates Stage 3. **Never reuse numeric IDs without re-checking IDs in staging.**
3. Install/test VI-EN Core **0.5.0** in staging; register category parents 46, 47, 48 **before** their child terms, using the IDs of the environment actually being configured.
4. Confirm the English homepage and its English-only header/footer, images, categories, search and article grid; prepare independent `primary-en` and `footer-en` menus.
5. Only pair VI and EN pages when their search intent/visitor purpose is equivalent. Check Yoast SEO canonical/hreflang and `/sitemap_index.xml`, preserving all current Vietnamese slugs, Yoast metadata, robots and sitemap.
6. Test desktop/tablet/mobile, all 404 boundaries, price/NAP correctness, category visibility and rollback. **Do not publish or turn on production English routes before these checks pass.**
7. Only after a passing staging release: update production plugin (while inactive), take a fresh backup, register English categories, configure menus, publish approved EN pages/articles and enable production flags/rewrite rules in a planned release window.

## Writer handoff

The /wp-aiwriter pipeline may plan about 500 independently researched English article drafts across these eleven term IDs, but should not publish or assign a category until VI-EN 0.5.0 is active and the term has been marked EN in the target environment. Keep posts human-readable, original and naturally optimized; treat all location, law and pricing statements as fact-checked editorial information.

## Update — VI-EN Core 0.5.1 (switch in WordPress)

The facts above are unchanged. Version 0.5.1 replaces the two `wp-config.php` constants of step 7 with a switch in **Tools → VI/EN Links** (the constants still work and, when `NH_VIEN_ENABLE_EN_ROUTES` is defined, decide alone; `false` is the emergency brake). On a production site the switch asks for a confirmation (backup done, staging checks passed) and is tied to the address of the site. The staging gate above (steps 1–7) is still **not** completed, and nothing in the code release publishes the draft pages or switches anything on. See [VI-EN-PLUGIN.md](VI-EN-PLUGIN.md) sections 3, 5 and 6 and [VI-EN-CHECKPOINT.md](VI-EN-CHECKPOINT.md).
