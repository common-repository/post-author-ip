=== Post Author IP ===
Contributors: coffee2code
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=6ARCFJ9TX3522
Tags: post, author, IP, IP address, audit, auditing, tracking, users, coffee2code
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 4.9
Tested up to: 5.7
Stable tag: 1.4

Records the IP address of the original post author when a post first gets created.

== Description ==

This plugin records the IP address of the original post author when a post first gets created.

The admin listing of posts is amended with a new "Author IP" column that shows the IP address of the author who first saved the post.

The plugin is unable to provide IP address information for posts that were created prior to the use of this plugin.


Links: [Plugin Homepage](https://coffee2code.com/wp-plugins/post-author-ip/) | [Plugin Directory Page](https://wordpress.org/plugins/post-author-ip/) | [GitHub](https://github.com/coffee2code/post-author-ip/) | [Author Homepage](https://coffee2code.com)


== Installation ==

1. Install via the built-in WordPress plugin installer. Or download and unzip `post-author-ip.zip` inside the plugins directory for your site (typically `wp-content/plugins/`)
2. Activate the plugin through the 'Plugins' admin menu in WordPress


== Screenshots ==

1. A screenshot of the admin post listing showing the added "Author IP" column. It demonstrates the mix of a post where the post author IP address was recorded, and posts where it wasn't (due to the plugin not being activated at the time).
2. A screenshot of the Publish metabox for a post showing the post author's IP address (for versions of WordPress older than 5.0, or later if the new block editor aka Gutenberg is disabled)
3. A screenshot of the block editor sidebar panel for a post showing the post author IP address (WP 5.0 and later)

== Frequently Asked Questions ==

= If a post is originally drafted at one IP address, then later worked on at another IP address, which IP address gets recorded? =

The IP address in use at the time that the post is first saved (regardless of whether the post was saved as a draft, immediately published, or some other status) will be recorded.

= Are other IP addresses in use during the post's handling (such as when it is edited, published, etc) also tracked? =

No, this plugin only records the IP address in use when the post was first saved.

= How do I see (or hide) the "Author IP" column in an admin listing of posts? =

In the upper-right of the page is a "Screen Options" link that reveals a panel of options. In the "Columns" section, check (to show) or uncheck (to hide) the "Author IP" option.

= Is this plugin compatible with the new block editor (aka Gutenberg)? =

Yes. This plugin is compatible with the block editor as well as the classic editor.

= Is this plugin GDPR-compliant? =

Yes. The IP address stored for authors on the posts they created will be exported on data export requests and deleted for data erasure requests.

= Does this plugin include unit tests? =

Yes.


== Hooks ==

The plugin is further customizable via four filters. Typically, code making use of filters should ideally be put into a mu-plugin or site-specific plugin (which is beyond the scope of this readme to explain).

**c2c_show_post_author_ip_column (filter)**

The 'c2c_show_post_author_ip_column' filter allows you to determine if the post author IP column should appear in the admin post listing table. Your hooking function can be sent 1 argument:

Argument :

* $show_column (bool) Should the column be shown? Default true.

Example:

`
/**
 * Don't show the post author IP column except to admins.
 *
 * @param bool $show_column Should the column be shown? Default true.
 * @return bool
 */
function post_author_ip_column_admin_only( $show ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		$show = false;
	}
	return $show;
}
add_filter( 'c2c_show_post_author_ip_column', 'post_author_ip_column_admin_only' );
`

**c2c_get_post_author_ip (filter)**

The 'c2c_get_post_author_ip' filter allows you to customize the value stored as the post author IP address. Your hooking function can be sent 2 arguments:

Arguments :

* $ip (string)   The post author IP address.
* $post_id (int) The post ID.

Example:

`
/**
 * Store all IP addresses from local subnet IP addresses as the same IP address.
 *
 * @param string $ip      The post author IP address.
 * @param int    $post_id The post ID.
 * @return string
 */
function customize_post_author_ip( $ip, $post_id ) {
	if ( 0 === strpos( $ip, '192.168.' ) ) {
		$ip = '192.168.1.1';
	}
	return $ip;
}
add_filter( 'c2c_get_post_author_ip', 'customize_post_author_ip', 10, 2 );
`

**c2c_get_current_user_ip (filter)**

The 'c2c_get_current_user_ip' filter allows you to customize the current user's IP address, as used by the plugin. Your hooking function can be sent 1 argument:

Argument :

* $ip (string)   The post author IP address.

Example:

`
/**
 * Overrides localhost IP address.
 *
 * @param string $ip      The post author IP address.
 * @param int    $post_id The post ID.
 * @return string
 */
function customize_post_author_ip( $ip, $post_id ) {
	if ( 0 === strpos( $ip, '192.168.' ) ) {
		$ip = '192.168.1.1';
	}
	return $ip;
}
add_filter( 'c2c_get_post_author_ip', 'customize_post_author_ip', 10, 2 );
`

**c2c_post_author_ip_allowed (filter)**

The 'c2c_post_author_ip_allowed' filter allows you to determine on a per-post basis if the post author IP should be stored. Your hooking function can be sent 3 arguments:

Arguments :

* $allowed (bool) Can post author IP be saved for post? Default true.
* $post_id (int)  The post ID.
* $ip (string)    The post author IP address.

Example:

`
/**
 * Don't bother storing localhost IP addresses.
 *
 * @param bool   $allowed Can post author IP be saved for post? Default true.
 * @param int    $post_id The post ID.
 * @param string $ip      The post author IP address.
 * @return string
 */
function disable_localhost_post_author_ips( $allowed, $post_id, $ip ) {
	if ( $allowed && 0 === strpos( $ip, '192.168.' ) ) {
		$allowed = false;
	}
	return $allowed;
}
add_filter( 'c2c_post_author_ip_allowed', 'disable_localhost_post_author_ips', 10, 3 );
`


== Changelog ==

= 1.4 (2021-06-09) =
Highlights:

This recommended release adds GDPR compliance for data export and erasure, modernizes block editor implementation, restructures unit test files, and notes compatibility through WP 5.7.

Details:

* New: Add GDPR compliance for data export and erasure
  * New: Add `register_privacy_erasers()` and `remove_ip_address_from_posts_by_email()` for handling data erasure requests
  * New: Add `register_data_exporter()` and `export_user_data_by_email()` for handling data export requests
  * New: Add `add_privacy_policy_content()` for outputting suggested privacy policy snippet
  * New: Add FAQ entry denoting GDPR compliance
* Change: Modernize block editor implementation and update JS dependencies
* Change: Remove check for theme support of HTML5 since that isn't relevant to admin
* Change: Enable script translations
* Change: Note compatibility through WP 5.7+
* Change: Update copyright date (2021)
* Unit tests:
    * Change: Restructure unit test directories and files into `tests/` top-level directory
    * Change: Remove 'test-' prefix from unit test files
    * Change: In bootstrap, store path to plugin file constant
    * Change: Rename `phpunit.xml` to `phpunit.xml.dist` per best practices
* New: Add a few more possible TODO items

= 1.3 (2020-08-06) =
Highlights:

This recommended release adds support for all public post types, reduces column width, improves meta key handling, expands unit testing, adds a TODO.md file, updates compatibility to be WP 4.9 through 5.4+, and more internally.

Details:

* New: Enable plugin functionality for all public post types by default
    * New: Add `get_post_types()` for retrieving post types
    * New: Add filter `c2c_stealth_publish_post_types` to filter post types
* New: Add `is_protected_meta()` to protect the meta key from being exposed as a custom field
* New: Improve configurability and accessibility of meta key name
  * New: Add `get_meta_key_name()` as getter for meta_key name
  * New: Add filter `c2c_post_author_ip_meta_key` for customizing meta key name
* New: Add `rest_pre_insert()` to add meta key as first-class object property prior to REST-initiated update
* New: Add HTML5 compliance by omitting `type` attribute for `style` tag when the theme supports 'html5'
* New: Add TODO.md for newly added potential TODO items
* Change: Reduce width of 'Author IP' column
* Change: Remove duplicate hook registration
* Change: Note compatibility through WP 5.4+
* Change: Drop compatibility for version of WP older than 4.9
* Change: Update JS dependencies
* Change: Tweak formatting of CSS styles
* Change: Update links to coffee2code.com to be HTTPS
* Unit tests:
    * New: Add tests for `add_admin_css()`, `admin_css()`, `add_post_column()`, `enqueue_block_editor_assets()`, `handle_column_data()`
    * New: Add tests for `include_column()`, `register_meta()`, `show_post_author_ip()`, `transition_post_status()`
    * New: Add test for default hooks
    * Change: Use `get_meta_key_name()` to set default meta key used by tests
    * Change: Remove unnecessary unregistering of hooks in `tearDown()`
    * Change: Use HTTPS for link to WP SVN repository in bin script for configuring unit tests

= 1.2.1 (2020-01-06) =
* New: Unit tests: Add test to verify plugin hooks `plugins_loaded` action to initialize itself
* Change: Note compatibility through WP 5.3+
* Change: Update JS dependencies
* Change: Update copyright date (2020)

_Full changelog is available in [CHANGELOG.md](https://github.com/coffee2code/post-author-ip/blob/master/CHANGELOG.md)._


== Upgrade Notice ==

= 1.4 =
Recommended update: added GDPR compliance for data export and erasure, modernized block editor implementation, restructured unit test files, noted compatibility through WP 5.7, and updated copyright date (2021).

= 1.3 =
Recommended update: added support for all public post types, reduced column width, improved meta key handling, expanded unit testing, added TODO.md file, updated compatibility to be WP 4.9 through 5.4+, and more internally.

= 1.2.1 =
Trivial update: noted compatibility through WP 5.3+, updated JS development dependencies, and updated copyright date (2020)

= 1.2 =
Recommended feature update: added support for the new block editor (aka Gutenberg),

= 1.1 =
Minor update: added 'c2c_post_author_ip_allowed' filter, modified initialization handling, noted compatibility through WP 5.1+, updated copyright date (2019), and more.

= 1.0 =
Initial public release.
