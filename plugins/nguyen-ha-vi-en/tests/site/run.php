<?php
/**
 * Site tests of the VI-EN plugin, over HTTP, against a real WordPress site that was seeded with seed.php.
 *
 *   php run.php --base=http://127.0.0.1:8080 --fixture=wp-content/nh-vien-fixture.json --suite=main [--seo=yoast|none] [--theme=editorial|other]
 *
 * Suites:
 *   snapshot  save the Vietnamese pages to --dir (run it with the gate closed and again with it open and no pairs)
 *   compare   compare --dir with --other: the Vietnamese pages must be identical
 *   main      the gate is open and the pairs exist: routes, redirects, languages, lists, switcher, SEO, security, admin
 *   inert     the gate is closed again while the pairs and the stored rules remain: nothing may change on the site
 *   homepage  the English home is a real page at /en/ that holds [nh_vien_posts]
 *   legacy    the active theme never calls wp_body_open() (tests/site/legacy-theme): one switcher, in the post
 *   deactivated  the plugin was deactivated after the English side had been live: no /en/ address may answer
 *
 * Exit code 0 when every check passed.
 *
 * @package Nguyen_Ha_VI_EN
 */

// Test tooling: runs from the command line only, never through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$opts = getopt( '', array( 'base:', 'fixture:', 'suite:', 'seo::', 'theme::', 'dir::', 'other::' ) );
$base = rtrim( isset( $opts['base'] ) ? $opts['base'] : 'http://127.0.0.1:8080', '/' );
$suite = isset( $opts['suite'] ) ? $opts['suite'] : 'main';
$seo   = isset( $opts['seo'] ) ? $opts['seo'] : 'none';
$theme = isset( $opts['theme'] ) ? $opts['theme'] : 'editorial';
$fx    = array();
if ( ! empty( $opts['fixture'] ) && is_file( $opts['fixture'] ) ) {
	$fx = json_decode( (string) file_get_contents( $opts['fixture'] ), true );
}

$failures = 0;
$checks   = 0;

/** Record one check. */
function ok( $label, $condition, $detail = '' ) {
	global $failures, $checks;
	++$checks;
	if ( $condition ) {
		echo "  ok    $label\n";
		return;
	}
	++$failures;
	echo "  FAIL  $label" . ( '' !== $detail ? "  [$detail]" : '' ) . "\n";
}

/** One HTTP request without following redirects. Returns status, headers, location and body. */
function http( $path, $options = array() ) {
	global $base;
	$headers = isset( $options['headers'] ) ? $options['headers'] : array();
	$http    = array(
		'method'          => isset( $options['method'] ) ? $options['method'] : 'GET',
		'follow_location' => 0,
		'ignore_errors'   => true,
		'timeout'         => 60,
		'header'          => implode( "\r\n", $headers ),
	);
	if ( isset( $options['body'] ) ) {
		$http['content'] = $options['body'];
	}
	$url  = 0 === strpos( $path, 'http' ) ? $path : $base . $path;
	$body = @file_get_contents( $url, false, stream_context_create( array( 'http' => $http ) ) );
	$raw  = isset( $http_response_header ) ? $http_response_header : array();
	$out  = array(
		'status'   => 0,
		'location' => '',
		'headers'  => array(),
		'cookies'  => array(),
		'body'     => false === $body ? '' : $body,
	);
	foreach ( $raw as $line ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $line, $m ) ) {
			$out['status'] = (int) $m[1];
		} elseif ( false !== strpos( $line, ':' ) ) {
			list( $name, $value )                  = explode( ':', $line, 2 );
			$out['headers'][ strtolower( $name ) ] = trim( $value );
			if ( 'set-cookie' === strtolower( $name ) ) {
				$out['cookies'][] = trim( explode( ';', $value )[0] );
			}
		}
	}
	$out['location'] = isset( $out['headers']['location'] ) ? $out['headers']['location'] : '';
	return $out;
}

/** JSON for a request body. */
function wp_json_encode_compat( $value ) {
	return json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

/** The path of an address on the site. */
function path_of( $url ) {
	global $base;
	$url = str_replace( $base, '', $url );
	return '' === $url ? '/' : $url;
}

/** Fetch a page once. */
function page( $path ) {
	static $cache = array();
	if ( ! isset( $cache[ $path ] ) ) {
		$cache[ $path ] = http( $path );
	}
	return $cache[ $path ];
}

/** An XPath over the HTML of a page. */
function xp( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
	libxml_clear_errors();
	return new DOMXPath( $doc );
}

/** Attribute values of the nodes an XPath query finds. */
function attrs( $xpath, $query, $attr ) {
	$values = array();
	foreach ( $xpath->query( $query ) as $node ) {
		$values[] = $node->getAttribute( $attr );
	}
	return $values;
}

/** The text of the first node an XPath query finds. */
function text_of( $xpath, $query ) {
	$nodes = $xpath->query( $query );
	return $nodes->length ? trim( preg_replace( '/\s+/', ' ', $nodes->item( 0 )->textContent ) ) : '';
}

/** Paths of the links inside the main content of a page that go to the given list of post paths. */
function post_links_in_main( $xpath, $post_paths ) {
	$found = array();
	foreach ( $xpath->query( '//main//a[@href]' ) as $a ) {
		$path = parse_url( path_of( $a->getAttribute( 'href' ) ), PHP_URL_PATH );
		if ( in_array( $path, $post_paths, true ) ) {
			$found[ $path ] = true;
		}
	}
	return array_keys( $found );
}

/** The head links of one kind, as a list of attribute strings. */
function head_links( $xpath, $rel ) {
	$out = array();
	foreach ( $xpath->query( "//head/link[@rel='$rel']" ) as $link ) {
		$out[] = array(
			'href'     => path_of( $link->getAttribute( 'href' ) ),
			'hreflang' => $link->getAttribute( 'hreflang' ),
		);
	}
	return $out;
}

/** The JSON-LD graphs of a page, flattened to a list of nodes. */
function json_ld_nodes( $xpath ) {
	$nodes = array();
	foreach ( $xpath->query( "//script[@type='application/ld+json']" ) as $script ) {
		$data = json_decode( $script->textContent, true );
		if ( ! is_array( $data ) ) {
			continue;
		}
		$items = isset( $data['@graph'] ) ? $data['@graph'] : array( $data );
		foreach ( $items as $item ) {
			$nodes[] = $item;
		}
	}
	return $nodes;
}

/** The first JSON-LD node of a type. */
function node_of_type( $nodes, $type ) {
	foreach ( $nodes as $node ) {
		$types = isset( $node['@type'] ) ? (array) $node['@type'] : array();
		if ( in_array( $type, $types, true ) ) {
			return $node;
		}
	}
	return null;
}

/** The suggestions of the REST search route: status and the paths of the posts it lists. */
function rest_suggestions( $term, $language = '' ) {
	$query = array(
		'search'   => $term,
		'type'     => 'post',
		'subtype'  => 'post',
		'per_page' => 50,
		'_fields'  => 'id,title,url',
	);
	if ( '' !== $language ) {
		$query['nh_vien_lang'] = $language;
	}
	$r    = http( '/wp-json/wp/v2/search?' . http_build_query( $query ) );
	$data = json_decode( $r['body'], true );
	$urls = array();
	if ( is_array( $data ) ) {
		foreach ( $data as $entry ) {
			if ( isset( $entry['url'] ) ) {
				$urls[] = parse_url( path_of( $entry['url'] ), PHP_URL_PATH );
			}
		}
	}
	return array( $r['status'], $urls );
}

// ---- The fixture, as known from seed.php.
$vi_paths = array( '/thue-xe-may-ha-noi/', '/kinh-nghiem-di-phuot/', '/bai-chua-dich/', '/bai-trung-trang/', '/bai-slug-rieng/', '/bai-ky-tu-dac-biet/', '/bai-co-ban-dich-nhap/' );
$en_paths = array( '/en/motorbike-rental-in-hanoi/', '/en/travel-tips-for-riders/', '/en/bikes-and-scooters/' );
for ( $i = 1; $i <= 14; $i++ ) {
	$vi_paths[] = '/bai-hang-loat-' . sprintf( '%02d', $i ) . '/';
	$en_paths[] = '/en/bulk-article-' . sprintf( '%02d', $i ) . '/';
}
$en_native_paths = array(
	'/motorbike-rental-in-hanoi/',
	'/travel-tips-for-riders/',
	'/bikes-and-scooters/',
	'/hidden-english-post/',
	'/collision-post/',
	'/english-draft/',
);

/** Fetch the pages the Vietnamese site is made of and save or compare them. */
function vietnamese_urls() {
	return array( '/', '/thue-xe-may-ha-noi/', '/kinh-nghiem-di-phuot/', '/bai-chua-dich/', '/bai-slug-rieng/', '/category/kinh-nghiem/', '/category/thue-xe/', '/page/2/', '/?s=xe', '/feed/', '/hello-world/', '/author/admin/', '/wp-sitemap.xml', '/nope-404-test/' );
}

if ( 'snapshot' === $suite ) {
	$dir = isset( $opts['dir'] ) ? $opts['dir'] : sys_get_temp_dir() . '/nh-vien-snap';
	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test tooling.
	}
	foreach ( vietnamese_urls() as $n => $url ) {
		$r = http( $url );
		file_put_contents( "$dir/$n.status", $r['status'] . ' ' . $r['location'] . ' ' . ( isset( $r['headers']['content-type'] ) ? $r['headers']['content-type'] : '' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test tooling.
		file_put_contents( "$dir/$n.body", $r['body'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test tooling.
	}
	echo 'saved ' . count( vietnamese_urls() ) . " Vietnamese pages to $dir\n";
	exit( 0 );
}

if ( 'compare' === $suite ) {
	$a = isset( $opts['dir'] ) ? $opts['dir'] : '';
	$b = isset( $opts['other'] ) ? $opts['other'] : '';
	echo "Vietnamese pages with the gate closed ($a) and open with no pairs ($b)\n";
	foreach ( vietnamese_urls() as $n => $url ) {
		$status_a = (string) @file_get_contents( "$a/$n.status" );
		$status_b = (string) @file_get_contents( "$b/$n.status" );
		$body_a   = (string) @file_get_contents( "$a/$n.body" );
		$body_b   = (string) @file_get_contents( "$b/$n.body" );
		// Only the timestamps WordPress itself puts into a feed or a sitemap may differ.
		$strip = function ( $html ) {
			return preg_replace( array( '#<lastBuildDate>.*?</lastBuildDate>#', '#<lastmod>.*?</lastmod>#', '#<sy:updatePeriod>.*?</sy:updatePeriod>#' ), '', $html );
		};
		ok( "identical: $url", '' !== $status_a && $status_a === $status_b && $strip( $body_a ) === $strip( $body_b ), 'status ' . trim( $status_a ) . ' / ' . trim( $status_b ) );
	}
	echo "\n$checks checks, $failures failed\n";
	exit( $failures ? 1 : 0 );
}

if ( 'inert' === $suite ) {
	echo "Gate closed again, pairs and stored rules still in the database\n";
	foreach ( array( '/', '/thue-xe-may-ha-noi/', '/motorbike-rental-in-hanoi/', '/category/kinh-nghiem/' ) as $url ) {
		$r  = page( $url );
		$xp = xp( $r['body'] );
		ok( "$url answers 200 and is Vietnamese", 200 === $r['status'] && 'vi' === $xp->query( '//html' )->item( 0 )->getAttribute( 'lang' ), 'status ' . $r['status'] );
		ok( "$url has no hreflang, no switcher", ! count( head_links( $xp, 'alternate' ) ) || ! array_filter( head_links( $xp, 'alternate' ), function ( $l ) { return '' !== $l['hreflang']; } ), '' );
		ok( "$url has no switcher markup", 0 === $xp->query( "//nav[contains(@class,'nh-vien-switcher')]" )->length );
	}
	foreach ( array( '/en/', '/en/motorbike-rental-in-hanoi/', '/en/nope/', '/en/category/travel-tips/', '/en/page/2/' ) as $url ) {
		$r = page( $url );
		ok( "$url is not served while the gate is closed (404)", 404 === $r['status'], 'status ' . $r['status'] );
	}
	list( $status, $urls ) = rest_suggestions( 'Bulk', 'vi' );
	ok( 'the suggestions ignore the language marker while the gate is closed', 200 === $status && $urls, $status . ' ' . implode( ',', $urls ) );
	ok( 'the search box carries no language marker while the gate is closed', false === strpos( page( '/' )['body'], 'nh_vien_lang' ) );
	$home = xp( page( '/' )['body'] );
	ok( 'English posts are in the Vietnamese home again, as they were before the plugin', count( post_links_in_main( $home, array( '/motorbike-rental-in-hanoi/', '/travel-tips-for-riders/' ) ) ) > 0 || count( post_links_in_main( $home, array( '/bulk-article-14/', '/bulk-article-13/' ) ) ) > 0 );
	echo "\n$checks checks, $failures failed\n";
	exit( $failures ? 1 : 0 );
}

if ( 'homepage' === $suite ) {
	echo "The English home is a real, published page at /en/ (with [nh_vien_posts])\n";
	$home = page( '/en/' );
	$xpath = xp( $home['body'] );
	ok( '/en/ serves the page', 200 === $home['status'] && 'English' === explode( ' ', text_of( $xpath, '//head/title' ) )[0], text_of( $xpath, '//head/title' ) );
	$items = $xpath->query( "//ul[contains(@class,'nh-vien-posts')]//li//a" );
	ok( 'the shortcode lists the English posts that may be shown', 17 === $items->length, $items->length . ' items' );
	$listed = array();
	foreach ( $items as $a ) {
		$listed[] = path_of( $a->getAttribute( 'href' ) );
	}
	ok( 'every item links to an English address and none is hidden', ! array_diff( $listed, $en_paths ) && ! array_intersect( $listed, array( '/en/hidden-english-post/', '/en/english-draft/', '/en/collision-post/' ) ), implode( ',', array_diff( $listed, $en_paths ) ) );
	ok( 'a title with markup is escaped in the list', false === strpos( $home['body'], '<img src=x onerror' ) && false !== strpos( $home['body'], '&lt;img src=x onerror' ) );
	ok( '/en/page/2/ is a 404 (a real page has no page 2)', 404 === page( '/en/page/2/' )['status'] );
	ok( 'a search below /en/ still finds English posts', in_array( '/en/motorbike-rental-in-hanoi/', post_links_in_main( xp( page( '/en/?s=Motorbike' )['body'] ), $en_paths ), true ) );
	$alt = array();
	foreach ( head_links( $xpath, 'alternate' ) as $l ) {
		if ( '' !== $l['hreflang'] ) {
			$alt[ $l['hreflang'] ] = $l['href'];
		}
	}
	ok( 'hreflang pairs the two homes', array(
		'vi'        => '/',
		'en'        => '/en/',
		'x-default' => '/',
	) === $alt, json_encode( $alt ) );
	$canon = head_links( $xpath, 'canonical' );
	ok( 'one canonical, the English home', 1 === count( $canon ) && '/en/' === $canon[0]['href'], json_encode( $canon ) );
	ok( 'pages below /en/ keep working', 200 === page( '/en/about/' )['status'] && 200 === page( '/en/collision-post/' )['status'] );
	ok( 'the English posts, categories and tags still work', 200 === page( '/en/motorbike-rental-in-hanoi/' )['status'] && 200 === page( '/en/category/guides/' )['status'] && 200 === page( '/en/tag/hanoi/' )['status'] );
	if ( 'yoast' === $seo ) {
		$count = 0;
		foreach ( array( '/post-sitemap.xml', '/page-sitemap.xml' ) as $map ) {
			preg_match_all( '#<loc>([^<]+)</loc>#', page( $map )['body'], $m );
			$count += count( array_keys( array_map( 'path_of', $m[1] ), '/en/' ) );
		}
		ok( 'the English home is in the sitemaps exactly once (the page, not a second entry for the list)', 1 === $count, $count . ' entries' );
	}
	echo "\n$checks checks, $failures failed\n";
	exit( $failures ? 1 : 0 );
}

if ( 'deactivated' === $suite ) {
	echo "The plugin was deactivated while its rewrite rules were stored: nothing of the English side may answer\n";
	foreach ( array( '/en/', '/en/category/travel-tips/', '/en/tag/hanoi/', '/en/page/2/', '/en/nope/' ) as $url ) {
		$r = page( $url );
		ok( "$url is a 404, not a copy of the Vietnamese home page", 404 === $r['status'], 'status ' . $r['status'] );
	}
	// WordPress guesses the post of an old address from its slug: the English post goes back to the address it had before.
	$r = page( '/en/motorbike-rental-in-hanoi/' );
	ok( '/en/motorbike-rental-in-hanoi/ goes back to the address the English post had before the plugin', 301 === $r['status'] && '/motorbike-rental-in-hanoi/' === path_of( $r['location'] ), 'status ' . $r['status'] . ' ' . $r['location'] );
	$r = page( '/motorbike-rental-in-hanoi/' );
	ok( 'the English post answers at its own address again', 200 === $r['status'], 'status ' . $r['status'] );
	foreach ( array( '/', '/thue-xe-may-ha-noi/', '/category/kinh-nghiem/' ) as $url ) {
		$r  = page( $url );
		$xp = xp( $r['body'] );
		$alternates = array_filter( head_links( $xp, 'alternate' ), function ( $l ) { return '' !== $l['hreflang']; } );
		ok( "$url answers 200 in Vietnamese with no switcher and no hreflang", 200 === $r['status'] && 'vi' === $xp->query( '//html' )->item( 0 )->getAttribute( 'lang' ) && 0 === $xp->query( "//nav[contains(@class,'nh-vien-switcher')]" )->length && ! $alternates, 'status ' . $r['status'] );
	}
	echo "\n$checks checks, $failures failed\n";
	exit( $failures ? 1 : 0 );
}

if ( 'legacy' === $suite ) {
	echo "A classic theme that never calls wp_body_open(): the switcher goes into the content of a post, once\n";
	$posts = array(
		'/thue-xe-may-ha-noi/'           => '/en/motorbike-rental-in-hanoi/',
		'/en/motorbike-rental-in-hanoi/' => '/thue-xe-may-ha-noi/',
	);
	foreach ( $posts as $url => $other ) {
		$r     = page( $url );
		$xpath = xp( $r['body'] );
		ok( "$url answers 200", 200 === $r['status'], 'status ' . $r['status'] );
		ok( "$url: the theme really never fires wp_body_open()", 0 === $xpath->query( "//body/*[contains(@class,'nh-vien-bar')]" )->length );
		$navs = $xpath->query( "//nav[contains(@class,'nh-vien-switcher')]" );
		ok( "$url has the switcher exactly once", 1 === $navs->length, $navs->length . ' found' );
		ok( "$url: the switcher is at the top of the article", 1 === $xpath->query( "//main/article/nav[contains(@class,'nh-vien-switcher')]" )->length );
		$hrefs = array_map( 'path_of', attrs( $xpath, "//nav[contains(@class,'nh-vien-switcher')]//a[@aria-current!='page' or not(@aria-current)]", 'href' ) );
		ok( "$url: the switcher leads to the other language", array( $other ) === $hrefs, implode( ',', $hrefs ) );
	}
	// Without the call there is no place for an automatic switcher on any other page: it is left to the shortcode.
	foreach ( array( '/', '/en/', '/category/kinh-nghiem/', '/en/category/travel-tips/' ) as $url ) {
		$r = page( $url );
		ok( "$url answers 200 and shows no automatic switcher", 200 === $r['status'] && 0 === xp( $r['body'] )->query( "//nav[contains(@class,'nh-vien-switcher')]" )->length, 'status ' . $r['status'] );
	}
	echo "\n$checks checks, $failures failed\n";
	exit( $failures ? 1 : 0 );
}

// ==================================================================== main suite
echo "Site tests: $base (SEO plugin: $seo, theme: $theme)\n";

echo "\n[1] Statuses and redirects\n";
$expect = array(
	'/'                                                => 200,
	'/thue-xe-may-ha-noi/'                             => 200,
	'/bai-chua-dich/'                                  => 200,
	'/en/motorbike-rental-in-hanoi/'                   => 200,
	'/en/travel-tips-for-riders/'                      => 200,
	'/en/bikes-and-scooters/'                          => 200,
	'/en/'                                             => 200,
	'/en/page/2/'                                      => 200,
	'/en/page/3/'                                      => 404,
	'/en/category/guides/'                             => 200,
	'/en/category/guides/motorbike-rental/'            => 200,
	'/en/category/guides/motorbike-rental/page/2/'     => 200,
	'/en/category/travel-tips/'                        => 200,
	'/en/category/kinh-nghiem/'                        => 404,
	'/en/category/thue-xe/'                            => 404,
	'/en/category/nope/'                               => 404,
	'/en/tag/hanoi/'                                   => 200,
	'/en/tag/safety/'                                  => 200,
	'/en/tag/nope/'                                    => 404,
	'/en/nope/'                                        => 404,
	'/en/category/'                                    => 404,
	'/en/page/'                                        => 404,
	'/en/feed/'                                        => 404,
	'/en/author/admin/'                                => 404,
	'/en/2026/10/'                                     => 404,
	'/en/english-draft/'                               => 404,
	'/en/translation-in-progress/'                     => 404,
	'/en/hidden-english-post/'                         => 404,
	'/hidden-english-post/'                            => 404,
	'/english-draft/'                                  => 404,
	'/en/collision-post/'                              => 200,
	'/collision-post/'                                 => 404,
	'/en/about/'                                       => 200,
	'/category/kinh-nghiem/'                           => 200,
	'/category/thue-xe/'                               => 200,
	'/category/travel-tips/'                           => 404,
	'/category/guides/'                                => 404,
	'/category/guides/motorbike-rental/'               => 404,
	'/tag/hanoi/'                                      => 404,
	'/tag/safety/'                                     => 404,
	'/page/2/'                                         => 200,
	'/page/3/'                                         => 404,
);
foreach ( $expect as $url => $status ) {
	$r = page( $url );
	ok( "$url -> $status", $status === $r['status'], 'got ' . $r['status'] . ( $r['location'] ? ' ' . $r['location'] : '' ) );
}
$moved = array(
	'/motorbike-rental-in-hanoi/'              => '/en/motorbike-rental-in-hanoi/',
	'/travel-tips-for-riders/'                 => '/en/travel-tips-for-riders/',
	'/en/motorbike-rental-in-hanoi'            => '/en/motorbike-rental-in-hanoi/',
	'/en'                                      => '/en/',
	'/motorbike-rental-in-hanoi/?utm_source=a&utm_campaign=caf%C3%A9' => '/en/motorbike-rental-in-hanoi/?utm_source=a&utm_campaign=caf%C3%A9',
);
if ( ! empty( $fx['en_rent'] ) ) {
	$moved[ '/?p=' . $fx['en_rent'] ] = '/en/motorbike-rental-in-hanoi/';
}
foreach ( $moved as $from => $to ) {
	$r = page( $from );
	ok( "$from -> 301 $to", 301 === $r['status'] && path_of( $r['location'] ) === $to, 'got ' . $r['status'] . ' ' . $r['location'] );
}
if ( ! empty( $fx['en_hidden'] ) ) {
	$r = page( '/?p=' . $fx['en_hidden'] );
	ok( 'a hidden English post answers 404 by ID, not a redirect', 404 === $r['status'], 'got ' . $r['status'] . ' ' . $r['location'] );
}
$r = page( '/motorbike-rental-in-hanoi/?redirect=//evil.example' );
ok( 'the redirect never leaves the site', 301 === $r['status'] && 0 === strpos( $r['location'], $base ), $r['location'] );
$r = page( '/en/collision-post/' );
ok( 'a page below /en/ keeps its address (the page wins over the English post)', false !== strpos( $r['body'], 'A real page below /en/' ) );
$r = page( '/en/about/' );
ok( 'a page below /en/ is served', false !== strpos( $r['body'], 'About (English page).' ) );

echo "\n[2] Language of the page\n";
foreach ( array( '/', '/thue-xe-may-ha-noi/', '/bai-chua-dich/', '/category/kinh-nghiem/', '/page/2/', '/nope-404-test/' ) as $url ) {
	$xpath = xp( page( $url )['body'] );
	$lang  = $xpath->query( '//html' )->item( 0 )->getAttribute( 'lang' );
	ok( "$url is lang=vi", 'vi' === $lang, "lang=$lang" );
}
foreach ( array( '/en/', '/en/motorbike-rental-in-hanoi/', '/en/category/travel-tips/', '/en/tag/hanoi/', '/en/page/2/', '/en/nope/', '/en/about/' ) as $url ) {
	$xpath = xp( page( $url )['body'] );
	$lang  = $xpath->query( '//html' )->item( 0 )->getAttribute( 'lang' );
	ok( "$url is lang=en-US", 'en-US' === $lang, "lang=$lang" );
}
$title = text_of( xp( page( '/en/motorbike-rental-in-hanoi/' )['body'] ), '//head/title' );
ok( 'the English post has its own title', false !== strpos( $title, 'Motorbike rental in Hanoi' ), $title );
$title = text_of( xp( page( '/thue-xe-may-ha-noi/' )['body'] ), '//head/title' );
ok( 'the Vietnamese post keeps its own title', false !== strpos( $title, 'Thuê xe máy Hà Nội' ), $title );

echo "\n[3] Lists show one language only\n";
$all_vi = $vi_paths;
$all_en = $en_paths;
foreach ( array( '/', '/page/2/', '/category/kinh-nghiem/', '/category/thue-xe/' ) as $url ) {
	$xpath = xp( page( $url )['body'] );
	ok( "$url lists no English post", array() === array_values( array_intersect( post_links_in_main( $xpath, array_merge( $all_en, $en_native_paths ) ), array_merge( $all_en, $en_native_paths ) ) ), implode( ',', post_links_in_main( $xpath, array_merge( $all_en, $en_native_paths ) ) ) );
}
$home_vi = post_links_in_main( xp( page( '/' )['body'] ), $all_vi );
ok( 'the Vietnamese home lists Vietnamese posts', count( $home_vi ) >= 5, count( $home_vi ) . ' found' );
foreach ( array( '/en/', '/en/page/2/', '/en/category/guides/', '/en/category/travel-tips/', '/en/tag/hanoi/' ) as $url ) {
	$xpath  = xp( page( $url )['body'] );
	$links  = post_links_in_main( $xpath, array_merge( $all_vi, $all_en, $en_native_paths ) );
	$wrong  = array_diff( $links, $all_en );
	ok( "$url lists English posts only", $links && ! $wrong, implode( ',', $wrong ) );
}
$page1 = post_links_in_main( xp( page( '/en/' )['body'] ), $all_en );
$page2 = post_links_in_main( xp( page( '/en/page/2/' )['body'] ), $all_en );
ok( 'the English list has 12 posts on page 1 and the rest on page 2', 12 === count( $page1 ) && count( $page2 ) >= 1 && ! array_intersect( $page1, $page2 ), count( $page1 ) . ' + ' . count( $page2 ) );
ok( 'the hidden, draft, reserved and colliding English posts are in no list', ! array_intersect( array_merge( $page1, $page2 ), array( '/en/hidden-english-post/', '/en/english-draft/', '/en/category/', '/en/collision-post/', '/en/translation-in-progress/' ) ) );
$search_vi = page( '/?s=Motorbike' );
$search_en = page( '/en/?s=Motorbike' );
ok( 'the Vietnamese search does not find English posts', 200 === $search_vi['status'] && ! post_links_in_main( xp( $search_vi['body'] ), array_merge( $all_en, $en_native_paths ) ) );
ok( 'the English search finds English posts only', 200 === $search_en['status'] && in_array( '/en/motorbike-rental-in-hanoi/', post_links_in_main( xp( $search_en['body'] ), $all_en ), true ) );
$search_vi2 = page( '/?s=xe' );
ok( 'the Vietnamese search still finds Vietnamese posts', in_array( '/thue-xe-may-ha-noi/', post_links_in_main( xp( $search_vi2['body'] ), $all_vi ), true ) );
$feed = page( '/feed/' );
ok( 'the Vietnamese feed lists no English post', false === strpos( $feed['body'], 'Motorbike rental in Hanoi' ) && false === strpos( $feed['body'], 'Bulk article' ) && false !== strpos( $feed['body'], 'Thuê xe máy Hà Nội' ) );
$single = xp( page( '/en/motorbike-rental-in-hanoi/' )['body'] );
ok( 'links inside an English post point to English posts only', ! array_diff( post_links_in_main( $single, array_merge( $all_vi, $all_en, $en_native_paths ) ), $all_en ) );
$single_vi = xp( page( '/thue-xe-may-ha-noi/' )['body'] );
ok( 'links inside a Vietnamese post point to Vietnamese posts only', ! array_diff( post_links_in_main( $single_vi, array_merge( $all_vi, $all_en, $en_native_paths ) ), $all_vi ) );

echo "\n[4] Internal links in the language of the page\n";
if ( 'editorial' === $theme ) {
	$en_home_xp = xp( page( '/en/' )['body'] );
	$brand      = array_map( 'path_of', attrs( $en_home_xp, "//a[contains(@class,'brand')]", 'href' ) );
	ok( 'the logo of an English page links to the English home', $brand && array_unique( $brand ) === array( '/en/' ), implode( ',', $brand ) );
	$actions = array_map( 'path_of', attrs( $en_home_xp, "//form[@role='search']", 'action' ) );
	ok( 'the search form of an English page searches the English posts', $actions && array_unique( $actions ) === array( '/en/' ), implode( ',', $actions ) );
	$vi_brand = array_map( 'path_of', attrs( xp( page( '/' )['body'] ), "//a[contains(@class,'brand')]", 'href' ) );
	ok( 'the logo of a Vietnamese page still links to the Vietnamese home', $vi_brand && array_unique( $vi_brand ) === array( '/' ), implode( ',', $vi_brand ) );
	$menu_en = text_of( $en_home_xp, "//ul[@id='primary-menu']" );
	ok( 'English pages use the English menu (primary-en)', false !== strpos( $menu_en, 'English Menu Item' ) && false === strpos( $menu_en, 'Trang chủ VI' ), $menu_en );
	$menu_vi = text_of( xp( page( '/' )['body'] ), "//ul[@id='primary-menu']" );
	ok( 'Vietnamese pages keep the Vietnamese menu', false !== strpos( $menu_vi, 'Trang chủ VI' ) && false === strpos( $menu_vi, 'English Menu Item' ), $menu_vi );
	$cats = array_map( 'path_of', attrs( xp( page( '/en/motorbike-rental-in-hanoi/' )['body'] ), "//main//a[contains(@href,'/category/')]", 'href' ) );
	ok( 'the categories of an English post link to English archives', $cats && ! array_filter( $cats, function ( $p ) { return 0 !== strpos( $p, '/en/category/' ); } ), implode( ',', $cats ) );
}

echo "\n[5] The language switcher\n";
$pairs = array(
	'/thue-xe-may-ha-noi/'           => array( '/thue-xe-may-ha-noi/', '/en/motorbike-rental-in-hanoi/', 'vi' ),
	'/en/motorbike-rental-in-hanoi/' => array( '/thue-xe-may-ha-noi/', '/en/motorbike-rental-in-hanoi/', 'en' ),
	'/bai-chua-dich/'                => array( '/bai-chua-dich/', '/en/', 'vi' ),
	'/'                              => array( '/', '/en/', 'vi' ),
	'/en/'                           => array( '/', '/en/', 'en' ),
	'/category/kinh-nghiem/'         => array( '/category/kinh-nghiem/', '/en/', 'vi' ),
	'/en/category/travel-tips/'      => array( '/', '/en/category/travel-tips/', 'en' ),
);
foreach ( $pairs as $url => $want ) {
	$xpath = xp( page( $url )['body'] );
	$links = $xpath->query( "//nav[contains(@class,'nh-vien-switcher')]//a" );
	$got   = array();
	$cur   = '';
	foreach ( $links as $a ) {
		$got[ $a->getAttribute( 'lang' ) ] = path_of( $a->getAttribute( 'href' ) );
		if ( 'page' === $a->getAttribute( 'aria-current' ) ) {
			$cur = $a->getAttribute( 'lang' );
		}
	}
	ok( "switcher on $url: vi=$want[0] en=$want[1] current=$want[2]", ( isset( $got['vi'] ) ? $got['vi'] : '' ) === $want[0] && ( isset( $got['en'] ) ? $got['en'] : '' ) === $want[1] && $cur === $want[2], json_encode( $got ) . " current=$cur" );
	ok( "the switcher on $url is printed once", 1 === $xpath->query( "//nav[contains(@class,'nh-vien-switcher')]" )->length );
}
$xpath = xp( page( '/thue-xe-may-ha-noi/' )['body'] );
ok( 'the switcher links carry hreflang, lang and an accessible name', 2 === $xpath->query( "//nav[contains(@class,'nh-vien-switcher')][@aria-label]//a[@hreflang and @lang and @aria-label]" )->length );
ok( 'the switcher stylesheet is loaded where the switcher is', $xpath->query( "//link[contains(@href,'nguyen-ha-vi-en') and contains(@href,'switcher.css')]" )->length > 0 );

echo "\n[6] SEO signals\n";
$pair_pages = array(
	'/thue-xe-may-ha-noi/',
	'/en/motorbike-rental-in-hanoi/',
	'/',
	'/en/',
);
foreach ( $pair_pages as $url ) {
	$xpath = xp( page( $url )['body'] );
	$alt   = array();
	foreach ( head_links( $xpath, 'alternate' ) as $l ) {
		if ( '' !== $l['hreflang'] ) {
			$alt[ $l['hreflang'] ] = $l['href'];
		}
	}
	$vi = '/' === $url || '/en/' === $url ? '/' : '/thue-xe-may-ha-noi/';
	$en = '/' === $url || '/en/' === $url ? '/en/' : '/en/motorbike-rental-in-hanoi/';
	ok( "hreflang vi, en and x-default on $url", array(
		'vi'        => $vi,
		'en'        => $en,
		'x-default' => $vi,
	) === $alt, json_encode( $alt ) );
}
foreach ( array( '/bai-chua-dich/', '/category/kinh-nghiem/', '/en/category/travel-tips/', '/en/?s=Motorbike', '/en/nope/', '/nope-404-test/', '/en/page/2/' ) as $url ) {
	$alt = array_filter( head_links( xp( page( $url )['body'] ), 'alternate' ), function ( $l ) { return '' !== $l['hreflang']; } );
	ok( "no hreflang on $url (no counterpart)", ! $alt, json_encode( $alt ) );
}
$canonical = array(
	'/thue-xe-may-ha-noi/'           => '/thue-xe-may-ha-noi/',
	'/en/motorbike-rental-in-hanoi/' => '/en/motorbike-rental-in-hanoi/',
	'/en/'                           => '/en/',
	'/en/page/2/'                    => '/en/page/2/',
	'/en/category/travel-tips/'      => '/en/category/travel-tips/',
	'/en/tag/hanoi/'                 => '/en/tag/hanoi/',
);
foreach ( $canonical as $url => $want ) {
	$links = head_links( xp( page( $url )['body'] ), 'canonical' );
	ok( "one canonical on $url: $want", 1 === count( $links ) && $links[0]['href'] === $want, json_encode( $links ) );
}
$xpath = xp( page( '/en/motorbike-rental-in-hanoi/' )['body'] );
ok( 'the English post canonical is not the ordinary address', ! in_array( '/motorbike-rental-in-hanoi/', array_column( head_links( $xpath, 'canonical' ), 'href' ), true ) );
ok( 'one canonical on the Vietnamese category', 1 === count( head_links( xp( page( '/category/kinh-nghiem/' )['body'] ), 'canonical' ) ) || 'none' === $seo );
$search_robots = xp( page( '/en/?s=Motorbike' )['body'] )->query( "//meta[@name='robots']/@content" );
ok( 'the English search results are noindex', $search_robots->length && false !== strpos( $search_robots->item( 0 )->nodeValue, 'noindex' ) );
$all_head = xp( page( '/en/motorbike-rental-in-hanoi/' )['body'] );
ok( 'a single title on an English post', 1 === $all_head->query( '//head/title' )->length );
$og = $all_head->query( "//meta[@property='og:url']/@content" );
ok( 'og:url of an English post is its English address (when printed)', ! $og->length || path_of( $og->item( 0 )->nodeValue ) === '/en/motorbike-rental-in-hanoi/', $og->length ? $og->item( 0 )->nodeValue : '' );
$loc = $all_head->query( "//meta[@property='og:locale']/@content" );
ok( 'og:locale of an English post is en_US (when printed)', ! $loc->length || 'en_US' === $loc->item( 0 )->nodeValue, $loc->length ? $loc->item( 0 )->nodeValue : '' );

if ( 'yoast' === $seo ) {
	echo "\n[7] Yoast SEO: structured data, breadcrumbs and sitemaps\n";
	$en_nodes = json_ld_nodes( $all_head );
	$web_page = node_of_type( $en_nodes, 'WebPage' );
	$article  = node_of_type( $en_nodes, 'Article' );
	$crumbs   = node_of_type( $en_nodes, 'BreadcrumbList' );
	ok( 'the WebPage of an English post is at its English address', $web_page && path_of( $web_page['url'] ) === '/en/motorbike-rental-in-hanoi/', $web_page ? $web_page['url'] : 'no WebPage' );
	ok( 'the WebPage of an English post is in English', $web_page && isset( $web_page['inLanguage'] ) && 'en-US' === $web_page['inLanguage'], $web_page && isset( $web_page['inLanguage'] ) ? $web_page['inLanguage'] : 'none' );
	$crumb_urls = array();
	if ( $crumbs && isset( $crumbs['itemListElement'] ) ) {
		foreach ( $crumbs['itemListElement'] as $item ) {
			$crumb_urls[] = isset( $item['item'] ) ? ( is_array( $item['item'] ) ? $item['item']['@id'] : $item['item'] ) : '';
		}
	}
	$crumb_urls = array_map( 'path_of', array_filter( $crumb_urls ) );
	ok( 'the BreadcrumbList of an English post starts at the English home and names English archives', $crumb_urls && '/en/' === $crumb_urls[0] && ! array_filter( $crumb_urls, function ( $p ) { return 0 !== strpos( $p, '/en/' ); } ), implode( ',', $crumb_urls ) );
	$vi_nodes = json_ld_nodes( xp( page( '/thue-xe-may-ha-noi/' )['body'] ) );
	$vi_page  = node_of_type( $vi_nodes, 'WebPage' );
	ok( 'the WebPage of the Vietnamese post is at its own address and in Vietnamese', $vi_page && path_of( $vi_page['url'] ) === '/thue-xe-may-ha-noi/' && ( ! isset( $vi_page['inLanguage'] ) || 'vi' === $vi_page['inLanguage'] ), json_encode( $vi_page ) );
	$org_en = node_of_type( $en_nodes, 'Organization' );
	$org_vi = node_of_type( $vi_nodes, 'Organization' );
	$web_en = node_of_type( $en_nodes, 'WebSite' );
	$web_vi = node_of_type( $vi_nodes, 'WebSite' );
	// Yoast SEO writes the Organization only once the site is set up as one; the WebSite is always there.
	ok( 'the site (and the organisation, when there is one) have the same identifiers in both languages', $web_en && $web_vi && $web_en['@id'] === $web_vi['@id'] && ( ! $org_en && ! $org_vi || $org_en && $org_vi && $org_en['@id'] === $org_vi['@id'] ), ( $web_en ? $web_en['@id'] : '-' ) . ' / ' . ( $web_vi ? $web_vi['@id'] : '-' ) . ' ; ' . ( $org_en ? $org_en['@id'] : '-' ) . ' / ' . ( $org_vi ? $org_vi['@id'] : '-' ) );
	$yoast_crumb = array_map( 'path_of', attrs( $all_head, "//main//nav[contains(@class,'breadcrumb')]//a | //nav[contains(@aria-label,'readcrumb')]//a", 'href' ) );
	ok( 'the visible breadcrumb of an English post follows the structured data', ! $yoast_crumb || ( '/en/' === $yoast_crumb[0] && ! array_filter( $yoast_crumb, function ( $p ) { return 0 !== strpos( $p, '/en/' ); } ) ), implode( ',', $yoast_crumb ) );

	// The English pages are real, indexable pages for Yoast SEO too: nothing in them may be noindex.
	foreach ( array( '/en/', '/en/page/2/', '/en/category/travel-tips/', '/en/motorbike-rental-in-hanoi/' ) as $url ) {
		$robots = attrs( xp( page( $url )['body'] ), "//head/meta[@name='robots']", 'content' );
		ok( "$url is indexable (one robots meta from Yoast SEO, no noindex)", 1 === count( $robots ) && false === strpos( $robots[0], 'noindex' ), implode( ' | ', $robots ) );
	}
	$home_title = text_of( xp( page( '/en/' )['body'] ), '//head/title' );
	ok( 'the English home has the title of the list, with the name of the site', false !== strpos( $home_title, 'Latest articles' ) && false !== strpos( $home_title, 'Nguyen Ha Test Blog' ), $home_title );
	$index = page( '/sitemap_index.xml' );
	ok( '/sitemap_index.xml is served by Yoast SEO', 200 === $index['status'] && false !== strpos( $index['body'], '<sitemapindex' ), 'status ' . $index['status'] );
	preg_match_all( '#<loc>([^<]+)</loc>#', $index['body'], $m );
	$maps = array_map( 'path_of', $m[1] );
	ok( 'the index lists each sitemap once and has no sitemap of its own for English', count( $maps ) === count( array_unique( $maps ) ) && ! array_filter( $maps, function ( $p ) { return false !== strpos( $p, '/en/' ) || false !== stripos( $p, 'english' ) || false !== strpos( $p, 'nh-vien' ); } ), implode( ',', $maps ) );
	$maps_xml = function ( $path ) {
		$r   = page( $path );
		$doc = new DOMDocument();
		$ok  = '' !== $r['body'] && @$doc->loadXML( $r['body'] );
		preg_match_all( '#<loc>([^<]+)</loc>#', $r['body'], $locs );
		return array( 200 === $r['status'] && $ok, array_map( 'path_of', $locs[1] ) );
	};
	list( $valid, $locs ) = $maps_xml( '/post-sitemap.xml' );
	ok( 'the post sitemap is valid XML', $valid );
	ok( 'the post sitemap lists the English posts that may be shown at their English address', in_array( '/en/motorbike-rental-in-hanoi/', $locs, true ) && in_array( '/en/travel-tips-for-riders/', $locs, true ) );
	ok( 'the post sitemap lists the Vietnamese posts', in_array( '/thue-xe-may-ha-noi/', $locs, true ) && in_array( '/kinh-nghiem-di-phuot/', $locs, true ) );
	ok( 'the post sitemap lists no ordinary address of an English post', ! array_intersect( $locs, $en_native_paths ), implode( ',', array_intersect( $locs, $en_native_paths ) ) );
	ok( 'the post sitemap lists no hidden, draft or reserved English post', ! array_intersect( $locs, array( '/en/hidden-english-post/', '/en/english-draft/', '/en/collision-post/', '/en/translation-in-progress/', '/en/category/' ) ) );
	ok( 'the post sitemap lists the English home', in_array( '/en/', $locs, true ) );
	list( $valid, $locs ) = $maps_xml( '/category-sitemap.xml' );
	ok( 'the category sitemap is valid XML', $valid );
	ok( 'the category sitemap lists the English archives', in_array( '/en/category/guides/motorbike-rental/', $locs, true ) && in_array( '/en/category/travel-tips/', $locs, true ) && in_array( '/en/category/guides/', $locs, true ), implode( ',', $locs ) );
	ok( 'the category sitemap lists the Vietnamese archives that have Vietnamese posts', in_array( '/category/kinh-nghiem/', $locs, true ) && in_array( '/category/thue-xe/', $locs, true ), implode( ',', $locs ) );
	ok( 'the category sitemap no longer lists archives that only English posts fill', ! array_intersect( $locs, array( '/category/travel-tips/', '/category/guides/', '/category/guides/motorbike-rental/' ) ), implode( ',', array_intersect( $locs, array( '/category/travel-tips/', '/category/guides/' ) ) ) );
	if ( in_array( '/post_tag-sitemap.xml', $maps, true ) ) {
		list( $valid, $locs ) = $maps_xml( '/post_tag-sitemap.xml' );
		ok( 'the tag sitemap lists the English tag archives and not the Vietnamese address of them', $valid && in_array( '/en/tag/hanoi/', $locs, true ) && ! in_array( '/tag/hanoi/', $locs, true ), implode( ',', $locs ) );
	} else {
		// Yoast SEO keeps tags out of the index and out of the sitemap by default, for both languages alike.
		ok( 'Yoast SEO has no tag sitemap here, so there is no tag entry to translate', true );
		ok( 'the English tag archive is left out of the search results like every tag, not given a sitemap of its own', ! array_filter( $maps, function ( $p ) { return false !== strpos( $p, 'tag' ); } ), implode( ',', $maps ) );
	}
} else {
	echo "\n[7] The sitemap of WordPress\n";
	$r = page( '/wp-sitemap-posts-post-1.xml' );
	preg_match_all( '#<loc>([^<]+)</loc>#', $r['body'], $m );
	$locs = array_map( 'path_of', $m[1] );
	ok( 'the WordPress sitemap lists English posts at their English address and Vietnamese posts as before', 200 === $r['status'] && in_array( '/en/motorbike-rental-in-hanoi/', $locs, true ) && in_array( '/thue-xe-may-ha-noi/', $locs, true ), 'status ' . $r['status'] . ' ' . count( $locs ) . ' urls' );
	ok( 'the WordPress sitemap lists no ordinary address of an English post and no hidden post', ! array_intersect( $locs, array_merge( $en_native_paths, array( '/en/hidden-english-post/', '/en/english-draft/' ) ) ) );
	$r = page( '/wp-sitemap-taxonomies-category-1.xml' );
	preg_match_all( '#<loc>([^<]+)</loc>#', $r['body'], $m );
	$locs = array_map( 'path_of', $m[1] );
	ok( 'the WordPress category sitemap lists the Vietnamese archives that have Vietnamese posts', 200 === $r['status'] && in_array( '/category/kinh-nghiem/', $locs, true ) && in_array( '/category/thue-xe/', $locs, true ), 'status ' . $r['status'] . ' ' . implode( ',', $locs ) );
	ok( 'the WordPress category sitemap no longer lists archives that only English posts fill (they answer 404)', ! array_intersect( $locs, array( '/category/travel-tips/', '/category/guides/', '/category/guides/motorbike-rental/' ) ), implode( ',', array_intersect( $locs, array( '/category/travel-tips/', '/category/guides/' ) ) ) );
	$r = page( '/wp-sitemap-taxonomies-post_tag-1.xml' );
	preg_match_all( '#<loc>([^<]+)</loc>#', $r['body'], $m );
	ok( 'the WordPress tag sitemap does not list the Vietnamese address of a tag that only English posts use', ! in_array( '/tag/hanoi/', array_map( 'path_of', $m[1] ), true ) && ! in_array( '/tag/safety/', array_map( 'path_of', $m[1] ), true ), 'status ' . $r['status'] );
	$index = page( '/wp-sitemap.xml' );
	ok( 'the WordPress sitemap index does not point to an empty tag sitemap', false === strpos( $index['body'], 'post_tag' ) || 200 === $r['status'], 'tag sitemap status ' . $r['status'] );
}

echo "\n[8] Security\n";
$xss = '<script>alert(1)</script>';
$r   = http( '/en/?s=' . rawurlencode( $xss ) );
ok( 'a script in the English search term is escaped', 200 === $r['status'] && false === strpos( $r['body'], $xss ) );
$r = http( '/en/' . rawurlencode( $xss ) . '/' );
ok( 'a script in an English address is a 404 and is not echoed', 404 === $r['status'] && false === strpos( $r['body'], $xss ) );
$r = http( '/?s=' . rawurlencode( $xss ) );
ok( 'a script in the Vietnamese search term is escaped', false === strpos( $r['body'], $xss ) );
$special = page( '/en/bikes-and-scooters/' );
$xpath   = xp( $special['body'] );
$special_title = text_of( $xpath, '//head/title' );
ok( 'a title with markup reaches the title tag as text only', 200 === $special['status'] && false !== strpos( $special_title, 'scooters' ) && false === strpos( $special_title, '<img' ) && false === strpos( preg_replace( '#<script.*?</script>#s', '', explode( '</head>', $special['body'] )[0] ), '<img src=x onerror' ), $special_title );
$r = http( '/?nh_vien_lang=en' );
$lang = xp( $r['body'] )->query( '//html' )->item( 0 )->getAttribute( 'lang' );
ok( 'a raw nh_vien_lang variable does not make a page English', 'vi' === $lang, "lang=$lang" );
$r = http( '/?nh_vien_en_slug=motorbike-rental-in-hanoi' );
ok( 'a raw nh_vien_en_slug variable does not open an English post', 200 === $r['status'] && false === strpos( text_of( xp( $r['body'] ), '//head/title' ), 'Motorbike rental' ) );
$r = http( '/?nh_vien_scope=off' );
ok( 'a raw nh_vien_scope variable does not put English posts back into Vietnamese lists', ! post_links_in_main( xp( $r['body'] ), array_merge( $all_en, $en_native_paths ) ) );

echo "\n[9] Caches keep the languages apart\n";
$first  = http( '/' )['body'];
$en_one = http( '/en/' )['body'];
$second = http( '/' )['body'];
$en_two = http( '/en/' )['body'];
ok( 'the Vietnamese home is the same before and after an English page was built', $first === $second );
ok( 'the English home is the same twice', $en_one === $en_two );

echo "\n[10] Administration\n";
$jar   = array();
$login = function ( $user, $pass ) use ( &$jar ) {
	$jar = array( 'wordpress_test_cookie=WP%20Cookie%20check' );
	$r   = http(
		'/wp-login.php',
		array(
			'method'  => 'POST',
			'headers' => array( 'Content-Type: application/x-www-form-urlencoded', 'Cookie: ' . implode( '; ', $jar ) ),
			'body'    => http_build_query(
				array(
					'log'        => $user,
					'pwd'        => $pass,
					'wp-submit'  => 'Log In',
					'redirect_to' => '/wp-admin/',
					'testcookie' => '1',
				)
			),
		)
	);
	$jar = array_merge( $jar, $r['cookies'] );
	return 302 === $r['status'];
};
$admin = function ( $path, $fields = null ) use ( &$jar ) {
	$o = array( 'headers' => array( 'Cookie: ' . implode( '; ', $jar ) ) );
	if ( null !== $fields ) {
		$o['method']    = 'POST';
		$o['headers'][] = 'Content-Type: application/x-www-form-urlencoded';
		$o['body']      = http_build_query( $fields );
	}
	return http( $path, $o );
};
$form_for = function ( $html, $action ) {
	foreach ( preg_split( '#</form>#', $html ) as $form ) {
		if ( false !== strpos( $form, 'name="action" value="' . $action . '"' ) ) {
			preg_match_all( '#<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"#', $form, $f, PREG_SET_ORDER );
			$fields = array();
			foreach ( $f as $field ) {
				$fields[ $field[1] ] = html_entity_decode( $field[2] );
			}
			return $fields;
		}
	}
	return null;
};
ok( 'the administrator can log in', $login( 'admin', 'admin' ) );
$tools = $admin( '/wp-admin/tools.php?page=nh-vien-links' );
ok( 'the Tools screen of the plugin opens for the administrator', 200 === $tools['status'] && false !== strpos( $tools['body'], 'English pages are live' ) );
ok( 'the Tools screen escapes the title of a post', false === strpos( $tools['body'], '<img src=x onerror' ) && false !== strpos( $tools['body'], '&lt;img src=x onerror' ) );
ok( 'the Tools screen says the English pages are live', (bool) preg_match( '#English pages are live</th><td>Yes#', $tools['body'] ) );
$flush = $form_for( $tools['body'], 'nh_vien_flush' );
ok( 'the Tools screen has the rewrite button with a nonce', $flush && ! empty( $flush['_wpnonce'] ) );
if ( $flush ) {
	$r = $admin( '/wp-admin/admin-post.php', $flush );
	ok( 'storing the rewrite rules with the nonce works', 302 === $r['status'] && false !== strpos( $r['location'], 'nh_vien_status=flushed' ), $r['status'] . ' ' . $r['location'] );
	$bad = $flush;
	$bad['_wpnonce'] = '0000000000';
	$r   = $admin( '/wp-admin/admin-post.php', $bad );
	ok( 'a wrong nonce is refused (CSRF)', 403 === $r['status'], 'got ' . $r['status'] );
	$r = $admin( '/wp-admin/admin-post.php', array( 'action' => 'nh_vien_flush' ) );
	ok( 'a missing nonce is refused (CSRF)', 403 === $r['status'], 'got ' . $r['status'] );
	$r = http( '/wp-admin/admin-post.php', array( 'method' => 'POST', 'headers' => array( 'Content-Type: application/x-www-form-urlencoded' ), 'body' => http_build_query( $flush ) ) );
	ok( 'a visitor who is not logged in cannot store the rules', in_array( $r['status'], array( 302, 400, 403 ), true ) && ( 302 !== $r['status'] || false !== strpos( $r['location'], 'wp-login.php' ) || '' === $r['location'] ), $r['status'] . ' ' . $r['location'] );
}
// A published post that already has the address /en/ must not lose it to the English home: the rules are not stored.
$rest_nonce = trim( $admin( '/wp-admin/admin-ajax.php?action=rest-nonce' )['body'] );
$rest       = function ( $method, $path, $payload = null ) use ( &$jar, $rest_nonce ) {
	$o = array(
		'method'  => $method,
		'headers' => array( 'Cookie: ' . implode( '; ', $jar ), 'X-WP-Nonce: ' . $rest_nonce, 'Content-Type: application/json' ),
	);
	if ( null !== $payload ) {
		$o['body'] = wp_json_encode_compat( $payload );
	}
	return http( $path, $o );
};
$created = $rest( 'POST', '/wp-json/wp/v2/posts', array( 'title' => 'En', 'slug' => 'en', 'status' => 'publish' ) );
$taken   = json_decode( $created['body'], true );
if ( 201 === $created['status'] && ! empty( $taken['id'] ) && 'en' === $taken['slug'] ) {
	$tools_taken = $admin( '/wp-admin/tools.php?page=nh-vien-links' );
	ok( 'the Tools screen says that a post has the address /en/', (bool) preg_match( '#Address /en/ used by a post</th><td>Yes#', $tools_taken['body'] ) );
	$flush_taken = $form_for( $tools_taken['body'], 'nh_vien_flush' );
	$r           = $flush_taken ? $admin( '/wp-admin/admin-post.php', $flush_taken ) : array( 'status' => 0, 'location' => '' );
	ok( 'the rules are not stored while a post has the address /en/', 302 === $r['status'] && false !== strpos( $r['location'], 'nh_vien_status=address_taken' ), $r['status'] . ' ' . $r['location'] );
	$deleted = $rest( 'DELETE', '/wp-json/wp/v2/posts/' . (int) $taken['id'] . '?force=true' );
	ok( 'the post used for that test is deleted again', 200 === $deleted['status'], 'status ' . $deleted['status'] );
} else {
	ok( 'a post with the address /en/ could be created for the test', false, 'status ' . $created['status'] . ' ' . substr( $created['body'], 0, 160 ) );
}
$pair_form = $form_for( $tools['body'], 'nh_vien_link' );
ok( 'the Tools screen has the pair form with a nonce', $pair_form && ! empty( $pair_form['_wpnonce'] ) );
if ( $pair_form && ! empty( $fx['vi_rent'] ) ) {
	$r = $admin( '/wp-admin/admin-post.php', array_merge( $pair_form, array( 'vi_post_id' => (string) $fx['vi_rent'], 'en_post_id' => (string) $fx['en_tips'] ) ) );
	ok( 'linking posts that are already linked is refused', 302 === $r['status'] && false === strpos( $r['location'], 'nh_vien_status=saved' ), $r['location'] );
	$r = $admin( '/wp-admin/admin-post.php', array_merge( $pair_form, array( 'vi_post_id' => '1', 'en_post_id' => '999999' ) ) );
	ok( 'linking a post that does not exist is refused', 302 === $r['status'] && false !== strpos( $r['location'], 'nh_vien_invalid_posts' ), $r['location'] );
	$r = $admin( '/wp-admin/admin-post.php', array_merge( $pair_form, array( 'vi_post_id' => '1abc', 'en_post_id' => '2' ) ) );
	ok( 'dirty post IDs are refused', 302 === $r['status'] && false !== strpos( $r['location'], 'nh_vien_invalid_ids' ), $r['location'] );
}
$login( 'sub1', 'sub1pass' );
$r = $admin( '/wp-admin/tools.php?page=nh-vien-links' );
ok( 'a subscriber cannot open the Tools screen', 403 === $r['status'], 'got ' . $r['status'] );
$r = $admin( '/wp-admin/admin-post.php', array( 'action' => 'nh_vien_flush', '_wpnonce' => $flush ? $flush['_wpnonce'] : 'x' ) );
ok( 'a subscriber cannot store the rules, even with an administrator nonce', 403 === $r['status'], 'got ' . $r['status'] );
$r = $admin( '/wp-admin/admin-post.php', array( 'action' => 'nh_vien_link', 'vi_post_id' => '1', 'en_post_id' => '2', '_wpnonce' => $pair_form ? $pair_form['_wpnonce'] : 'x' ) );
ok( 'a subscriber cannot link posts', in_array( $r['status'], array( 302, 403 ), true ) && ( 403 === $r['status'] || false === strpos( $r['location'], 'nh_vien_status=saved' ) ), $r['status'] . ' ' . $r['location'] );

echo "\n[11] The English home page\n";
$login( 'admin', 'admin' );
$tools = $admin( '/wp-admin/tools.php?page=nh-vien-links' );
ok( 'while a draft page has the address /en/ the plugin does not offer to create another', null === $form_for( $tools['body'], 'nh_vien_home_page' ) );
$r = page( '/en/' );
ok( 'a draft page at /en/ leaves the list of English posts in place', false !== strpos( text_of( xp( $r['body'] ), '//head/title' ), 'Latest articles' ) || false !== strpos( text_of( xp( $r['body'] ), '//head/title' ), 'English' ) );

echo "\n[12] Search suggestions follow the language of the page\n";
if ( 'editorial' === $theme ) {
	foreach ( array(
		'/'                              => 'vi',
		'/thue-xe-may-ha-noi/'           => 'vi',
		'/en/'                           => 'en',
		'/en/motorbike-rental-in-hanoi/' => 'en',
	) as $url => $language ) {
		$endpoint = attrs( xp( page( $url )['body'] ), '//form[@data-suggest-url]', 'data-suggest-url' );
		ok( "the search box on $url asks for $language suggestions", $endpoint && false !== strpos( $endpoint[0], 'nh_vien_lang=' . $language ), $endpoint ? $endpoint[0] : 'no search box' );
	}
}
list( $status, $urls ) = rest_suggestions( 'Bulk', 'en' );
ok( 'the English suggestions list English posts only', 200 === $status && $urls && ! array_diff( $urls, $all_en ), $status . ' ' . implode( ',', array_diff( $urls, $all_en ) ) );
list( $status, $urls ) = rest_suggestions( 'Bulk', 'vi' );
ok( 'the Vietnamese suggestions list no English post', 200 === $status && ! $urls, $status . ' ' . implode( ',', $urls ) );
list( $status, $urls ) = rest_suggestions( 'hàng loạt', 'vi' );
ok( 'the Vietnamese suggestions find Vietnamese posts', 200 === $status && $urls && ! array_diff( $urls, $all_vi ), $status . ' ' . implode( ',', array_diff( $urls, $all_vi ) ) );
list( $status, $urls ) = rest_suggestions( 'hàng loạt', 'en' );
ok( 'the English suggestions list no Vietnamese post', 200 === $status && ! $urls, $status . ' ' . implode( ',', $urls ) );
list( $status, $urls ) = rest_suggestions( 'Bulk', '' );
ok( 'a request without a language is answered as before, with the English posts at their English address', 200 === $status && $urls && ! array_diff( $urls, $all_en ), $status . ' ' . implode( ',', $urls ) );
list( $status, $urls ) = rest_suggestions( 'Bulk', 'xx' );
ok( 'an unknown language is ignored', 200 === $status && $urls && ! array_diff( $urls, $all_en ), $status . ' ' . implode( ',', $urls ) );
list( $status, $urls ) = rest_suggestions( 'English draft', 'en' );
ok( 'a draft English post is never suggested', 200 === $status && ! in_array( '/en/english-draft/', $urls, true ) && ! in_array( '/english-draft/', $urls, true ) );


echo "\n[13] Embeds find the English posts\n";
$oembed = function ( $path ) {
	$r = http( '/wp-json/oembed/1.0/embed?url=' . rawurlencode( $path ) );
	return array( $r['status'], json_decode( $r['body'], true ) );
};
list( $status, $data ) = $oembed( $base . '/en/motorbike-rental-in-hanoi/' );
ok( 'oEmbed finds an English post at its English address', 200 === $status && is_array( $data ) && isset( $data['title'] ) && false !== strpos( $data['title'], 'Motorbike rental' ), 'status ' . $status );
list( $status, $data ) = $oembed( $base . '/thue-xe-may-ha-noi/' );
ok( 'oEmbed still finds a Vietnamese post', 200 === $status && is_array( $data ) && isset( $data['title'] ) && false !== strpos( $data['title'], 'Thuê xe máy' ), 'status ' . $status );
list( $status ) = $oembed( $base . '/en/hidden-english-post/' );
ok( 'oEmbed finds no English post that may not be shown', 404 === $status, 'status ' . $status );
list( $status ) = $oembed( $base . '/en/nope/' );
ok( 'oEmbed finds nothing at an unknown English address', 404 === $status, 'status ' . $status );

echo "\n$checks checks, $failures failed\n";
exit( $failures ? 1 : 0 );
