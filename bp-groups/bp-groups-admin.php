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
		add_action( 'bp_groups_admin_meta_boxes', array( $this, 'group_metabox'           ), 10    );
		add_action( 'bp_group_admin_edit_after',  array( $this, 'update_terms'            ), 10, 1 );
		add_action( 'bp_groups_admin_load',       array( $this, 'enqueue_css'             ), 10, 1 );

		// Filters
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
		add_submenu_page( 
			'bp-groups', 
			_x( 'Group Tags', 'admin page title', 'bp-groups-taxo' ), 
			_x( 'Group Tags', 'admin menu title', 'bp-groups-taxo' ), 
			'bp_moderate', 
			'edit-tags.php?taxonomy=bp_group_tags', 
			'' 
		);
		add_action( 'load-edit-tags.php',       array( $this, 'admin_load' ) );
		add_action( 'admin_head-edit-tags.php', array( $this, 'menu_highlight') );
	}

	/**
	 * Fake a post type and add it to post type global.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * 
	 * @global $wp_post_types
	 */
	public function admin_load() {
		global $wp_post_types;

		if ( empty( $_GET['taxonomy'] ) ) {
			return;
		}

		if ( $_GET['taxonomy'] == 'bp_group_tags' ) {
			get_current_screen()->post_type = 'bp_group';
			$bp_group_post_type_labels = new stdClass;
			$bp_group_post_type_labels->name = __( 'Groups', 'bp-groups-taxo' );

			$bp_group_post_type = (object) array(
				'labels' => $bp_group_post_type_labels,
				'show_ui' => false,
			);

			$wp_post_types = array_merge( $wp_post_types, array( 
				'bp_group' => $bp_group_post_type
			) );
		}
	}

	/**
	 * Forces BP Groups menu to be parent file.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * 
	 * @global $parent_file
	 */
	public function menu_highlight(){
		global $parent_file;

		if ( empty( $_GET['taxonomy'] ) ) {
			return;
		}

		if ( $_GET['taxonomy'] == 'bp_group_tags' ) {
			$parent_file = 'bp-groups';
		}
	}

	/**
	 * Adds a column to list tags
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * 
	 */
	public function add_tag_column( $columns ) {
		return array_merge( $columns, array( 'tag' => __( 'Group Tags', 'bp-groups-taxo' ) ) );
	}

	/**
	 * Fill the tag column
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * 
	 */
	public function fill_tag_column( $content, $column, $group ) {
		if( 'tag' != $column || empty( $group['id'] ) ) {
			return;
		}
		echo BP_Groups_Terms::get_the_term_list( $group['id'], 'bp_group_tags', '', ', ', '' );
	}

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

	public function display_metabox( $item = false ) {
		if ( empty( $item->id ) ) {
			return;
		}

		BP_Groups_Tag::tag_editor( $item->id );
	}

	public function update_terms( $group_id = 0 ) {
		if ( empty( $group_id ) ) {
			return;
		}
		
		BP_Groups_Tag::set_group_tags( $group_id );
	}
}

endif;

add_action( 'bp_init', array( 'BP_Groups_Tag_Admin', 'start' ), 14 );
