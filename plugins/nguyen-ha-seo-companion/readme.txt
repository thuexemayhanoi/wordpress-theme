=== Nguyen Ha SEO Companion ===
Contributors: thuexemayhanoi
Tags: seo, faq, structured data, internal linking, topic clusters
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Keeps SEO and editorial data independent of your theme: topic clusters, quick answers, FAQ, sources, business profile, structured data and search.

== Description ==

Nguyen Ha SEO Companion is the companion plugin of the Nguyen Ha Editorial theme. It owns everything that must survive a theme change, so you can switch themes without losing a single summary, FAQ, cluster or Pillar. The theme only presents the data; this plugin stores it, validates it and exposes it to publishing workflows.

**What it does**

* **Answer-first content.** A meta box on posts and pages for a quick answer, key takeaways, an FAQ, sources, the date the content was really updated, extra search keywords, a Pillar page ID and a local-business flag.
* **Topic Clusters.** A flat, non-public taxonomy that groups articles for internal linking. Clusters have no public pages, archives or sitemap entries, so they can never compete with your category pages. Each article can have a primary cluster; categories and clusters can point to a Pillar page; Pillar and Hub pages can list a category or a cluster.
* **Fallback rendering.** If the active theme does not display the quick answer, takeaways, FAQ, sources or update date itself, the plugin adds exactly the missing parts to the article with a tiny stylesheet. A theme opts out feature by feature.
* **Verified business profile.** One settings form for the business name, type, contact details, address, opening hours and social profiles. Nothing is pre-filled and nothing is guessed. The theme reads it through a filter.
* **Structured data.** FAQPage and LocalBusiness data next to the graph of your SEO plugin (Yoast SEO, Rank Math and others are detected), or a complete minimal graph when no SEO plugin is active. FAQ data is only output when the FAQ is visible on the page and no FAQ block of Yoast SEO or Rank Math already prints it. LocalBusiness data needs a complete business profile and a page you flagged (or the front page, when you allow it). Ratings, reviews and offers are never output.
* **Internal search index.** An optional index that makes the site search fast and relevant on sites with tens of thousands of posts. Accents do not matter ("thue xe may" finds "Thuê xe máy"), titles and category, tag and cluster names count more than the text, and the best match comes first. It needs InnoDB full-text indexes (MySQL 5.6, MariaDB 10.0.5 or newer). Until you build it, and whenever it cannot be used, WordPress searches as before.
* **Cache purging.** When content is published, updated, unpublished or deleted, the URLs it makes stale (the post, the home page, its category and tag archives, the author page, its Pillar page and its neighbours) are purged once per request from LiteSpeed Cache, WP Rocket, W3 Total Cache and WP Super Cache.
* **WP-CLI.** `wp nh-seo reindex` rebuilds the search index in batches, `wp nh-seo index-status` shows its state and `wp nh-seo purge --post=<id>` purges the caches for one post.

**What it never does**

* It never creates, changes or deletes your posts, pages, slugs or canonical URLs, and it never adds a noindex.
* It never invents business data, ratings, reviews, prices or author credentials.
* It makes no remote requests and loads no external libraries.

== Installation ==

1. Upload the `nguyen-ha-seo-companion` folder to `/wp-content/plugins/`, or install the zip from Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Open Settings > Nguyen Ha SEO Companion. Fill in the Business profile with information that is accurate and verifiable, or leave it empty.
4. Optional: open the Tools tab and build the search index (or run `wp nh-seo reindex`).

No other setup is needed. Posts without any of the fields below render exactly as before.

== Frequently Asked Questions ==

= Does the plugin work without the Nguyen Ha Editorial theme? =

Yes. Every field is optional. With another theme the fallback renderer shows the quick answer, takeaways, FAQ, sources and update date inside the article. A theme can take over any of them with `add_theme_support( 'nguyen-ha-companion', array( 'summary' => true, 'takeaways' => true, 'faq' => true, 'sources' => true, 'updated' => true ) )`.

= Can an automated publishing workflow fill these fields? =

Yes, through the standard REST API with an Application Password. Send the keys below in the `meta` object of `POST /wp-json/wp/v2/posts`.

`nh_summary` (string, 800 characters), `nh_takeaways` (array of up to 10 strings), `nh_faq` (array of up to 20 objects with `question` and `answer`; answers may contain a, p, br, strong, b, em, i, ul, ol, li and code), `nh_sources` (array of up to 15 objects with `title`, `url` and `publisher`), `nh_content_updated` (YYYY-MM-DD), `nh_topic_cluster` (a cluster slug or name), `nh_primary_cluster` (term ID), `nh_pillar_id` (post ID), `nh_local_business` (boolean), `nh_keywords` (string). Pages also accept `nh_hub_term` (`category:<id>` or `topic_cluster:<id>`).

Values are sanitized on the way in: HTML is stripped, links must be http or https, lists are cut at their limit instead of failing the request, and invalid dates, Pillar IDs and cluster IDs are dropped. A value of the wrong type is rejected with a 400. Unknown keys are ignored. Sending `null` removes a key. Writing requires the permission to edit the post.

= How does the cluster alias work? =

Writing `nh_topic_cluster` finds the Topic Cluster by slug, then by name. If it does not exist and "Create Topic Clusters automatically" is on, it is created. The cluster is added to the post, existing clusters are never removed, and the cluster becomes the primary one. An alias is applied once, when it is written or changed, so saving the post later never overrides a primary cluster chosen by an editor. Send the alias again through the REST API to apply it again. Like tags, clusters can be created by anyone who may assign them.

= What happens when I delete the plugin? =

Only the plugin's own storage is removed: its settings (including the business profile), transients, scheduled events and the search index table. Your posts, pages, terms, users and every piece of editorial data (summaries, FAQs, sources, clusters, Pillars, author details) are kept.

= Does the plugin send any data anywhere? =

No. It makes no remote requests and does not track visitors. The business profile and the other settings are stored in your own database and are only output on your own site.

= Will it conflict with Yoast SEO or Rank Math? =

It is built not to. When an SEO plugin owns the titles and the structured data graph, the companion prints no titles, canonical links, robots tags or graph of its own: only separate FAQPage and LocalBusiness blocks, and never edits that plugin's graph. A FAQ block of Yoast SEO or Rank Math in the content (also inside a reusable block) and a Local SEO add-on are recognised, and the companion then prints nothing of its own for them. The primary category chosen in those plugins is respected.

= How does the internal search index work, and what are its limits? =

The index is a separate table with a folded copy of every public post and page: the title, the category, tag and cluster names, the extra keywords and the words of the text. Your content is never changed. It is kept up to date when you save, trash, delete or re-categorise a post; a change that touches thousands of posts at once (renaming a category) is refreshed in the background by WP-Cron, and the Tools tab says when a rebuild is advised.

* It finds the start of words ("motor" finds "motorbike"), not text inside a word. Up to 15,000 indexed posts a search that finds nothing in the index is repeated with the WordPress search, which does find text inside words. Above that the empty answer stands, because the WordPress search then reads every post and takes seconds. The filter `nh_seo_search_native_fallback_limit` changes the limit.
* A search made only of words of one or two letters, a quoted phrase and a search with an excluded word ("-word") are answered by WordPress as before. Inside a longer search, words of one or two letters are ignored by the index, because full-text indexes do not hold them.
* When no post has every word, the results that have some of the words are shown and the theme can say so with `nh_seo_search_was_relaxed()`.
* Private posts are not indexed, and password protected posts never are. After an import that writes straight into the database, rebuild the index in the Tools tab or with `wp nh-seo reindex`.
* A database error pauses the index for ten minutes; WordPress searches meanwhile and the Tools tab shows why.

= Can my own cache or CDN be purged too? =

Yes: listen to the action `nh_seo_purge_urls`, which receives the list of URLs once per request. The built-in connections to LiteSpeed Cache, WP Rocket, W3 Total Cache and WP Super Cache use the public hooks and functions of those plugins and can be switched off with the setting "Purge page caches when content changes".

= Which languages are included? =

English and Vietnamese. The text domain is `nguyen-ha-seo-companion`; the template is in the `languages` folder.

= Why is a Pillar ID not accepted? =

A Pillar must be another published, public post or page. Drafts, private and password protected posts, the post itself and anything that is not a plain ID are ignored, and you are told when that happens.

== Developer notes ==

**Functions**

* `nh_seo_get_post_extras( $post_id )`: summary, takeaways, FAQ, sources and update date, sanitized.
* `nh_seo_render_summary()`, `nh_seo_render_takeaways()`, `nh_seo_render_faq()`, `nh_seo_render_sources()`, `nh_seo_render_updated()`: escaped HTML for a post (ID, object or the current post), or an empty string.
* `nh_seo_get_primary_cluster( $post )`: the primary Topic Cluster as a `WP_Term`, or null.
* `nh_seo_get_primary_category( $post )` and `nh_seo_breadcrumb_trail()`: the canonical primary category and breadcrumb trail.
* `nh_seo_get_business_profile()`: the verified business profile. The theme reads it with `apply_filters( 'nguyen_ha_business_profile', array() )`.

**Filters and actions**

* `nguyen_ha_business_profile`, `nh_seo_business_profile`, `nh_seo_business_types`: the business profile and the types offered for it.
* `nh_seo_allowed_html`: the tags allowed in FAQ answers.
* `nh_seo_cluster_can_create_term`: restrict (or allow) creating a cluster from an alias.
* `nh_seo_cluster_synced`: fires after an alias was applied to a post.
* `nh_seo_render_tools_panel`: other modules print their panel in the Tools tab.
* `nh_seo_search_status`: the one-line search index status shown in the Status tab.
* `nh_seo_detected_seo_plugin`, `nh_seo_breadcrumb_trail`: see the data contract in the theme repository.
* `nh_seo_schema_mode`, `nh_seo_schema_graph`, `nh_seo_faq_min_items`, `nh_seo_has_external_faq`, `nh_seo_external_faq_blocks`, `nh_seo_has_external_localbusiness`, `nh_seo_local_seo_probes`: the structured data mode, the nodes that are printed and how another plugin's FAQ and LocalBusiness data is recognised.
* `nh_seo_search_enabled`, `nh_seo_search_post_types`, `nh_seo_search_taxonomies`, `nh_seo_search_native_fallback_limit`, `nh_seo_search_batch_size`: where the search index is used, what it holds, and how large a build step is. `nh_seo_search_was_relaxed()` tells a theme that the results are the partial ones.
* `nh_seo_purge_urls` (action), `nh_seo_purge_post_urls`, `nh_seo_purge_max_urls`, `nh_seo_purge_failed`: the URLs handed to the cache plugins, and a way to add to them or to listen.
* The Nguyen Ha Editorial theme asks `nh_editorial_search_index_active` whether a fast search index is serving the search, and offers suggestions while typing on very large sites only when it is. The plugin answers true only while its index is built and in use.

== Changelog ==

= 1.0.0 =
* First release: Topic Clusters, answer-first content with FAQ and sources, fallback rendering, business profile, structured data (FAQPage and LocalBusiness next to an SEO plugin's graph, or a minimal graph), internal search index that ignores accents (Vietnamese and other Latin scripts), cache purging for LiteSpeed Cache, WP Rocket, W3 Total Cache and WP Super Cache, and the WP-CLI commands `reindex`, `index-status` and `purge`.

== Upgrade Notice ==

= 1.0.0 =
First release.
