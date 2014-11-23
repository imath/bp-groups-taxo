<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BP_Groups_Tag_Admin' ) ) :

class BP_Groups_Tag_Admin {
	/**
	 * Setup BP_Groups_Tag_Admin.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 *
	 * @uses buddypress() to get BuddyPress main instance.
	 * @static
	 */
	public static function start() {

		$bp = buddypress();

		if ( empty( $bp->groups->tagadmin ) ) {
			$bp->groups->tagadmin = new self;
		}

		return $bp->groups->tagadmin;
	}

	/**
	 * Constructor.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set hooks.
	 *
	 * @access private
	 * @since BP Groups Taxo (1.0.0)
	 */
	private function setup_hooks() {
		// Actions
		add_action( bp_core_admin_hook(),         array( $this, 'bp_groups_admin_submenu' ), 11    );
		add_action( 'current_screen',             array( $this, 'set_current_screen'      ), 10    );
		add_action( 'bp_admin_init',              array( $this, 'register_post_type'      ), 10    );

		add_action( 'bp_groups_admin_meta_boxes', array( $this, 'group_metabox'           ), 10    );
		add_action( 'bp_group_admin_edit_after',  array( $this, 'update_terms'            ), 10, 1 );
		add_action( 'bp_groups_admin_load',       array( $this, 'enqueue_css'             ), 10, 1 );

		// Filters
		add_filter( 'get_edit_term_link',                      array( $this, 'edit_term_link'  ), 10, 4 );
		add_filter( 'bp_group_tags_row_actions',               array( $this, 'tags_row_action' ), 10, 2 );

		add_filter( 'bp_groups_list_table_get_columns',        array( $this, 'add_tag_column'  ), 10, 1 );
		add_filter( 'bp_groups_admin_get_group_custom_column', array( $this, 'fill_tag_column' ), 10, 3 );
	}

	/**
	 * Enqueue style when editing a group.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function enqueue_css( $doaction = '' ) {
		if ( 'edit' == $doaction && ! empty( $_GET['gid'] ) ) {
			$bp_groups_taxo = bp_groups_taxo_loader();

			$css_args = apply_filters( 'bp_groups_taxo_admin_css', array(
				'handle'  => 'bp-groups-taxo-style',
				'src'     => $bp_groups_taxo->plugin_css . 'bp-groups-taxo.css',
				'deps'    => array( 'dashicons' ),
				'version' => $bp_groups_taxo->version,
			) );

			// in case admin wants to neutralize plugin's style
			if ( empty( $css_args ) ) {
				return;
			}

			wp_enqueue_style( $css_args['handle'], $css_args['src'], $css_args['deps'], $css_args['version'] );
		}
	}

	/**
	 * Add a submenu to Group administration.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function bp_groups_admin_submenu() {
		$this->admin_screen = add_submenu_page(
			'bp-groups',
			_x( 'Group Tags', 'admin page title', 'bp-groups-taxo' ),
			_x( 'Group Tags', 'admin menu title', 'bp-groups-taxo' ),
			'bp_moderate',
			'bp-group-tags',
			array( $this, 'admin_tags' )
		);

		add_action( "load-{$this->admin_screen}", array( $this, 'admin_tags_load' ) );

		if ( is_network_admin() ) {
			$this->admin_screen .= '-network';
		}
	}

	/**
	 * Make sure the BP Group Tags Screen includes the post type and taxonomy properties
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 *
	 * @param WP_Screen $current_screen
	 */
	function set_current_screen( $current_screen = OBJECT ) {

		if ( empty( $this->admin_screen ) || $this->admin_screen != $current_screen->id ) {
			return;
		}

		$current_screen->post_type = 'bp_group';
		$current_screen->taxonomy  = 'bp_group_tags';
	}

	/**
	 * Register a fake post type for the groups component
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 *
	 * @global $wp_post_types the list of available post types
	 */
	function register_post_type() {
		global $wp_post_types;

		$post_type = 'bp_group';

		// Set needed properties
		$wp_post_types[ $post_type ]               = new stdClass;
		$wp_post_types[ $post_type ]->show_ui      = false;
		$wp_post_types[ $post_type ]->labels       = new stdClass;
		$wp_post_types[ $post_type ]->labels->name = __( 'Groups', 'bp-groups-taxo' );
		$wp_post_types[ $post_type ]->name         = $post_type;
	}

	/**
	 * Register the Group tags updated messages
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 *
	 * @param  array  $messages list of term updated messages
	 * @return array            same list including Group tags ones
	 */
	function admin_updated_message( $messages = array() ) {
		$messages['bp_group_tags'] = array(
			0 => '', // Unused. Messages start at index 1.
			1 => __( 'Group Tag added.',       'bp-groups-taxo' ),
			2 => __( 'Group Tag deleted.',     'bp-groups-taxo' ),
			3 => __( 'Group Tag updated.',     'bp-groups-taxo' ),
			4 => __( 'Group Tag not added.',   'bp-groups-taxo' ),
			5 => __( 'Group Tag not updated.', 'bp-groups-taxo' ),
			6 => __( 'Group Tags deleted.',    'bp-groups-taxo' ),
		);

		return $messages;
	}

	/**
	 * Get the admin current ation
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	function current_action() {
		$action = ! empty( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';

		// If the bottom is set, let it override the action
		if ( ! empty( $_REQUEST['action2'] ) && $_REQUEST['action2'] != "-1" ) {
			$action = $_REQUEST['action2'];
		}

		return $action;
	}

	/**
	 * Set up the Group Tags admin page.
	 *
	 * On multisite configs, the edit-tags.php page does not exist
	 * This function will include it to output the Group tags management forms
	 * But we still need to deal with actions before including this file. Some
	 * of this works thanks to the javascript part.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 *
	 * @global $wp_post_types
	 */
	public function admin_tags_load() {
		$cheating = __( 'Cheatin&#8217; uh?', 'bp-groups-taxo' );

		if ( ! bp_current_user_can( 'bp_moderate' ) ) {
			wp_die( $cheating );
		}

		$post_type = 'bp_group';
		$taxnow    = $taxonomy = 'bp_group_tags';

		$redirect_to = add_query_arg( 'page', 'bp-group-tags', bp_get_admin_url( 'admin.php' ) );

		// Filter the updated messages
		add_filter( 'term_updated_messages', array( $this, 'admin_updated_message' ), 10, 1 );

		$doaction = $this->current_action();

		/**
		 * Eventually deal with actions before including the edit-tags.php file
		 */
		if ( ! empty( $doaction ) ) {
			$bp_group_tags_tax = get_taxonomy( $taxonomy );

			if ( ! $bp_group_tags_tax ) {
				wp_die( __( 'Invalid taxonomy', 'bp-groups-taxo' ) );
			}

			switch ( $doaction ) {
				case 'add-tag':

					check_admin_referer( 'add-tag', '_wpnonce_add-tag' );

					if ( ! bp_current_user_can( $bp_group_tags_tax->cap->edit_terms ) ) {
						wp_die( $cheating );
					}

					$inserted = BP_Groups_Terms::insert_term( $_POST['tag-name'], $bp_group_tags_tax->name, $_POST );

					if ( ! empty( $inserted ) && ! is_wp_error( $inserted ) ){
						$redirect_to = add_query_arg( 'message', 1, $redirect_to );
					} else {
						$redirect_to = add_query_arg( 'message', 4, $redirect_to );
					}
					wp_redirect( $redirect_to );
				exit;

				case 'delete':
				case 'bulk-delete':
					$tag_IDs = array();
					$query_args = array();

					if ( empty( $_REQUEST['tag_ID'] ) && empty( $_REQUEST['delete_tags'] ) ) {
						wp_redirect( $redirect_to );
						exit;
					} else if ( ! empty( $_REQUEST['tag_ID'] ) ) {
						$tag_ID = absint( $_REQUEST['tag_ID'] );
						check_admin_referer( 'delete-tag_' . $tag_ID );
						$tag_IDs = array( $tag_ID );
						$query_args['message'] = 2;
					} else {
						check_admin_referer( 'bulk-tags' );
						$tag_IDs = wp_parse_id_list( $_REQUEST['delete_tags'] );
						$query_args['message'] = 6;
					}

					if ( ! bp_current_user_can( $bp_group_tags_tax->cap->delete_terms ) ) {
						wp_die( $cheating );
					}

					foreach ( $tag_IDs as $tag_ID ) {
						BP_Groups_Terms::delete_term( $tag_ID, $bp_group_tags_tax->name );
					}

					$redirect_to = add_query_arg( $query_args, $redirect_to );
					wp_redirect( $redirect_to );
				exit;

				case 'edit':
					// We need to reset the action of the edit form
					wp_enqueue_script( 'bp_groups_tag_admin_js', bp_groups_taxo_loader()->plugin_js . 'admin.js', array( 'jquery' ), bp_groups_taxo_loader()->version, true );
					wp_localize_script( 'bp_groups_tag_admin_js', 'BP_Groups_Tag_Admin', array(
						'edit_action' => $redirect_to,
					) );
					break;

				case 'editedtag':
					$tag_ID = (int) $_POST['tag_ID'];
					check_admin_referer( 'update-tag_' . $tag_ID );

					if ( ! bp_current_user_can( $bp_group_tags_tax->cap->edit_terms ) )
						wp_die( $cheating );

					$tag = BP_Groups_Terms::get_term( $tag_ID, $bp_group_tags_tax->name );
					if ( ! $tag ) {
						wp_die( __( 'You attempted to edit an item that doesn&#8217;t exist. Perhaps it was deleted?', 'bp-groups-taxo' ) );
					}

					$ret = BP_Groups_Terms::update_term( $tag_ID, $bp_group_tags_tax->name, $_POST );

					if ( ! empty( $ret ) && ! is_wp_error( $ret ) ) {
						$redirect_to = add_query_arg( 'message', 3, $redirect_to );
					} else {
						$redirect_to = add_query_arg( 'message', 5, $redirect_to );
					}

					wp_redirect( $redirect_to );
				exit;
			}

		/**
		 * Make sure to "javascript change" some form attributes
		 * in edit-tags.php
		 */
		} else {
			wp_enqueue_script( 'bp_groups_tag_admin_js', bp_groups_taxo_loader()->plugin_js . 'admin.js', array( 'jquery' ), bp_groups_taxo_loader()->version, true );
			wp_localize_script( 'bp_groups_tag_admin_js', 'BP_Groups_Tag_Admin', array(
				'edit_action' => $redirect_to,
				'ajax_screen' => 'edit-' . $taxonomy,
				'search_page' => 'bp-group-tags',
			) );
		}


		require_once( ABSPATH . 'wp-admin/edit-tags.php' );
		exit();
	}

	/**
	 * Not used, everything is done in BP_Groups_Tag_Admin->admin_tags_load()
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function admin_tags() {}

	/**
	 * Adds a column to list tags
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function add_tag_column( $columns ) {
		return array_merge( $columns, array( 'tag' => __( 'Group Tags', 'bp-groups-taxo' ) ) );
	}

	/**
	 * Fill the tag column
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function fill_tag_column( $content, $column, $group ) {
		if( 'tag' != $column || empty( $group['id'] ) ) {
			return;
		}
		echo BP_Groups_Terms::get_the_term_list( $group['id'], 'bp_group_tags', '', ', ', '' );
	}

	/**
	 * Add a meta box to set tags for a group from the single
	 * group admin
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function group_metabox() {
		add_meta_box(
			'bp_group_tags',
			_x( 'Manage Tags', 'group tags admin edit screen', 'bp-groups-taxo' ),
			array( $this, 'display_metabox' ),
			get_current_screen()->id,
			'side',
			'core'
		);
	}

	/**
	 * Display the single group meta box
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 *
	 * @uses BP_Groups_Tag::tag_editor() to output the tag editor
	 */
	public function display_metabox( $item = false ) {
		if ( empty( $item->id ) ) {
			return;
		}

		BP_Groups_Tag::tag_editor( $item->id );
	}

	/**
	 * Update the tags of a group from the single group
	 * Administration
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 *
	 * @uses BP_Groups_Tag::set_group_tags() to output the tag editor
	 */
	public function update_terms( $group_id = 0 ) {
		if ( empty( $group_id ) ) {
			return;
		}

		BP_Groups_Tag::set_group_tags( $group_id );
	}

	/**
	 * Make sure the edit term link for the group tags
	 * will point to our custom edit-tags administration
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function edit_term_link( $link = '', $term_id = 0, $taxonomy = '', $object_type = '' ) {
		if ( empty( $taxonomy ) || 'bp_group_tags' != $taxonomy ) {
			return $link;
		}

		$query_args = array(
			'page'   => 'bp-group-tags',
			'action' => 'edit',
			'tag_ID' => $term_id,
		);

		return add_query_arg( $query_args, bp_get_admin_url( 'admin.php' ) );

	}

	/**
	 * Make sure the delete term link for the group tags
	 * will point to our custom edit-tags administration
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function tags_row_action( $actions = array(), $tag = null ) {
		if ( empty( $tag ) ) {
			return $actions;
		}

		// Only the delete action is to edit.
		$query_args = array(
			'page'     => 'bp-group-tags',
			'action'   => 'delete',
			'tag_ID'   => $tag->term_id,
			'taxonomy' => 'bp_group_tags'
		);
		$delete_link = add_query_arg( $query_args, bp_get_admin_url( 'admin.php' ) );
		$actions['delete'] = "<a class='delete-tag' href='" . esc_url( wp_nonce_url( $delete_link, 'delete-tag_' . $tag->term_id ) ) . "'>" . esc_html( _x( 'Delete', 'Group Tags term delete link', 'bp-groups-taxo' ) ) . "</a>";

		return $actions;
	}
}

endif;

add_action( 'bp_init', array( 'BP_Groups_Tag_Admin', 'start' ), 14 );
