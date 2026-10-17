# Nguyen Ha SEO Companion — design and module specification

Folder: `plugins/nguyen-ha-seo-companion/` (shipped as its own zip; install it in
`wp-content/plugins/`). It owns everything that must **survive a theme change**: editorial data,
Topic Clusters, business profile, structured data, the internal search index and cache purging.
The theme only presents. Data keys, limits and shared rules: `docs/DATA-CONTRACT.md`.

Hard rules for every module:

* PHP 7.4 compatible (no `match`, enums, `readonly`, union types, named arguments, `?->`).
* WordPress Coding Standards clean: `vendor/bin/phpcs plugins/nguyen-ha-seo-companion tests/phpunit/plugin`.
* Every PHP file starts with `defined( 'ABSPATH' ) || exit;`. No `eval`, no remote calls, no external libraries.
* Capability + nonce on every state-changing admin action. Sanitize on input, escape on output.
* Never create, modify, delete or noindex posts, pages, terms, slugs or canonicals. The plugin only
  *reads* content and writes its own meta/options/table.
* Never invent business data, ratings, reviews, prices or author credentials. No `aggregateRating`,
  `review`, `offers` anywhere.
* Text domain `nguyen-ha-seo-companion`; function prefix `nh_seo_`; class prefix `NH_SEO_`.
* Modules are loaded from `includes/<module>/bootstrap.php` by `NH_SEO_Plugin::boot()` at include time.
  Register lifecycle work on the plugin's own actions: `nh_seo_activate`, `nh_seo_deactivate`,
  `nh_seo_upgrade( $from, $to )`.
* Shared API (do not duplicate): `includes/functions.php`.

## Module `data` (agent P1)

Files: `includes/data/bootstrap.php`, `class-nh-seo-data-model.php`, `class-nh-seo-cluster-sync.php`.

* Register taxonomy `topic_cluster` on `init` (priority 5): flat, `public=false`, `publicly_queryable=false`,
  `show_ui=true`, `show_admin_column=true`, `show_in_rest=true`, `rest_base='topic_clusters'`,
  `show_in_nav_menus=false`, `show_tagcloud=false`, `rewrite=false`, `query_var=false`, object type `post`.
* Register post meta (`post` and `page`; `hub_term` for pages only) from `NH_SEO_Keys`: `single=true`,
  REST schemas for arrays/objects (`nh_takeaways`, `nh_faq`, `nh_sources`), `sanitize_callback` using the
  shared sanitizers, `auth_callback` = `current_user_can( 'edit_post', $post_id )`.
* Term meta `nh_pillar_id` for `category` and `topic_cluster` (REST, `edit_term` capability,
  `nh_seo_sanitize_pillar_id`). User meta `nh_job_title`, `nh_sameas` (not REST).
* Cluster alias sync (see contract §2) on `rest_after_insert_post`, `save_post` and when `nh_topic_cluster`
  meta is added/updated. Respect `auto_cluster_terms`. Guard recursion; ignore revisions and autosaves.
* `nh_seo_get_primary_cluster( $post_id )`: `WP_Term|null` per the contract.
* Supply the theme's profile filter: `add_filter( 'nguyen_ha_business_profile', 'nh_seo_get_business_profile' )`.

## Module `admin` (agent P1)

Files under `includes/admin/`, assets `assets/admin.css`, `assets/admin.js` (vanilla JS; `wp.media` for the logo).

* Meta box **"Answer, FAQ and sources"** on posts and pages: summary (character counter), takeaways
  (one per line), FAQ repeater, sources repeater, content-updated date, keywords, Pillar ID, local
  business checkbox, primary cluster select (assigned clusters), hub-term select (pages only).
  Nonce, capability `edit_post`, skip autosave/revision, `wp_unslash` then sanitizers; delete meta when empty.
* Term fields for `category` and `topic_cluster` (add and edit forms): Pillar post/page ID. Nonce + `edit_term`.
* User profile fields: job title, sameAs URLs (for users who can `edit_posts`). Nonce + `edit_user`.
* **Settings → Nguyen Ha SEO Companion** (`manage_options`, Settings API): tabs *Business profile*
  (all `nh_seo_business` fields, nothing pre-filled, clear help text that the data must be verified),
  *General* (`nh_seo_settings`), *Tools* (calls `do_action( 'nh_seo_render_tools_panel' )` for the
  search index panel), plus a read-only *Status* box: detected SEO plugin, effective schema mode,
  theme support (`nh_seo_theme_supports`), search-index state (filter `nh_seo_search_status`).
* Enqueue assets only on the plugin's own screens, post editor screens and term/user screens.

## Module `render` (agent P1)

* When `fallback_render` is on, append a block to `the_content` (singular, main query, in the loop,
  priority 20) for each extra the theme does **not** declare (`nh_seo_theme_supports()`): summary first,
  takeaways, FAQ and sources last. Semantic markup, classes prefixed `nh-seo-`, tiny
  `assets/frontend.css` enqueued only when something is rendered. Everything escaped; FAQ answers pass
  `wp_kses( $html, nh_seo_allowed_html(), nh_seo_allowed_protocols() )`.
* Public renderers reusable by other code: `nh_seo_render_summary()`, `nh_seo_render_takeaways()`,
  `nh_seo_render_faq()`, `nh_seo_render_sources()` (each returns HTML, `''` when empty).

## Module `schema` (agent P2)

Files under `includes/schema/`. Mode from `nh_seo_schema_mode()`:

* `off`: output nothing.
* `extras` (an SEO plugin owns titles/canonical/graph): print only **separate** `application/ld+json`
  blocks (never edit the SEO plugin's graph):
  * `FAQPage` on singular posts/pages when `nh_seo_faq_is_visible()` and items ≥ 2 (filter
    `nh_seo_faq_min_items`). Skip when the content already holds a Yoast or Rank Math FAQ block, or
    the filter `nh_seo_has_external_faq` returns true.
  * `LocalBusiness` (type from the profile) only when `nh_seo_business_is_complete()` and the post/page
    has `nh_local_business`, or it is the front page with `localbusiness_on_home`. Skip when Yoast Local
    SEO or Rank Math's Local SEO module is active (filter `nh_seo_has_external_localbusiness`).
* `full` (no SEO plugin): one `@graph` with `Organization`, `WebSite`, `WebPage` / `CollectionPage` /
  `ProfilePage` / `SearchResultsPage`, `BreadcrumbList` (from `nh_seo_breadcrumb_trail()`), `Article` on
  posts (headline ≤ 110 chars, image, datePublished, dateModified, author `Person`, publisher, articleSection,
  keywords from tags, wordCount, inLanguage, description from `nh_summary` or the manual excerpt), plus the
  `FAQPage` / `LocalBusiness` nodes under the same conditions. Organization data comes only from the
  business profile (name falls back to the site name; logo only when configured; `sameAs` from `social`;
  `contactPoint` only when a phone is configured).
* Print in `wp_head` (priority 20) with `wp_json_encode( …, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
  | JSON_HEX_TAG | JSON_HEX_AMP )`. Unique `@id`s, no empty properties, filter `nh_seo_schema_graph`.
* Tests must prove: no duplicated `@type` per page, valid JSON, nothing printed for drafts/404/search in
  `extras`, `</script>` injection in titles/FAQ is neutralized, no rating/review/offer keys ever appear,
  and behaviour with simulated Yoast / Rank Math (constants defined; block detection).

## Module `search` (agent P2)

Files under `includes/search/`, asset `assets/admin-search-index.js`.

Goal: fast relevance search over tens of thousands of posts on shared hosting, without an external
service, with a clean fallback to native WordPress search.

* Table `{$wpdb->prefix}nh_search_index` (create with `dbDelta` on `nh_seo_upgrade`, InnoDB utf8mb4):
  `post_id BIGINT UNSIGNED PRIMARY KEY`, `post_type VARCHAR(20)`, `title_fold TEXT`, `tax_fold TEXT`,
  `body_fold LONGTEXT`, `updated DATETIME`; `FULLTEXT ft_all (title_fold, tax_fold, body_fold)`,
  `FULLTEXT ft_title (title_fold)`, `FULLTEXT ft_tax (tax_fold)`.
* Folding: strip tags/shortcodes, decode entities, `remove_accents()` (Vietnamese diacritics and `đ`
  must fold to ASCII), lowercase, keep `[a-z0-9]` tokens. `title_fold` = title; `tax_fold` = category,
  tag and cluster names + `nh_keywords` + SEO-plugin focus keyword meta (`_yoast_wpseo_focuskw`,
  `rank_math_focus_keyword`) when present; `body_fold` = **unique** tokens of length ≥ 3 (InnoDB minimum)
  from content, excerpt, summary and headings, capped (default 4000 tokens, token ≤ 80 chars).
* Index only public posts (`nh_seo_is_public_post`) of `post` and `page`. Hooks: `save_post`,
  `transition_post_status`, `deleted_post`, `set_object_terms`, term edit/delete (schedule batched
  reindex of the term's posts through WP-Cron, never a blocking loop). Never index revisions/autosaves.
* Query integration for the **main search query and REST/other `WP_Query` searches** (`is_search()` /
  non-empty `s`): when the index is ready and mode allows, replace the LIKE clauses via `posts_search`,
  join the index via `posts_join`, order via `posts_orderby` (score = title×3 + taxonomy×2 + all×1,
  then date) unless the caller set `orderby`. Required terms use boolean `+term*`; drop tokens shorter than
  3 chars and InnoDB stop-words; if no usable token remains, do nothing (native search). When the strict
  query returns 0 rows, retry once with OR semantics and expose `nh_seo_search_was_relaxed()`.
  Any SQL error ⇒ disable the index for 10 minutes (transient) and fall back to native search.
  All SQL through `$wpdb->prepare`; user text only enters the query as folded `[a-z0-9]` tokens.
* State option `nh_seo_search_state`: `ready` (set only after a complete build), `total`, `indexed`,
  `last_run`, `last_error`. Modes: `auto` (use index when ready), `native`, `index` (use index when it exists).
* Tools panel (`nh_seo_render_tools_panel`): status, **Build / rebuild index** with an AJAX batch loop
  (200 posts per request, progress bar, `manage_options` + nonce), **Clear index**. Filter
  `nh_seo_search_status` supplies the one-line status for the Status box.
* Tests must prove: folding of Vietnamese text ("Thuê xe máy Hà Nội" ⇄ "thue xe may ha noi"), unpublished
  and password posts never indexed, update/delete/status-change keep the index correct, relevance order
  (title match beats body-only match), fallback to native when not ready / short tokens / SQL error, and
  no SQL injection through `s`. Include a performance test with ≥ 2,000 posts asserting a single query for
  the results and a sane time bound.

## Module `cache` (agent P2)

* Collect the URLs affected by a content change during the request and purge **once at shutdown**:
  the post, home, posts page, every category archive and ancestors, tag archives, the author archive,
  the Pillar page, and the previous/next neighbours of the post in its silo. Triggers: publish, update of a
  published post, unpublish/trash/delete, term changes affecting archives.
* Dispatch (each guarded by `function_exists`/`has_action`): LiteSpeed Cache (`litespeed_purge_url`),
  WP Rocket (`rocket_clean_files`), W3 Total Cache (`w3tc_flush_url`), WP Super Cache
  (`wpsc_delete_url_cache`), plus the generic action `nh_seo_purge_urls( array $urls )`.
  Respect `purge_caches`. No remote requests.
* Tests use spies on those hooks; they cannot prove behaviour against the real cache plugins, and
  the test docblocks must say so.

## Module `cli` (agent P2)

`wp nh-seo reindex [--batch=200] [--post-type=post,page]`, `wp nh-seo index-status`, `wp nh-seo purge --post=<id>`.
Registered only when `WP_CLI` is defined.

## Uninstall (agent P1)

`uninstall.php` removes only the plugin's options, transients, scheduled events and the search index
table. **It never deletes post meta, term meta, user meta, terms or content**: editorial data must
survive removal of the plugin.

## Definition of done per module

* PHPUnit tests in `tests/phpunit/plugin/test-<module>-*.php` extending `NH_Unit_Test_Case`; all green.
* `vendor/bin/phpcs plugins/nguyen-ha-seo-companion tests/phpunit/plugin` has no errors and no warnings.
* `php -l` clean; no PHP notices/deprecations in the run.
* A short `Report` section appended to your final message: what exists, what is untested, known limits.
