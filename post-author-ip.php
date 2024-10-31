<?php
/**
 * Plugin Name: Post Author IP
 * Version:     1.4
 * Plugin URI:  https://coffee2code.com/wp-plugins/post-author-ip/
 * Author:      Scott Reilly
 * Author URI:  https://coffee2code.com/
 * Text Domain: post-author-ip
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Description: Records the IP address of the original post author when a post first gets created.
 *
 * Compatible with WordPress 4.9 through 5.7+.
 *
 * =>> Read the accompanying readme.txt file for instructions and documentation.
 * =>> Also, visit the plugin's homepage for additional information and updates.
 * =>> Or visit: https://wordpress.org/plugins/post-author-ip/
 *
 * @package Post_Author_IP
 * @author  Scott Reilly
 * @version 1.4
 */

/*
	Copyright (c) 2017-2021 by Scott Reilly (aka coffee2code)

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'c2c_PostAuthorIP' ) ) :

class c2c_PostAuthorIP {

	/**
	 * Field name for the post listing column.
	 *
	 * @access private
	 * @var string
	 */
	private static $field = 'post_author_ip';

	/**
	 * Returns version of the plugin.
	 *
	 * @since 1.0
	 */
	public static function version() {
		return '1.4';
	}

	/**
	 * Hooks actions and filters.
	 *
	 * @since 1.0
	 */
	public static function init() {
		// Load textdomain.
		load_plugin_textdomain( 'post-author-ip' );

		// Register hooks.
		add_filter( 'manage_posts_columns',        array( __CLASS__, 'add_post_column' )               );
		add_action( 'manage_posts_custom_column',  array( __CLASS__, 'handle_column_data' ),     10, 2 );

		add_action( 'load-edit.php',               array( __CLASS__, 'add_admin_css' )                 );
		add_action( 'load-post.php',               array( __CLASS__, 'add_admin_css' )                 );
		add_action( 'transition_post_status',      array( __CLASS__, 'transition_post_status' ), 10, 3 );
		add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'show_post_author_ip' )           );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor_assets' )   );
		add_action( 'init',                        array( __CLASS__, 'register_meta' ) );
		add_filter( 'is_protected_meta',           array( __CLASS__, 'is_protected_meta' ),      10, 2 );

		/* Privacy */
		add_action( 'admin_init',                         array( __CLASS__, 'add_privacy_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_privacy_erasers' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_data_exporter' ) );
	}

	/**
	 * Returns post types that will have their post author IP address recorded.
	 *
	 * By default, all public post types are included (except 'attachment').
	 *
	 * @since 3.0
	 * @uses apply_filters() Calls 'c2c_post_author_ip_post_types' with post types.
	 *
	 * @return array
	 */
	public static function get_post_types() {
		$post_types = get_post_types( array( 'public' => true ) );

		unset( $post_types['attachment'] );

		/**
		 * Filters the post types that can be stealth published.
		 *
		 * @since 1.3
		 *
		 * @param array $post_types Array of post type names.
		 */
		return (array) apply_filters( 'c2c_post_author_ip_post_types', array_values( $post_types ) );
	}

	/**
	 * Returns the name of the meta key.
	 *
	 * @since 1.3
	 * @uses apply_filters() Calls 'c2c_post_author_ip_meta_key' with default meta key name.
	 *
	 * @return string The meta key name. Default 'c2c-post-author-ip'.
	 */
	public static function get_meta_key_name() {
		// Default value.
		$meta_key_default = 'c2c-post-author-ip';

		/**
		 * Filters the name of the custom field key used by the plugin to store a
		 * post's stealth publish status.
		 *
		 * @since 1.3
		 *
		 * @param string $meta_key The name of the meta key used for storing the
		 *                         value of the post's post author IP address. If
		 *                         blank, then default is used. Default is
		 *                         'c2c-post-author-ip'.
		 */
		$meta_key = apply_filters( 'c2c_post_author_ip_meta_key', $meta_key_default );

		// If a meta key name was not returned from the filter, use the default.
		if ( ! $meta_key || ! is_string( $meta_key ) ) {
			$meta_key = $meta_key_default;
		}

		return $meta_key;
	}

	/**
	 * Registers the post meta field.
	 *
	 * @since 1.0
	 */
	public static function register_meta() {
		$config = array(
			'type'              => 'string',
			'description'       => __( 'The IP address of the original post author', 'post-author-ip' ),
			'single'            => true,
			'sanitize_callback' => array( __CLASS__, 'sanitize_ip_address' ),
			'auth_callback'     => function() {
				return current_user_can( 'edit_posts' );
			},
			'show_in_rest'      => true,
		);

		if ( function_exists( 'register_post_meta' ) ) {
			foreach ( self::get_post_types() as $post_type ) {
				register_post_meta( $post_type, self::get_meta_key_name(), $config );
				add_filter( "rest_pre_insert_{$post_type}", array( __CLASS__, 'rest_pre_insert' ), 1, 2 );
			}
		}
		// Pre WP 4.9.8 support
		else {
			register_meta( 'post', self::get_meta_key_name(), $config );
		}
	}

	/**
	 * Adds meta key as first-class object property prior to REST-initiated
	 * post insertion to ensure value gets handled.
	 *
	 * @since 1.3
	 *
	 * @param stdClass        $prepared_post An object representing a single
	 *                                       post prepared for inserting or
	 *                                       updating the database.
	 * @param WP_REST_Request $request       Request object.
	 * @return stdClass Single post prepared for inserting/updating the database.
	 */
	public static function rest_pre_insert( $prepared_post, $request ) {
		$meta_key_name = self::get_meta_key_name();
		// Make meta key a first-class object property.
		if ( isset( $request['meta'][ $meta_key_name ] ) ) {
			$prepared_post->{self::$field} = $request['meta'][ $meta_key_name ];
		}

		return $prepared_post;
	}

	/**
	 * Hides the meta key from the custom field dropdown.
	 *
	 * @since 1.3
	 *
	 * @param  bool   $protected Is the meta key protected?
	 * @param  string $meta_key  The meta key.
	 * @return bool True if meta key is protected, else false.
	 */
	public static function is_protected_meta( $protected, $meta_key ) {
		return $meta_key === self::get_meta_key_name() ? true : $protected;
	}

	/**
	 * Determines if the Author IP column should be shown.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	public static function include_column() {
		/**
		 * Filters to determine if post author IP column show appear in the admin
		 * post listing table.
		 *
		 * @since 1.0
		 *
		 * @param bool $show_column Should the column be shown? Default true.
		 */
		return (bool) apply_filters( 'c2c_show_post_author_ip_column', true );
	}

	/**
	 * Adds hook to outputs CSS for the display of the Author IP column if
	 * on the appropriate admin page.
	 *
	 * @since 1.0
	 */
	public static function add_admin_css() {
		if ( ! self::include_column() ) {
			return;
		}

		add_action( 'admin_head', array( __CLASS__, 'admin_css' ) );
	}

	/**
	 * Outputs CSS for the display of the Author IP column.
	 *
	 * @since 1.0
	 */
	public static function admin_css() {
		$field = self::$field;

echo <<<HTML
<style>
	.fixed .column-{$field} { width: 7rem; }
	#c2c-post-author-ip { font-weight: 600; }
</style>

HTML;
	}

	/**
	 * Displays the IP address of the original post author in the publish metabox.
	 *
	 * @since 1.0
	 */
	public static function show_post_author_ip() {
		global $post;

		$post_author_ip = self::get_post_author_ip( $post->ID );

		if ( ! $post_author_ip ) {
			return;
		}

		$author_ip = sprintf( '<span id="c2c-post-author-ip">%s</span>', sanitize_text_field( $post_author_ip ) );

		echo '<div class="misc-pub-section curtime misc-pub-curtime">';
		printf( __( 'Author IP address: <strong>%s</strong>', 'post-author-ip' ), $author_ip );
		echo '</div>';
	}

	/**
	 * Enqueues JavaScript and CSS for the block editor.
	 *
	 * @since 1.2.0
	 */
	public static function enqueue_block_editor_assets() {
		global $post;

		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		if ( ! $post ) {
			return;
		}

		$post_author_ip = self::get_post_author_ip( $post->ID );

		if ( ! $post_author_ip ) {
			return;
		}

		$asset_file = include( plugin_dir_path( __FILE__ ) . 'build/index.asset.php' );

		wp_enqueue_script(
			'post-author-ip-js',
			plugins_url( 'build/index.js', __FILE__ ),
			$asset_file['dependencies'],
			$asset_file['version']
		);

		wp_enqueue_style(
			'post-author-ip',
			plugins_url( 'assets/css/editor.css', __FILE__ ),
			array(),
			self::version()
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'post-author-ip-js', 'post-author-ip' );
		}
	}

	/**
	 * Adds a column to show the IP address of the original post author.
	 *
	 * @since 1.0
	 *
	 * @param  array $posts_columns Array of post column titles.
	 *
	 * @return array The $posts_columns array with the 'post-author-ip' column's title added.
	 */
	public static function add_post_column( $posts_columns ) {
		if ( self::include_column() ) {
			$posts_columns[ self::$field ] = __( 'Author IP', 'post-author-ip' );
		}

		return $posts_columns;
	}

	/**
	 * Outputs the IP address of the original post author for each post listed in the post
	 * listing table in the admin.
	 *
	 * @since 1.0
	 *
	 * @param string $column_name The name of the column.
	 * @param int    $post_id     The id of the post being displayed.
	 */
	public static function handle_column_data( $column_name, $post_id ) {
		if ( ! self::include_column() ) {
			return;
		}

		if ( self::$field === $column_name ) {
			$post_author_ip = self::get_post_author_ip( $post_id );

			if ( $post_author_ip ) {
				echo '<span>' . sanitize_text_field( $post_author_ip ) . '</span>';
			}
		}
	}

	/**
	 * Records the IP address of the original author of a post.
	 *
	 * @since 1.0
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function transition_post_status( $new_status, $old_status, $post ) {
		// Only concerned with posts on creation.
		if ( 'new' !== $old_status || 'revision' === get_post_type( $post ) ) {
			return;
		}

		if ( $post_author_ip = self::get_current_user_ip() ) {
			self::set_post_author_ip( $post->ID, $post_author_ip );
		}
	}

	/**
	 * Returns the IP address of the current user.
	 *
	 * @since 1.0
	 *
	 * @return string The IP address of the current user.
	 */
	public static function get_current_user_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP ) : '';

		/**
		 * Filters the current user's IP address as used by the plugin.
		 *
		 * @since 1.0
		 *
		 * @param string $ip The current user's IP address.
		 */
		return apply_filters(
			'c2c_get_current_user_ip',
			$ip
		);
	}

	/**
	 * Returns the IP address of the original post author.
	 *
	 * @since 1.0
	 *
	 * @param  int|WP_Post $post_id Post object or post id.
	 * @return string      The IP address of the original post author.
	 */
	public static function get_post_author_ip( $post_id ) {
		$post_author_ip = '';
		$post           = get_post( $post_id );

		if ( $post ) {
			/**
			 * Filters the post author IP address.
			 *
			 * @since 1.0
			 *
			 * @param string $ip      The post author IP address.
			 * @param int    $post_id The post ID.
			 */
			$post_author_ip = apply_filters(
				'c2c_get_post_author_ip',
				get_post_meta( $post->ID, self::get_meta_key_name(), true ),
				$post->ID
			);
		}

		return $post_author_ip;
	}

	/**
	 * Explicitly sets the post author IP address for a post.
	 *
	 * @since 1.0
	 *
	 * @param  int|WP_Post $post_id Post object or post id.
	 * @param  string      $ip      IP address.
	 */
	public static function set_post_author_ip( $post_id, $ip ) {
		$post = get_post( $post_id );

		if ( $post && $ip ) {
			/**
			 * Filters whether the post author IP can be stored for the post.
			 *
			 * @since 1.1
			 *
			 * @param bool   $allowed Can post author IP be saved for post? Default true.
			 * @param int    $post_id The post ID.
			 * @param string $ip      The post author IP address.
			 */
			$can_store_post_author_id = (bool) apply_filters( 'c2c_post_author_ip_allowed', true, $post_id, $ip );

			if ( $can_store_post_author_id ) {
				update_post_meta( $post->ID, self::get_meta_key_name(), filter_var( $ip, FILTER_VALIDATE_IP ) );
			}
		}
	}

	/**
	 * Adds a privacy policy statement.
	 *
	 * @since 1.4
	 */
	public static function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<h2 class="privacy-policy-tutorial">' . __( 'Post Author IP Plugin privacy policy content.', 'post-author-ip' ) . '</h2>'
			. '<strong class="privacy-policy-tutorial">' . __( 'Suggested Text:', 'post-author-ip' ) . '</strong> '
			. __( "If you create a post on the site, your IP address at the time of the post's creation will be stored as post metadata.", 'post-author-ip' );

		wp_add_privacy_policy_content( __( 'Post Author IP Plugin', 'post-author-ip' ), wp_kses_post( wpautop( $content, false ) ) );
	}

	/**
	 * Registers all data erasers.
	 *
	 * @since 1.4
	 *
	 * @param array $exporters An array of callable erasers of personal data.
	 * @return array
	 */
	public static function register_privacy_erasers( $erasers ) {
		$erasers['post-author-ip'] = array(
			'eraser_friendly_name' => __( 'Post Author IP Plugin', 'post-author-ip' ),
			'callback'             => array( __CLASS__, 'remove_ip_address_from_posts_by_email' ),
		);

		return $erasers;
	}

	/**
	 * Removes any stored IP addresses associated with a post authored by the
	 * user with the given email address.
	 *
	 * @since 1.4
	 *
	 * @param string $email_address Email address being removed.
	 * @param int    $page          Page number. Default 1.
	 * @return array
	 */
	public static function remove_ip_address_from_posts_by_email( $email_address, $page = 1 ) {
		global $wpdb;

		$items_removed = false;
		$removed_count = 0;

		$user = get_user_by( 'email', $email_address );

		if ( $user ) {
			$removed_count = $wpdb->query( $wpdb->prepare(
				"DELETE pm FROM $wpdb->postmeta AS pm INNER JOIN $wpdb->posts AS p ON p.ID = pm.post_id WHERE p.post_author = %d AND pm.meta_key = %s",
				$user->ID,
				self::get_meta_key_name()
			) );
		}

		if ( $removed_count ) {
			$items_removed = true;
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Registers data exporter.
	 *
	 * @since 1.4
	 *
	 * @param array $exporters
	 * @return array
	 */
	public static function register_data_exporter( $exporters ) {
		$exporters['post-author-ip'] = array(
			'exporter_friendly_name' => __( 'Post Author IP Plugin', 'post-author-ip' ),
			'callback'               => array( __CLASS__, 'export_user_data_by_email' ),
		);

		return $exporters;
	}

	/**
	 * Export user meta for a user using the supplied email.
	 *
	 * @since 1.4
	 *
	 * @param string $email_address Email address being removed.
	 * @param int    $page          Page number. Default 1.
	 * @return array
	 */
	public static function export_user_data_by_email( $email_address, $page = 1 ) {
		$number = 500; // Limit to avoid timeouts.
		$page   = (int) $page;
		$posts  = $export_items = array();

		$user = get_user_by( 'email', $email_address );

		if ( $user ) {
			// Get posts made by the user that have a post author IP assigned.
			$posts = get_posts( array(
				'author'         => $user->ID,
				'meta_key'       => self::get_meta_key_name(),
				'paged'          => $page,
				'posts_per_page' => $number,
			) );

			foreach ( (array) $posts as $post ) {
				$ip_address = get_post_meta( $post->ID, self::get_meta_key_name(), true );

				if ( $ip_address ) {
					$item_id = "post-{$post->ID}";
					$group_id = 'posts';

					$data = array(
						array(
							'name'  => __( 'Post Author IP', 'post-author-ip' ),
							'value' => $ip_address,
						),
					);

					$export_items[] = array(
						'group_id'    => $group_id,
						'item_id'     => $item_id,
						'data'        => $data,
					);
				}
			}
		}

		// Have all matching posts been included?
		$done = count( $posts ) < $number;

		return array(
			'data' => $export_items,
			'done' => $done,
		);
	}

} // end c2c_PostAuthorIP

add_action( 'plugins_loaded', array( 'c2c_PostAuthorIP', 'init' ) );

endif; // end if !class_exists()
