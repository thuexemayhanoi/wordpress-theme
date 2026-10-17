# Data contract: theme ⇄ companion plugin ⇄ AI Writer

This is the single source of truth for every piece of data the theme reads and the companion
plugin (`plugins/nguyen-ha-seo-companion`) stores. The AI Writer workflow can publish through the
standard WordPress REST API using only the keys below. **Nothing here is required**: a post without
any of these fields still renders correctly.

Naming rules: public keys use the `nh_` prefix; options and hooks use `nh_seo_` (plugin),
`nh_editorial_` (theme) or `nguyen_ha_` (cross-component hooks).

## 1. Post meta (posts and pages, REST-enabled)

| Key | Type | Limit | Purpose |
|---|---|---|---|
| `nh_summary` | string | 800 chars | Answer-first summary shown at the top ("Quick answer"). Plain text. |
| `nh_takeaways` | string[] | 10 items × 300 chars | Key takeaways. Show only when the article really has them. |
| `nh_faq` | `{question, answer}[]` | 20 items; Q 300 chars; A 3000 chars | Per-article FAQ. Answers may contain `a`, `p`, `br`, `strong`, `b`, `em`, `i`, `ul`, `ol`, `li`, `code`. |
| `nh_sources` | `{title, url, publisher}[]` | 15 items | References. `url` must be `http(s)`. |
| `nh_content_updated` | `YYYY-MM-DD` | — | The date the content was *really* updated. Invalid or future dates are dropped. |
| `nh_topic_cluster` | string | 120 chars | Alias written by workflows: cluster **slug or name**. Synced to the `topic_cluster` taxonomy (see §2). |
| `nh_primary_cluster` | int | — | Term ID of the primary Topic Cluster (set automatically by the alias sync; editable). |
| `nh_pillar_id` | int | — | Post/page ID of the Pillar for this article. Must be published and public. Overrides term-level pillars. |
| `nh_hub_term` | string | — | Pages only. `category:<id>` or `topic_cluster:<id>`: the topic a Pillar/Hub page lists. |
| `nh_local_business` | bool | — | This page has verified local-business context (enables `LocalBusiness` schema). |
| `nh_keywords` | string | 300 chars | Extra comma separated keywords for the internal search index only. |

Sanitization and limits are implemented once in `plugins/nguyen-ha-seo-companion/includes/functions.php`
(`nh_seo_sanitize_*`). The theme re-validates on read and escapes on output.

**Who can read these fields over REST.** Published posts and pages show every key to everyone, with two
exceptions that follow what a visitor can see on the site:

* `nh_keywords` is an editor's working note (the editor screen says visitors never see it): REST shows it only
  to users who can edit that post, such as the publishing workflow.
* A password-protected post or page keeps **all** `nh_*` keys behind its password, as core does for the body: they
  are removed from the response unless the request carries the right `password` parameter (or the user can edit the
  post). Core protects only `content` and `excerpt`.

## 2. Taxonomy `topic_cluster`

* Flat, **non-public** (no front-end URLs, not in sitemaps, no archives): clusters group content
  for internal linking only, so they cannot cannibalize category pages.
* REST: `/wp/v2/topic_clusters`; the post field is `topic_clusters` (array of term IDs). Visitors and subscribers
  get only clusters that a published post uses (clusters are created from a hidden field when any post is saved,
  drafts included, and core would list every term of a REST-enabled taxonomy); people who can edit posts see all.
* Term meta `nh_pillar_id` (int) links a cluster (and a `category`) to its Pillar page.
* **Primary cluster** of a post: `nh_primary_cluster` if that term is assigned to the post, otherwise
  the assigned term with the lowest term ID.
* **Alias sync**: saving `nh_topic_cluster` (slug or name) finds the term by slug, then name; when it
  does not exist and the setting *Auto-create clusters* is on, the term is created. The term is added
  to the post and stored as `nh_primary_cluster`. Existing terms are never removed. The alias is applied once, when it
  is written or changed (backslashes in the name included), so later saves never override an editor's choice.

## 3. Silo, Hub and Pillar resolution (theme)

For a post, in this order:

1. **Silo term** = primary cluster if any, otherwise the post's **primary category**.
2. **Primary category** (canonical, also implemented by `nh_seo_get_primary_category()`):
   Yoast / Rank Math primary category if it is assigned to the post; otherwise the deepest assigned
   category (ties: lowest term ID), ignoring the default *Uncategorized* unless it is the only one.
3. **Pillar** = `nh_pillar_id` on the post → `nh_pillar_id` on the primary cluster → on the primary
   category → on its ancestors (nearest first). The target must be published, public and not the post
   itself. No pillar mapped ⇒ the box falls back to the top-level category archive (the Hub).

**Related posts** (3 by default), each stage only fills what is still missing, published posts only,
never the current post, never password-protected posts:
cluster → primary category → parent category (with children) → top-level category (with children).

**Previous / Next** are the neighbours by publish date inside the silo term (ties broken by ID).

## 4. Breadcrumb (visible in the theme, structured data in the plugin)

Whoever prints the `BreadcrumbList` owns the trail, and the page shows the same list:

* An SEO plugin that prints it (Yoast SEO): the theme shows the plugin's own crumbs, read from
  `YoastSEO()->meta->for_current_page()->breadcrumbs` (`nh_editorial_seo_plugin_breadcrumbs()`), minus the crumbs the
  plugin hides from its structured data. They follow the plugin's settings: a fresh Yoast lists no category for a
  post and says "You searched for …"; choose a taxonomy for breadcrumbs in Yoast to get the category path in both
  places. The filter `nh_editorial_seo_plugin_crumbs` hands over the crumbs of another SEO plugin;
  `nh_editorial_breadcrumbs_follow_seo_plugin` (false) keeps the theme's own trail.
* The companion plugin in *full* mode, or no plugin at all: both build the same trail
  (`nh_seo_breadcrumb_trail()` is the reference; the theme's builder is `nh_editorial_own_breadcrumb_items()`), below.

* Single post: Home › ancestors of the primary category › primary category › title.
* Page: Home › published ancestor pages › title.
* Category / hierarchical taxonomy archive: Home › ancestors › term. Tag / other taxonomy: Home › term.
* Author: Home › author. Search: Home › "Search results for: …", whose URL is the address the visitor reached
  (`/?s=word` or `/search/word/`), not a second form of it. Date archive: Home › archive title.
* Front page and 404: no trail. Paged archives never add "Page N".
* The URL of an archive crumb is the archive itself: no "page N" and none of the visitor's query string
  (`?utm_source=…`, `?ref=…`) — date and post type archives are built from their query, anything else from the paged
  link minus the arguments that are not WordPress query variables (`nh_seo_archive_url()` in the plugin,
  `nh_editorial_archive_url()` in the theme: the same algorithm).

A PHPUnit parity test asserts that the theme's own trail equals the plugin's trail; `NH_Test_Breadcrumb_Source` covers
the trail taken from the SEO plugin, and the `seo-plugins` job of the CI workflow compares the visible breadcrumb with
the `BreadcrumbList` that the real Yoast SEO prints.

## 5. User meta

| Key | Purpose |
|---|---|
| `nh_job_title` | Shown in the author box and `Person.jobTitle`. |
| `nh_sameas` | Newline separated profile URLs for `Person.sameAs` and author box links. |

The author box also reads the WordPress bio (`description`) and, when present, the Yoast social
fields (`facebook`, `twitter`, `instagram`, `linkedin`, `youtube`, `wikipedia`). Nothing is invented:
a missing bio simply hides the box (setting `Author box: with bio / always / never`).

## 6. Options

* `nh_seo_business` — verified business profile. **Empty by default; never pre-filled.** Keys:
  `name`, `legal_name`, `type` (schema.org subtype), `url`, `phone`, `email`, `street`, `locality`,
  `region`, `postal`, `country` (ISO 3166-1 alpha-2), `lat`, `lng`, `hours[]` (schema.org text such as
  `Mo-Su 09:00-21:00`), `price_range` (free text, optional), `has_map`, `social[]`, `logo_id`.
* `nh_seo_settings` — `schema_mode` (`auto|extras|full|off`), `search_mode` (`auto|native|index`),
  `auto_cluster_terms`, `fallback_render`, `purge_caches`, `localbusiness_on_home`.
* `nh_seo_search_state` — search index status (managed by the plugin).

The theme reads the profile through the filter `nguyen_ha_business_profile`; the plugin supplies it.
Without the plugin the filter returns an empty array and the theme shows no contact block.

## 7. Theme support handshake

The theme declares what it renders itself:

```php
add_theme_support( 'nguyen-ha-companion', array(
	'summary' => true, 'takeaways' => true, 'faq' => true, 'sources' => true, 'updated' => true,
) );
```

The plugin renders a fallback block (through `the_content`) **only for features the theme does not
declare** and only when *Fallback rendering* is on. Structured data (`FAQPage`) is emitted only when
the FAQ is visible (`nh_seo_faq_is_visible()`): structured data must never describe invisible content.

The theme keeps that promise on every template that shows a post or page: the single post, the page, the Pillar
page, the full width page and the static front page print the FAQ they are given (a PHPUnit test renders each of
them and compares the questions in the page with the questions in the JSON-LD). A child theme or page builder
that renders a template without the FAQ answers `false` through the filter `nh_seo_faq_is_visible( bool $visible,
int $post_id )` for those posts.

## 8. REST examples (AI Writer)

```jsonc
// POST /wp-json/wp/v2/posts   (Application Password, Basic auth)
{
  "title": "Which scooter should a first-time visitor rent?",
  "status": "publish",
  "categories": [12],                 // parent/child: send the child (and parent when you want both)
  "tags": [31, 32],
  "featured_media": 456,
  "meta": {
    "nh_summary": "Pick an automatic 110–125 cc scooter for city riding; a manual only if you are experienced.",
    "nh_takeaways": ["Automatic scooters are easiest in traffic.", "Check the helmet and mirrors before leaving."],
    "nh_faq": [
      { "question": "Can I rent without experience?", "answer": "<p>Confirm requirements with the shop before booking.</p>" }
    ],
    "nh_sources": [ { "title": "Official guide", "url": "https://example.org/guide", "publisher": "Example" } ],
    "nh_content_updated": "2026-10-01",
    "nh_topic_cluster": "scooter-rental-basics",
    "nh_pillar_id": 123
  }
}
```

Publishing never needs the plugin: the theme simply shows what exists. Without the plugin the
`meta` keys are not registered for REST, so the plugin must be active for the workflow to write them.
After publishing, theme caches (related posts, adjacent posts, menus, home sections) are invalidated
automatically; page-cache plugins are purged by the plugin (`nh_seo_purge_urls`).

## 9. Hooks

| Hook | Type | Owner | Purpose |
|---|---|---|---|
| `nguyen_ha_business_profile` | filter | plugin supplies, theme reads | Verified business profile array. |
| `nh_seo_business_profile` | filter | plugin | Adjust the stored profile. |
| `nh_seo_allowed_html` | filter | plugin | Tags allowed in FAQ answers. |
| `nh_seo_detected_seo_plugin` | filter | plugin | Declare an SEO plugin unknown to the detector. |
| `nh_seo_breadcrumb_trail` | filter | plugin | Adjust the canonical trail. |
| `nh_seo_schema_graph` | filter | plugin | Last-mile edit of the JSON-LD graph. |
| `nh_seo_purge_urls` | action | plugin | URLs that must be purged from page caches. |
| `nh_seo_faq_is_visible` | filter | plugin | Withdraw the claim that a post's FAQ is shown (a template that hides it). |
| `nh_seo_search_activation_repair_limit` | filter | plugin | Posts the repair at activation re-indexes in that request (default 200). |
| `nh_editorial_cache_version` | filter | theme | Cache-busting version for theme fragments. |
| `nh_editorial_breadcrumb_items` | filter | theme | Last word on the items of the visible breadcrumb (name and url). |
| `nh_editorial_seo_plugin_crumbs` | filter | theme | Crumbs of an SEO plugin other than Yoast (arrays of `text`, `url`, optionally `hide_in_schema`); Yoast's are read by the theme. |
| `nh_editorial_breadcrumbs_follow_seo_plugin` | filter | theme | Return false to keep the theme's own trail even when an SEO plugin prints a different `BreadcrumbList`. |
| `nh_editorial_related_count` | filter | theme | Number of related posts. |
