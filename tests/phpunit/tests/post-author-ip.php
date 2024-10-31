<?php

defined( 'ABSPATH' ) or die();

class Post_Author_IP_Test extends WP_UnitTestCase {

	protected static $field    = 'post_author_ip';
	protected static $meta_key = '';

	protected static $default_ip = '192.168.2.30';
	protected static $filter_ip  = '192.168.1.112';

	/**
	 * Test REST Server
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	public function setUp() {
		parent::setUp();

		$_SERVER['REMOTE_ADDR'] = self::$default_ip;
		$GLOBALS['post'] = '';

		c2c_PostAuthorIP::register_meta();

		self::$meta_key = c2c_PostAuthorIP::get_meta_key_name();

		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$this->server = $wp_rest_server = new \WP_REST_Server;
		do_action( 'rest_api_init' );
	}

	public function tearDown() {
		parent::tearDown();
		$this->unset_current_user();
	}


	//
	//
	// DATA PROVIDERS
	//
	//


	public static function get_default_hooks() {
		return array(
			array( 'filter', 'manage_posts_columns',        'add_post_column'             ),
			array( 'action', 'manage_posts_custom_column',  'handle_column_data'          ),
			array( 'action', 'load-edit.php',               'add_admin_css'               ),
			array( 'action', 'load-post.php',               'add_admin_css'               ),
			array( 'action', 'transition_post_status',      'transition_post_status'      ),
			array( 'action', 'post_submitbox_misc_actions', 'show_post_author_ip'         ),
			array( 'action', 'enqueue_block_editor_assets', 'enqueue_block_editor_assets' ),
			array( 'action', 'init',                        'register_meta'               ),
			array( 'filter', 'is_protected_meta',           'is_protected_meta'           ),
		);
	}


	//
	//
	// HELPER FUNCTIONS
	//
	//


	private function create_user( $set_as_current = true ) {
		$user_id = $this->factory->user->create();
		if ( $set_as_current ) {
			wp_set_current_user( $user_id );
		}
		return $user_id;
	}

	// helper function, unsets current user globally. Taken from post.php test.
	private function unset_current_user() {
		global $current_user, $user_ID;

		$current_user = $user_ID = null;
	}


	//
	//
	// FUNCTIONS FOR HOOKING ACTIONS/FILTERS
	//
	//


	public function query_for_posts( $text ) {
		$q = new WP_Query( array( 'post_type' => 'post' ) );
		$GLOBALS['custom_query'] = $q;
		return $text;
	}

	public function filter_on_special_meta( $wpquery ) {
		$wpquery->query_vars['meta_query'][] = array(
			'key'     => 'special',
			'value'   => '1',
			'compare' => '='
		);
	}

	public function check_default_c2c_published_by_post_status( $post_statuses ) {
		return self::$default_c2c_published_by_post_status = $post_statuses;
	}

	public function c2c_post_author_ip( $ip ) {
		return self::$filter_ip;
	}

	public function c2c_post_author_ip_allowed( $allowed, $post_id, $ip ) {
		if ( $_SERVER['REMOTE_ADDR'] === self::$default_ip ) {
			$allowed = false;
		}

		return $allowed;
	}


	//
	//
	// TESTS
	//
	//


	public function test_plugin_version() {
		$this->assertEquals( '1.4', c2c_PostAuthorIP::version() );
	}

	public function test_class_is_available() {
		$this->assertTrue( class_exists( 'c2c_PostAuthorIP' ) );
	}

	public function test_hooks_plugins_loaded() {
		$this->assertEquals( 10, has_action( 'plugins_loaded', array( 'c2c_PostAuthorIP', 'init' ) ) );
	}

	/**
	 * @dataProvider get_default_hooks
	 */
	public function test_default_hooks( $hook_type, $hook, $function, $priority = 10, $class_method = true ) {
		$callback = $class_method ? array( 'c2c_PostAuthorIP', $function ) : $function;

		$prio = $hook_type === 'action' ?
			has_action( $hook, $callback ) :
			has_filter( $hook, $callback );

		$this->assertNotFalse( $prio );
		if ( $priority ) {
			$this->assertEquals( $priority, $prio );
		}
	}

	public function test_meta_key_created_for_post_saved_as_draft() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );

		$this->assertEquals( self::$default_ip, get_post_meta( $post_id, self::$meta_key, true ) );
	}

	public function test_meta_key_created_for_post_immediately_published() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		$this->assertEquals( self::$default_ip, get_post_meta( $post_id, self::$meta_key, true ) );
	}

	public function test_meta_key_created_for_post_future_published() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'future', 'post_date' => '2080-01-01 12:00:00' ) );

		$this->assertEquals( self::$default_ip, get_post_meta( $post_id, self::$meta_key, true ) );
	}

	public function test_meta_key_not_updated_for_draft_upon_publish() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );

		$post = get_post( $post_id );

		// Simulate user moved to new IP address.
		$_SERVER['REMOTE_ADDR'] = '192.168.13.13';

		$post->post_status = 'publish';
		wp_update_post( $post );

		$this->assertEquals( self::$default_ip, get_post_meta( $post_id, self::$meta_key, true ) );
	}

	public function test_meta_key_created_for_custom_post_type_created_via_wp_insert_post() {
		register_post_type( 'job', array( 'label' => 'job' ) );
		$args = array(
			'post_author'  => 1,
			'post_content' => 'Sample post',
			'post_status'  => 'draft',
			'post_title'   => 'Sample title',
			'post_type'    => 'job',
		);

		$post_id = wp_insert_post( $args );

		$this->assertEquals( self::$default_ip, get_post_meta( $post_id, self::$meta_key, true ) );
	}


	/*
	 * REST API
	 */


	public function test_meta_is_registered() {
		$this->assertTrue( registered_meta_key_exists( 'post', self::$meta_key, 'post' ) );
	}

	public function test_rest_post_request_includes_meta() {
		$author_id = $this->create_user( false );
		$post_id = $this->factory->post->create( array( 'post_status' => 'publish', 'post_author' => $author_id ) );
		c2c_PostAuthorIP::set_post_author_ip( $post_id, self::$default_ip );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'meta', $data );

		$meta = (array) $data['meta'];

		$this->assertArrayHasKey( self::$meta_key, $meta );
		$this->assertEquals( self::$default_ip, $meta[ self::$meta_key ] );
	}

	/*
	 * get_post_types()
	 */

	public function test_get_post_types( $extra_post_types = array() ) {
		$expected = array_merge( array( 'post', 'page' ), $extra_post_types );

		$this->assertEquals( $expected, c2c_PostAuthorIP::get_post_types() );
	}

	public function test_c2c_stealth_publish_post_types_with_custom_post_type() {
		register_post_type( 'private', array( 'public' => false, 'name' => 'Private' ) );
		register_post_type( 'book', array( 'public' => true, 'name' => 'Book' )	);

		$this->test_get_post_types( array( 'book' ) );
	}

	/*
	 * filter: c2c_post_author_ip_post_types
	 */

	 public function test_filter_c2c_post_author_ip_post_types() {
		register_post_type( 'private', array( 'public' => false, 'name' => 'Private' ) );
		register_post_type( 'book', array( 'public' => true, 'name' => 'Book' )	);

		add_filter( 'c2c_post_author_ip_post_types', function ( $p ) {
			// Add the non-public post type 'private'.
			$p[] = 'private';
			$p = array_flip( $p );
			// Remove the public post type 'book'.
			unset( $p[ 'book' ] );
			return array_values( array_flip( $p ) );
		} );

		$this->test_get_post_types( array( 'private' ) );
	}

	/*
	 * register_meta()
	 */

	public function test_meta_key_is_registered() {
		c2c_PostAuthorIP::register_meta();

		$this->assertTrue( registered_meta_key_exists( 'post', self::$meta_key, 'post' ) );
		$this->assertFalse( registered_meta_key_exists( 'book', self::$meta_key, 'post' ) );
	}

	public function test_meta_key_is_registered_for_nonstandard_public_post_type() {
		register_post_type( 'book', array( 'public' => true, 'name' => 'Book' )	);
		c2c_PostAuthorIP::register_meta();

		$this->assertTrue( registered_meta_key_exists( 'post', self::$meta_key, 'post' ) );
		$this->assertTrue( registered_meta_key_exists( 'post', self::$meta_key, 'book' ) );
	}

	/*
	 * get_meta_key_name()
	 */

	public function test_get_meta_key_name_default() {
		$this->assertEquals( 'c2c-post-author-ip', c2c_PostAuthorIP::get_meta_key_name() );
	}

	/*
	 * filter: c2c_post_author_ip_meta_key
	 */

	public function test_get_meta_key_name_with_valid_filter_value_c2c_post_author_ip_meta_key() {
		add_filter( 'c2c_post_author_ip_meta_key', function ( $x ) { return 'new-key'; } );

		$this->assertEquals( 'new-key', c2c_PostAuthorIP::get_meta_key_name() );
	}

	public function test_get_meta_key_name_with_invalid_filter_value_c2c_post_author_ip_meta_key() {
		add_filter( 'c2c_post_author_ip_meta_key', '__return_empty_string' );

		$this->test_get_meta_key_name_default();

		add_filter( 'c2c_post_author_ip_meta_key', '__return_empty_array' );

		$this->test_get_meta_key_name_default();

		add_filter( 'c2c_post_author_ip_meta_key', '__return_zero' );

		$this->test_get_meta_key_name_default();
	}

	/*
	 * rest_pre_insert()
	 */

	public function test_rest_pre_insert_does_not_set_object_when_no_stealth_publish() {
		$obj = (object) array( 'post_name' => 'Test' );
		$response = array( 'meta' => array( 'something' => '1' ) );

		$result = c2c_PostAuthorIP::rest_pre_insert( $obj, $response );

		$this->assertFalse( property_exists( $result, self::$field ) );
	}

	public function test_rest_pre_insert_sets_obj_property_to_value_of_1() {
		$obj = (object) array( 'post_name' => 'Test' );
		$response = array( 'meta' => array( self::$meta_key => '1' ) );

		$result = c2c_PostAuthorIP::rest_pre_insert( $obj, $response );

		$this->assertTrue( property_exists( $result, self::$field ) );
		$this->assertEquals( '1', $result->{self::$field} );
	}

	public function test_rest_pre_insert_sets_obj_property_to_value_of_0() {
		$obj = (object) array( 'post_name' => 'Test' );
		$response = array( 'meta' => array( self::$meta_key => '0' ) );

		$result = c2c_PostAuthorIP::rest_pre_insert( $obj, $response );

		$this->assertTrue( property_exists( $result, self::$field ) );
		$this->assertEquals( '0', $result->{self::$field} );
	}

	/*
	 * is_protected_meta()
	 */

	public function test_is_protected_meta_for_plugin_meta() {
		$this->assertTrue( c2c_PostAuthorIP::is_protected_meta( false, self::$meta_key ) );
	}

	public function test_is_protected_meta_for_unrelated_meta() {
		$this->assertFalse( c2c_PostAuthorIP::is_protected_meta( false, 'bogus' ) );
		$this->assertTrue( c2c_PostAuthorIP::is_protected_meta( true, 'bogus' ) );
	}

	/*
	 * include_column()
	 */

	public function test_include_column() {
		$this->assertTrue( c2c_PostAuthorIP::include_column() );
	}

	/*
	 * Filter: c2c_show_post_author_ip_column
	 */

	public function test_filter_c2c_show_post_author_ip_column() {
		$this->assertTrue( c2c_PostAuthorIP::include_column() );

		add_filter( 'c2c_show_post_author_ip_column', '__return_false' );

		$this->assertFalse( c2c_PostAuthorIP::include_column() );
	}

	public function test_filter_c2c_show_post_author_ip_column_with_bogus_value() {
		$this->assertTrue( c2c_PostAuthorIP::include_column() );

		add_filter( 'c2c_show_post_author_ip_column', '__return_empty_array' );
		$val = c2c_PostAuthorIP::include_column();

		$this->assertIsBool( $val );
		$this->assertFalse( $val );
	}

	/*
	 * add_admin_css()
	 */

	public function test_add_admin_css() {
		c2c_PostAuthorIP::add_admin_css();

		$this->assertEquals( 10, has_action( 'admin_head', array( 'c2c_PostAuthorIP', 'admin_css' ) ) );
	}

	public function test_add_admin_css_when_column_not_shown() {
		add_filter( 'c2c_show_post_author_ip_column', '__return_false' );
		c2c_PostAuthorIP::add_admin_css();

		$this->assertFalse( has_action( 'admin_head', array( 'c2c_PostAuthorIP', 'admin_css' ) ) );
	}

	/*
	 * admin_css()
	 */

	public function test_admin_css() {
		$expected = "<style>
	.fixed .column-post_author_ip { width: 7rem; }
	#c2c-post-author-ip { font-weight: 600; }
</style>\n";

		$this->expectOutputRegex( '~^' . preg_quote( $expected ) . '$~', c2c_PostAuthorIP::admin_css() );
	}

	/*
	 * show_post_author_ip()
	 */

	public function test_show_post_author_ip() {
		global $post;
		$ip = '192.168.1.222';
		$post = $this->factory->post->create_and_get( array( 'post_status' => 'draft' ) );
		c2c_PostAuthorIP::set_post_author_ip( $post->ID, $ip );

		$expected = '<div class="misc-pub-section curtime misc-pub-curtime">';
		$expected .= 'Author IP address: <strong><span id="c2c-post-author-ip">' . $ip . '</span></strong>';
		$expected .= '</div>';

		$this->expectOutputRegex( '~^' . preg_quote( $expected ) . '$~', c2c_PostAuthorIP::show_post_author_ip() );
	}

	public function test_show_post_author_ip_for_post_without_saved_value() {
		// Unset the REMOTE_ADDR since the post creation below will fire a transition and store IP address.
		$_SERVER['REMOTE_ADDR'] = '';

		$GLOBALS['post'] = $this->factory->post->create_and_get( array( 'post_status' => 'publish' ) );

		$this->expectOutputRegex( '~^$~', c2c_PostAuthorIP::show_post_author_ip() );
	}

	/*
	 * enqueue_block_editor_assets()
	 */

	public function test_enqueue_block_editor_assets_not_enqueued_by_default() {
		c2c_PostAuthorIP::enqueue_block_editor_assets();

		$this->assertFalse( wp_script_is( 'post-author-ip-js', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'post-author-ip', 'enqueued' ) );
	}

	public function test_enqueue_block_editor_assets_for_non_applicable_post() {
		global $post;
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		$post = get_post( $post_id );

		c2c_PostAuthorIP::enqueue_block_editor_assets();

		$this->assertTrue( wp_script_is( 'post-author-ip-js', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'post-author-ip', 'enqueued' ) );
	}

	public function test_enqueue_block_editor_assets_for_applicable_post() {
		global $post;
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		c2c_PostAuthorIP::set_post_author_ip( $post_id, '192.168.1.225' );
		$post = get_post( $post_id );

		c2c_PostAuthorIP::enqueue_block_editor_assets();

		$this->assertTrue( wp_script_is( 'post-author-ip-js', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'post-author-ip', 'enqueued' ) );
	}

	/*
	 * add_post_column()
	 */

	public function test_add_post_column() {
		$expected = array( 'example' => 'something', self::$field => 'Author IP' );

		$this->assertSame( $expected, c2c_PostAuthorIP::add_post_column( array( 'example' => 'something' ) ) );
	}

	public function test_add_post_column_when_column_not_showing() {
		add_filter( 'c2c_show_post_author_ip_column', '__return_false' );

		$expected = array( 'example' => 'something' );

		$this->assertSame( $expected, c2c_PostAuthorIP::add_post_column( $expected ) );
	}

	/*
	 * handle_column_data()
	 */

	public function test_handle_column_data() {
		$ip = '192.168.1.225';
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		c2c_PostAuthorIP::set_post_author_ip( $post_id, $ip );

		$expected = '<span>' . $ip . '</span>';

		$this->expectOutputRegex( '~^' . preg_quote( $expected ) . '$~', c2c_PostAuthorIP::handle_column_data( self::$field, $post_id ) );
	}

	public function test_handle_column_data_when_column_not_showing() {
		add_filter( 'c2c_show_post_author_ip_column', '__return_false' );
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );

		$this->expectOutputRegex( '~^$~', c2c_PostAuthorIP::handle_column_data( self::$field, $post_id ) );
	}

	public function test_handle_column_data_for_different_column() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );

		$this->expectOutputRegex( '~^$~', c2c_PostAuthorIP::handle_column_data( 'title', $post_id ) );
	}

	public function test_handle_column_data_for_post_without_ip_saved() {
		// Unset the REMOTE_ADDR since the post creation below will fire a transition and store IP address.
		$_SERVER['REMOTE_ADDR'] = '';

		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );

		$this->expectOutputRegex( '~^$~', c2c_PostAuthorIP::handle_column_data( self::$field, $post_id ) );
	}

	/*
	 * transition_post_status()
	 */

	public function test_transition_post_status() {
		// Unset the REMOTE_ADDR since the post creation below will fire a transition and store IP address.
		$_SERVER['REMOTE_ADDR'] = '';

		$post = $this->factory->post->create_and_get( array( 'post_status' => 'draft' ) );

		$_SERVER['REMOTE_ADDR'] = self::$default_ip;

		c2c_PostAuthorIP::transition_post_status( 'publish', 'new', $post );

		$this->assertEquals( self::$default_ip, c2c_PostAuthorIP::get_post_author_ip( $post->ID ) );
	}

	public function test_transition_post_status_when_old_status_is_not_new() {
		// Unset the REMOTE_ADDR since the post creation below will fire a transition and store IP address.
		$_SERVER['REMOTE_ADDR'] = '';

		$post = $this->factory->post->create_and_get( array( 'post_status' => 'draft' ) );

		$_SERVER['REMOTE_ADDR'] = self::$default_ip;

		c2c_PostAuthorIP::transition_post_status( 'publish', 'draft', $post );

		$this->assertEmpty( c2c_PostAuthorIP::get_post_author_ip( $post->ID ) );
	}

	public function test_transition_post_status_when_post_is_revision() {
		// Unset the REMOTE_ADDR since the post creation below will fire a transition and store IP address.
		$_SERVER['REMOTE_ADDR'] = '';

		$post = $this->factory->post->create_and_get( array( 'post_status' => 'draft', 'post_type' => 'revision' ) );

		$_SERVER['REMOTE_ADDR'] = self::$default_ip;

		c2c_PostAuthorIP::transition_post_status( 'publish', 'new', $post );

		$this->assertEmpty( c2c_PostAuthorIP::get_post_author_ip( $post->ID ) );
	}


	/*
	 * set_post_author_ip()
	 */

	public function test_set_post_author_ip() {
		$ip = '192.168.1.225';

		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );

		$this->assertEquals( self::$default_ip, c2c_PostAuthorIP::get_post_author_ip( $post_id ) );

		c2c_PostAuthorIP::set_post_author_ip( $post_id, $ip );

		$this->assertEquals( $ip , c2c_PostAuthorIP::get_post_author_ip( $post_id ) );
	}

	/*
	 * get_post_author_ip()
	 */

	public function test_get_post_author_ip() {
		$ip = '192.168.1.222';

		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		c2c_PostAuthorIP::set_post_author_ip( $post_id, $ip );

		$this->assertEquals( $ip , c2c_PostAuthorIP::get_post_author_ip( $post_id ) );
	}

	public function test_get_post_author_ip_on_post_without_the_meta() {
		$_SERVER['REMOTE_ADDR'] = '';

		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );

		$this->assertEmpty( c2c_PostAuthorIP::get_post_author_ip( $post_id ) );
	}

	/*
	 * get_current_user_ip()
	 */

	public function test_get_current_user_ip() {
		$_SERVER['REMOTE_ADDR'] = '';

		$this->assertEmpty( c2c_PostAuthorIP::get_current_user_ip() );

		$ip = '192.168.1.111';
		$_SERVER['REMOTE_ADDR'] = $ip;

		$this->assertEquals( $ip , c2c_PostAuthorIP::get_current_user_ip() );
	}

	/*
	 * Filter: c2c_get_post_author_ip
	 */

	public function test_filter_c2c_get_post_author_ip() {
		add_filter( 'c2c_get_post_author_ip', array( $this, 'c2c_post_author_ip' ) );

		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );

		$this->assertEquals( self::$filter_ip, c2c_PostAuthorIP::get_post_author_ip( $post_id ) );
	}

	/*
	 * Filter: c2c_get_current_user_ip
	 */

	public function test_filter_c2c_get_current_user_ip() {
		add_filter( 'c2c_get_current_user_ip', array( $this, 'c2c_post_author_ip' ) );

		$this->assertEquals( self::$filter_ip , c2c_PostAuthorIP::get_current_user_ip() );
	}

	/*
	 * Filter: c2c_post_author_ip_allowed
	 */

	public function test_filter_c2c_post_author_ip_allowed() {
		add_filter( 'c2c_post_author_ip_allowed', array( $this, 'c2c_post_author_ip_allowed' ), 10, 3 );

		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );

		$this->assertEmpty( c2c_PostAuthorIP::get_post_author_ip( $post_id ) );
	}

	/*
	 * register_privacy_erasers()
	 */

	public function test_register_privacy_erasers() {
		$erasures = array(
			'example-plugin' => array(
				'eraser_friendly_name' => 'Example Plugin',
				'callback'             => '',
			),
		);

		$result = c2c_PostAuthorIP::register_privacy_erasers( $erasures );

		$this->assertArrayHasKey( 'post-author-ip', $result );
		$this->assertArrayHasKey( 'example-plugin', $result );
		$this->assertArrayHasKey( 'eraser_friendly_name', $result['post-author-ip'] );
		$this->assertEquals( 'Post Author IP Plugin', $result['post-author-ip']['eraser_friendly_name'] );
		$this->assertArrayHasKey( 'callback', $result['post-author-ip'] );
		$this->assertEquals( array( 'c2c_PostAuthorIP', 'remove_ip_address_from_posts_by_email' ), $result['post-author-ip']['callback'] );
	}

	/*
	 * remove_ip_address_from_posts_by_email()
	 */

	public function test_remove_ip_address_from_posts_by_email_with_no_posts() {
		$expected = array(
			'items_removed'  => 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		$this->assertEquals( $expected, c2c_PostAuthorIP::remove_ip_address_from_posts_by_email( 'user@example.com' ) );
	}

	public function test_remove_ip_address_from_posts_by_email_with_no_matching_user() {
		$user1_id = $this->factory->user->create( array( 'user_email' => 'user1@example.com' ) );
		$post1_id = $this->factory->post->create( array( 'post_status' => 'publish', 'post_author' => $user1_id ) );
		c2c_PostAuthorIP::set_post_author_ip( $post1_id, '127.0.0.121' );

		$expected = array(
			'items_removed'  => 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		$this->assertEquals( $expected, c2c_PostAuthorIP::remove_ip_address_from_posts_by_email( 'user@example.com' ) );
		$this->assertEquals( '127.0.0.121', c2c_PostAuthorIP::get_post_author_ip( $post1_id ) );
	}

	public function test_remove_ip_address_from_posts_by_email_with_user_but_no_matching_posts() {
		$user1_id = $this->factory->user->create( array( 'user_email' => 'user1@example.com' ) );
		$post1_id = $this->factory->post->create( array( 'post_status' => 'publish', 'post_author' => $user1_id ) );
		c2c_PostAuthorIP::set_post_author_ip( $post1_id, '127.0.0.121' );

		$user2_id = $this->factory->user->create( array( 'user_email' => 'user@example.com' ) );

		$expected = array(
			'items_removed'  => 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		$this->assertEquals( $expected, c2c_PostAuthorIP::remove_ip_address_from_posts_by_email( 'user@example.com' ) );
		$this->assertEquals( '127.0.0.121', c2c_PostAuthorIP::get_post_author_ip( $post1_id ) );
	}

	public function test_remove_ip_address_from_posts_by_email() {
		$user1_id = $this->factory->user->create( array( 'user_email' => 'user1@example.com' ) );
		$post1_id = $this->factory->post->create( array( 'post_status' => 'publish', 'post_author' => $user1_id ) );
		c2c_PostAuthorIP::set_post_author_ip( $post1_id, '127.0.0.121' );

		$user2_id = $this->factory->user->create( array( 'user_email' => 'user2@example.com' ) );
		$post2_id = $this->factory->post->create( array( 'post_status' => 'publish', 'post_author' => $user2_id ) );
		c2c_PostAuthorIP::set_post_author_ip( $post2_id, '127.0.0.125' );

		$post3_id = $this->factory->post->create( array( 'post_status' => 'publish', 'post_author' => $user1_id ) );
		c2c_PostAuthorIP::set_post_author_ip( $post3_id, '127.0.0.122' );

		$expected = array(
			'items_removed'  => 2,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		$this->assertEquals( $expected, c2c_PostAuthorIP::remove_ip_address_from_posts_by_email( 'user1@example.com' ) );

		$this->assertEmpty( c2c_PostAuthorIP::get_post_author_ip( $post1_id ) );
		$this->assertEquals( '127.0.0.125', c2c_PostAuthorIP::get_post_author_ip( $post2_id ) );
		$this->assertEmpty( c2c_PostAuthorIP::get_post_author_ip( $post3_id ) );
	}

	/*
	 * register_data_exporter()
	 */

	public function test_register_data_exporter() {
		$exporters = array(
			'example-plugin' => array(
				'exporter_friendly_name' => 'Example Plugin',
				'callback'               => '',
			),
		);

		$result = c2c_PostAuthorIP::register_data_exporter( $exporters );

		$this->assertArrayHasKey( 'post-author-ip', $result );
		$this->assertArrayHasKey( 'example-plugin', $result );
		$this->assertArrayHasKey( 'exporter_friendly_name', $result['post-author-ip'] );
		$this->assertEquals( 'Post Author IP Plugin', $result['post-author-ip']['exporter_friendly_name'] );
		$this->assertArrayHasKey( 'callback', $result['post-author-ip'] );
		$this->assertEquals( array( 'c2c_PostAuthorIP', 'export_user_data_by_email' ), $result['post-author-ip']['callback'] );
	}

	/*
	 * export_user_data_by_email()
	 */

	public function test_export_user_data_by_email() {
		$user1_id = $this->factory->user->create( array( 'user_email' => 'user1@example.com' ) );
		$post1_id = $this->factory->post->create( array( 'post_status' => 'publish', 'post_author' => $user1_id ) );
		c2c_PostAuthorIP::set_post_author_ip( $post1_id, '127.0.0.121' );

		$user2_id = $this->factory->user->create( array( 'user_email' => 'user2@example.com' ) );
		$post2_id = $this->factory->post->create( array( 'post_status' => 'publish', 'post_author' => $user2_id ) );
		c2c_PostAuthorIP::set_post_author_ip( $post2_id, '127.0.0.125' );

		$post3_id = $this->factory->post->create( array( 'post_status' => 'publish', 'post_author' => $user1_id ) );
		c2c_PostAuthorIP::set_post_author_ip( $post3_id, '127.0.0.121' );

		$expected = array(
			'data' => array(
				array(
					'group_id'  => 'posts',
					'item_id'   => 'post-' . $post1_id,
					'data'      => array(
						array(
							'name'  => 'Post Author IP',
							'value' => c2c_PostAuthorIP::get_post_author_ip( $post1_id ),
						),
					),
				),
				array(
					'group_id'  => 'posts',
					'item_id'   => 'post-' . $post3_id,
					'data'      => array(
						array(
							'name'  => 'Post Author IP',
							'value' => c2c_PostAuthorIP::get_post_author_ip( $post3_id ),
						),
					),
				),
			),
			'done' => true,
		);

		$this->assertEquals( $expected, c2c_PostAuthorIP::export_user_data_by_email( 'user1@example.com' ) );
	}

	/***
	 * ALL ADMIN AREA RELATED TESTS NEED TO FOLLOW THIS FUNCTION
	 *****/

	public function test_turn_on_admin() {
		if ( ! defined( 'WP_ADMIN' ) ) {
			define( 'WP_ADMIN', true );
		}

		$this->assertTrue( is_admin() );
	}

	/*
	 * add_privacy_policy_content()
	 */

	public function test_add_privacy_policy_content() {
		$this->test_turn_on_admin();

		// Not ideal, but it's a quick way to simulate 'admin_init' having been
		// fired so `wp_add_privacy_policy_content()` works.
		$GLOBALS['wp_actions']['admin_init'] = 1;

		c2c_PostAuthorIP::add_privacy_policy_content();

		$policy_text = WP_Privacy_Policy_Content::get_suggested_policy_text();
		$expected = <<<HTML
<h2 class="privacy-policy-tutorial">Post Author IP Plugin privacy policy content.</h2>
<p><strong class="privacy-policy-tutorial">Suggested Text:</strong> If you create a post on the site, your IP address at the time of the post's creation will be stored as post metadata.</p>

HTML;

		$this->assertNotEmpty( $policy_text );
		$this->assertEquals( 'Post Author IP Plugin', $policy_text[0]['plugin_name'] );
		$this->assertEquals( $expected, $policy_text[0]['policy_text'] );
	}

}
