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
* **Structured data.** FAQPage and LocalBusiness data next to the graph of your SEO plugin (Yoast SEO, Rank Math and others are detected), or a complete minimal graph when no SEO plugin is active. FAQ data is only output when the FAQ is visible on the page, and ratings, reviews and offers are never output.
* **Internal search index.** An optional index that makes the site search fast and relevant on sites with tens of thousands of posts, with an automatic fallback to the WordPress search.
* **Cache purging.** Affected URLs are purged once per request from LiteSpeed Cache, WP Rocket, W3 Total Cache and WP Super Cache.
* **WP-CLI.** `wp nh-seo reindex`, `wp nh-seo index-status` and `wp nh-seo purge`.

**What it never does**

* It never creates, changes or deletes your posts, pages, slugs or canonical URLs, and it never adds a noindex.
* It never invents business data, ratings, reviews, prices or author credentials.
* It makes no remote requests and loads no external libraries.

== Installation ==

1. Upload the `nguyen-ha-seo-companion` folder to `/wp-content/plugins/`, or install the zip from Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Open Settings > Nguyen Ha SEO Companion. Fill in the Business profile with information that is accurate and verifiable, or leave it empty.
4. Optional: open the Tools tab and build the search index.

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

No. When an SEO plugin owns the titles and the structured data graph, the companion only prints separate FAQPage and LocalBusiness blocks and never edits that plugin's graph. The primary category chosen in those plugins is respected.

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
* `nh_seo_detected_seo_plugin`, `nh_seo_breadcrumb_trail`, `nh_seo_schema_graph`, `nh_seo_purge_urls`: see the data contract in the theme repository.

== Changelog ==

= 1.0.0 =
* First release: Topic Clusters, answer-first content with FAQ and sources, fallback rendering, business profile, structured data, internal search index, cache purging and WP-CLI commands.

== Upgrade Notice ==

= 1.0.0 =
First release.
