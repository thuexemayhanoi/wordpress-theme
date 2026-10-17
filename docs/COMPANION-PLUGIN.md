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

Implementation notes and hooks (all added by the schema module unless marked shared):

* The mode is worked out when the page is printed (`wp_head`), not when the plugin loads, so a SEO plugin
  that loads after this one (alphabetical order: `wordpress-seo` comes after `nguyen-ha-…`) is still seen.
  **Shared:** `nh_seo_schema_mode()` applies the filter `nh_seo_schema_mode( string $mode )`; only `full`,
  `extras` and `off` are accepted. The theme's render tests use it to switch the plugin's JSON-LD off.
* The plugin prints no `<title>`, canonical, robots or description tag in any mode and registers nothing on
  `wp_robots`, `get_canonical_url`, the title filters or the hooks of the SEO plugins (a test checks it).
* FAQ detection (`NH_SEO_Schema_Detect`): FAQ blocks in the content, also inside published reusable blocks
  (at most 10 reusable blocks and 3 levels are opened per post, loops end). Filters
  `nh_seo_external_faq_blocks( string[] $names )` (default `yoast/faq-block`, `rank-math/faq-block`),
  `nh_seo_has_external_faq( bool $detected, ?WP_Post $post )`, `nh_seo_faq_min_items( int )` (default 2).
* Local SEO detection: filter `nh_seo_local_seo_probes( array )` (constants, classes and an optional module
  option per plugin; defaults: Yoast Local SEO `WPSEO_LOCAL_VERSION`, Rank Math with its `local-seo` module)
  and `nh_seo_has_external_localbusiness( bool )`.
* `nh_seo_schema_graph( array $nodes, string $mode, array $context )` runs in `full` and `extras` mode.
  `priceRange` is printed on the LocalBusiness only when the owner typed it into the profile.
* How the tests simulate the SEO plugins: Yoast and Rank Math are **not installed** in any test. Detection
  goes through the filter `nh_seo_detected_seo_plugin` (the constant `WPSEO_VERSION` is deliberately not
  defined: constants cannot be undefined and would change every later test of the PHP process), FAQ blocks are
  block markup in the post content, Local SEO add-ons are made-up constants behind `nh_seo_local_seo_probes`.
  The line of `nh_seo_detect_seo_plugin()` that looks for the real constants is not executed by any test.

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

### Search module: API and contracts

What other code (themes, plugins, WP-CLI scripts) may rely on:

* **Function** `nh_seo_search_was_relaxed( ?WP_Query $query = null ): bool`: true when the displayed results
  are the "any of the words" ones because no post had every word (main query when `$query` is null).
* **Theme contract: filter `nh_editorial_search_index_active( bool $active )`.** The theme asks it as
  `apply_filters( 'nh_editorial_search_index_active', false )` and, on a site with more than 15,000
  articles, offers search-as-you-type suggestions only when the answer is true (the built-in search reads
  every article: with 50,000 articles core search takes 3.7 s for two words and 3.5 s for a rare word).
  The search bootstrap hooks it to `NH_SEO_Search_Query::filter_index_active()`: the answer is **true exactly
  when the index is built and serving queries**, i.e. `NH_SEO_Search_State::is_active()` (mode is not
  `native`, the build is complete in `auto` mode or the table exists in `index` mode, no error pause is
  running) **and** the filter `nh_seo_search_enabled` has not switched it off (asked with an empty
  `WP_Query`, so a callback that disables the index only for some queries cannot be seen). It never turns an
  earlier `true` into `false`. It costs no query in `native` mode and no pause lookup while the index is
  not built; tests compare the answer with what a real search does in eight index states
  (`test-search-active-filter.php`). The state is not looked up per table: if the table disappears behind the
  plugin's back while the state says ready, the first failing search pauses the index and the answer turns false.
* **Filters:** `nh_seo_search_enabled( bool $enabled, WP_Query $query )` (default true; keep one query on
  native search), `nh_seo_search_post_types( string[] )` (default `post`, `page`; a query that may return another
  searchable type stays native), `nh_seo_search_taxonomies( string[] )` (names that count as taxonomy matches),
  `nh_seo_search_native_fallback_limit( int )` (see below), `nh_seo_search_batch_size( int )` (10 to 500, default
  200), `nh_seo_search_inline_limit( int )` (posts re-indexed inside one request, default 50, the rest goes to
  WP-Cron), `nh_seo_search_body_token_cap( int )` (default 4000), `nh_seo_search_stopwords( string[] )`,
  `nh_seo_search_max_tokens( int )`, `nh_seo_search_time_budget( float )`, `nh_seo_search_status( string )` (the
  one-line status of the Status box and the Tools panel; plain text).
* **Storage** (what P1's `uninstall.php` must remove): table `{prefix}nh_search_index`, option
  `nh_seo_search_state` (not autoloaded), transient `nh_seo_search_off` (the error pause), WP-Cron hooks
  `nh_seo_search_reindex_term` and `nh_seo_search_reindex_ids`. AJAX actions `nh_seo_search_build` and
  `nh_seo_search_clear` (`manage_options`, nonce, POST only, never for visitors).
* **How a search is answered.** Stage `strict` (every word, `MATCH … AGAINST ('+tok* +tok*' IN BOOLEAN MODE)`),
  then `relaxed` (any word) when `strict` finds nothing on the first page, then native WordPress search. Words
  of one or two letters and InnoDB stop-words are not sent to the index; a search with none left, a phrase, an
  exact or an excluded-word (`-word`) search, custom search columns and any query that could return other than
  public posts of indexed types stay native. `fields=ids` queries (the REST search) use the index but are not
  relaxed. Private posts are not indexed. A database error pauses the index for ten minutes and native search
  answers the same request.
* **Native fallback limit.** Native search is the safety net behind an empty index answer (it finds text inside
  words, the index finds the start of words), but it reads every post: on a large site it must not run for a
  nonsense word or a typo. It runs only while the index holds at most `nh_seo_search_native_fallback_limit`
  posts (default 15,000; the number of the last build, or the rows when no build recorded one); 0 never, a
  negative number always. A database error always hands the request to native search.

Measured on a private site with about 50,000 articles (the `tests/bin/seed-bulk.php` fixture plus a few
rare-word posts; 50,016 posts and pages at build time). PHP built-in server, 4 cores shared with other test
processes, MariaDB 10.11 with the default 128 MB buffer pool while `wp_posts` is 316 MB. "Page time" is the
server time of the whole page from `tests/bin/mu-metrics.php`, repeated requests, best to median:

| search | native WordPress search | with the index |
|---|---|---|
| a word that 1 to 400 articles contain | 3.4 to 4.4 s | 46 to 75 ms (25 to 35 ms of it database) |
| a word in no article, one or two words | 3.3 to 6.0 s | 43 to 50 ms |
| a word every article contains (the fixture has only 80 distinct words), 1 to 3 words | 2.1 to 4.8 s | 0.9 to 2.6 s |
| the same with 4 words | not measured | about 2.2 s |
| a REST suggestion request: rare word / no match / common word | about 4 s / 4 s / 2.8 s | 17 to 50 ms / 15 ms / 1.2 to 3.7 s |
| a search made only of words of one or two letters | 2.0 to 2.9 s | the same (native search answers it) |

The theme's suggestion box was checked end to end on that site: before the build the home page has no
`data-suggest-url` (the theme's guard for more than 15,000 articles), after the build it has it, in `native`
mode it is gone again, in `auto` mode it is back.

Build: `wp nh-seo reindex` indexed 50,016 posts in 49.7 s (200 per batch, about 1 ms per post, one CLI process).
Size on disk: 133 MB for the table and its three full-text indexes (the `wp_posts` table is 316 MB, its text
216 MB). Saving one post later indexes it once at shutdown; create, rename and unpublish were checked against
the same site (the old word disappears, the new one is found).

The cost of a search grows with the number of articles that match, not with the size of the site: the time goes
into joining and ranking every matching article, so a word that nearly every article contains is the slow case
(0.9 to 2.6 s here). Ranking inside the index first and joining only the displayed page would cut it to a few
hundred milliseconds; that is not implemented, because it also has to count the matches separately and to stay
exact next to other plugins' query filters.

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
* Public hooks: action `nh_seo_purge_urls( string[] $urls )` (once per request, the generic integration point),
  filter `nh_seo_purge_post_urls( string[] $urls, WP_Post $post )` (add feeds, date archives or theme pages),
  filter `nh_seo_purge_max_urls( int )` (default 300: a larger set is cut to its first URLs), action
  `nh_seo_purge_failed( string $integration, Throwable $error )` (one integration failing never stops the
  others). `NH_SEO_Cache::urls_for_post( $post_id )` returns the URLs a change of a post makes stale (used by
  `wp nh-seo purge`). URLs are absolute http(s) URLs; revisions, auto-drafts and non-viewable types never
  purge anything.

## Module `cli` (agent P2)

`wp nh-seo reindex [--batch=<10-500>] [--post-type=<types>]`, `wp nh-seo index-status [--format=table|json|csv|yaml]`,
`wp nh-seo purge --post=<id> [--dry-run]`. Registered only when `WP_CLI` is defined (`includes/cli/bootstrap.php`).

* `NH_SEO_CLI_Command` never touches `WP_CLI`: every message goes through an `NH_SEO_CLI_Output` object (the
  WP-CLI adapter `NH_SEO_CLI_Output_WPCLI` by default), so the commands are plain PHP and are tested without
  WP-CLI (`NH_CLI_Test_Output` records the output; `error()` throws like `WP_CLI::error()` halts).
* `reindex` without `--post-type` empties and rebuilds the whole index and marks it ready at the end. With
  `--post-type` only those types are re-indexed (leftovers are pruned, readiness is unchanged). `--batch` is
  validated as a whole number and clamped to 10 to 500 with a warning. A flag given without a value (WP-CLI passes
  `true`) is never read as a number, so `--post` alone cannot purge post 1.
* `index-status` prints one fact per row (`key`, `value`): status, mode, used_for_searches, ready, building,
  table, posts_in_index, posts_at_last_build, last_run, paused_until, last_error, rebuild_recommended. The keys
  are an interface for scripts and are not translated; the `status` line is.
* `purge` lists the URLs, hands them to the page caches once and ignores the "purge automatically" setting (an
  explicit command), saying so; `--dry-run` only lists.
* Run with the real WP-CLI 2.12 on a site with 50,000 articles: the table and JSON output of `index-status`,
  `reindex` (one process, 49.7 s) and the error messages with exit code 1 for missing or invalid options. The
  WP-CLI adapter itself is not covered by PHPUnit (it is one line per method).

## Uninstall (agent P1)

`uninstall.php` removes only the plugin's options, transients, scheduled events and the search index
table. **It never deletes post meta, term meta, user meta, terms or content**: editorial data must
survive removal of the plugin.

## Definition of done per module

* PHPUnit tests in `tests/phpunit/plugin/test-<module>-*.php` extending `NH_Unit_Test_Case`; all green.
* `vendor/bin/phpcs plugins/nguyen-ha-seo-companion tests/phpunit/plugin` has no errors and no warnings.
* `php -l` clean; no PHP notices/deprecations in the run.
* A short `Report` section appended to your final message: what exists, what is untested, known limits.
