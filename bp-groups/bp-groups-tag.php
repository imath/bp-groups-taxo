<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BP_Groups_Tag' ) ) :

class BP_Groups_Tag {
	/**
	 * Setup BP_Groups_Tag.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 *
	 * @uses buddypress() to get BuddyPress main instance.
	 * @static
	 */
	public static function start() {

		$bp = buddypress();

		if ( empty( $bp->groups->tag ) ) {
			$bp->groups->tag = new self;
		}

		return $bp->groups->tag;
	}

	/**
	 * Constructor.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function __construct() {
		$this->setup_globals();
		$this->setup_hooks();
	}

	/**
	 * Set globals.
	 *
	 * @access private
	 * @since BP Groups Taxo (1.0.0)
	 */
	private function setup_globals() {
		$this->css_url   = bp_groups_taxo_loader()->plugin_css;
		$this->term      = 0;
		$this->tax_query = array();
	}

	/**
	 * Set hooks.
	 *
	 * @access private
	 * @since BP Groups Taxo (1.0.0)
	 */
	private function setup_hooks() {
		// Get plugin params
		$params = bp_groups_taxo_loader()->params;

		// Actions
		add_action( 'bp_enqueue_scripts',                          array( $this, 'enqueue_cssjs'            )    );
		add_action( 'bp_actions',                                  array( $this, 'groups_directory'         ), 1 );
		add_action( 'bp_setup_theme_compat',                       array( $this, 'is_group_tag'             )    );
		add_action( $params['directory_hook'],                     array( $this, 'append_tags'              )    );
		add_action( $params['group_hook'],                         array( $this, 'append_tags'              )    );
		add_action( 'bp_before_directory_groups_content',          array( $this, 'tag_infos'                )    );
		add_action( 'bp_after_group_details_creation_step',        array( $this, 'tag_editor'               )    );
		add_action( 'bp_after_group_details_admin',                array( $this, 'tag_editor'               )    );
		add_action( 'groups_create_group_step_save_group-details', array( $this, 'set_group_tags'           )    );
		add_action( 'groups_group_details_edited',                 array( $this, 'set_group_tags'           ), 1 );
		add_action( 'groups_group_settings_edited',                array( $this, 'group_changed_visibility' ), 1 );
		add_action( 'groups_delete_group',                         array( $this, 'remove_relationships'     ), 1 );

		// Filters
		add_filter( 'bp_before_has_groups_parse_args',          array( $this, 'parse_groups_query'         ), 10, 1 );
		add_filter( 'bp_groups_get_paged_groups_sql',           array( $this, 'parse_select'               ), 10, 3 );
		add_filter( 'bp_groups_get_total_groups_sql',           array( $this, 'parse_total'                ), 10, 3 );
		add_filter( 'bp_get_total_group_count',                 array( $this, 'total_group_count'          ), 10, 1 );
		add_filter( 'bp_get_total_group_count_for_user',        array( $this, 'total_group_count_for_user' ), 10, 2 );
		add_filter( 'widget_tag_cloud_args',                    array( $this, 'tag_cloud_args'             ), 10, 1 );
		add_filter( 'bp_template_hierarchy_groups_single_item', array( $this, 'single_template_hierarchy'  ), 10, 1 );
	}

	/**
	 * Enqueue needed script/css
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function enqueue_cssjs() {
		$css_args = apply_filters( 'bp_groups_taxo_front_css', array(
			'handle'  => 'bp-groups-taxo-style',
			'src'     => $this->css_url . 'bp-groups-taxo.css',
			'deps'    => array( 'dashicons' ),
			'version' => bp_groups_taxo_loader()->version,
		) );

		// in case admin wants to neutralize plugin's style
		if ( empty( $css_args ) ) {
			return;
		}

		wp_enqueue_style( $css_args['handle'], $css_args['src'], $css_args['deps'], $css_args['version'] );
	}

	/**
	 * Handle Ajax requests
	 *
	 * Hook to bp_ajax_querystring to make sure the tag action
	 * is set.
	 *
	 * @param array|string $args Specify taxonomy term slug with group_term
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @since BP Groups Taxo (1.0.1) Support for group_term in query args
	 */
	public function parse_groups_query( $args = '' ) {
		$r = wp_parse_args( $args, array(
			'group_term'  => null,
		) );

		if ( ! empty( $r['group_term' ] ) ) {
			$slug = $r['group_term'];
		} else {
			// Filter Groups by Tag in the Groups Administration screen.
			if ( is_admin() ) {
				$current_screen = get_current_screen();
			}

			if ( ! empty( $current_screen->id ) && false !== strpos( $current_screen->id, 'bp-groups' ) && ! empty( $_GET['tag'] ) ) {
				$slug = $_GET['tag'];

			// Filter Groups by Tag in the Groups Directory.
			} elseif ( bp_is_groups_component() && bp_is_current_action( 'tag' ) ) {
				$slug = bp_action_variable( 0 );
			}
		}

		if ( ! empty( $slug ) ) {
			$this->term = BP_Groups_Terms::get_term_by( 'slug', $slug );
		}

		return $args;
	}

	/**
	 * Set the tag action to be a directory one
	 *
	 * BP Default & standalone themes.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function groups_directory() {
		if ( bp_is_groups_component() && bp_is_current_action( 'tag' ) ) {

			$this->term = BP_Groups_Terms::get_term_by( 'slug', bp_action_variable( 0 ) );

			if ( empty( $this->term ) ) {
				return;
			}

			bp_update_is_directory( true, 'groups' );

			do_action( 'groups_directory_groups_setup' );

			bp_core_load_template( apply_filters( 'groups_template_directory_groups', 'groups/index' ) );
		}
	}

	/**
	 * Set the tag action to be a directory one
	 *
	 * BP Theme Compat themes.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function is_group_tag() {
		global $wp_query;

		if ( empty( $this->term ) ) {
			return;
		}

		// Avoid WP_Query notices by resetting the queried object
		$wp_query->queried_object    = null;
		$wp_query->queried_object_id = 0;

		bp_update_is_directory( true, 'groups' );

		do_action( 'groups_directory_groups_setup' );

		add_action( 'bp_template_include_reset_dummy_post_data', array( $this, 'directory_dummy_post' ) );
		add_filter( 'bp_replace_the_content',                    array( $this, 'directory_content'    ) );
	}

	/**
	 * Build a WP_Tax_Query.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function get_sql_parts( $sql_parts = array() ) {
		if ( empty( $this->tax_query ) ) {
			$tax_query = new WP_Tax_Query( array(
				array(
					'taxonomy' => 'bp_group_tags',
					'terms'    => $this->term->term_id,
					'field'    => 'term_id',
				)
			) );

			$this->tax_query = $tax_query->get_sql( 'g', 'id' );
		}

		$where = $this->tax_query['where'];

		/**
		 * This can be the case when filtering alphabetically
		 * or by created date.
		 */
		if ( empty( $sql_parts['where'] ) ) {
			$where_parts = explode( 'AND', $where );
			
			if ( count( $where_parts ) > 1 ) {
				// Remove the first 'AND'
				array_shift( $where_parts );
			}

			$where = sprintf( 'WHERE %s', join( 'AND', $where_parts ) );
		} else {
			$where = sprintf( 'WHERE %1$s%2$s', $sql_parts['where'], $this->tax_query['where'] );
		}

		$sql_parts['from']  = sprintf( 'FROM %1$s%2$s', $sql_parts['from'], $this->tax_query['join'] );
		$sql_parts['where'] = $where;

		return $sql_parts;
	}

	/**
	 * Adapt the select query if needed.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function parse_select( $query = '', $sql_parts = array(), $args = array() ) {
		if ( ! empty( $this->term ) ) {
			$query = join( ' ', $this->get_sql_parts( $sql_parts ) );
		}

		return $query;
	}

	/**
	 * Adjust total sql query if needed.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function parse_total( $query = '', $sql_parts = array(), $args = array() ) {
		if ( ! empty( $this->term ) && ! empty( $this->tax_query ) ) {
			$sql = array_merge(
				array( 'select' => 'SELECT COUNT(DISTINCT g.id)' ),
				array_intersect_key( $this->get_sql_parts( $sql_parts ), array(
					'from' => true,
					'where' => true,
				) )
			);

			$query = join( ' ', $sql );
		}

		return $query;
	}

	/**
	 * Adjust Groups directory All Groups count
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function total_group_count( $count = 0 ) {
		if ( ! empty( $this->term->count ) ) {
			$count = absint( $this->term->count );
		}
		return $count;
	}

	/**
	 * Adjust Groups directory My Groups count
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function total_group_count_for_user( $count = 0, $user_id = 0 ) {
		if ( ! empty( $this->term ) && ! empty( $user_id ) && ! empty( $count ) ) {
			$user_groups = $this->get_user_groups( $user_id );

			if ( empty( $user_groups ) ) {
				return $count;
			}

			$current_tag_groups = BP_Groups_Terms::get_objects_in_term( $this->term->term_id );
			$count = count( array_intersect( $user_groups, $current_tag_groups ) );
		}
		return $count;
	}

	/**
	 * Return a user's groups
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function get_user_groups( $user_id = false ) {
		global $wpdb;
		$bp = buddypress();

		if ( empty( $user_id ) ) {
			return array();
		}

		$sql = array(
			'select' => "SELECT DISTINCT m.group_id FROM {$bp->groups->table_name_members} m, {$bp->groups->table_name} g",
			'where'  => array(
				'join'      => 'm.group_id = g.id',
				'user'      => $wpdb->prepare( 'm.user_id = %d', $user_id ),
				'confirmed' => 'm.is_confirmed = 1',
				'banned'    => 'm.is_banned = 0',
			)
		);

		$hide_hidden = ( ! is_super_admin() && $user_id != bp_loggedin_user_id() );

		if ( ! empty( $hide_hidden ) ) {
			$sql['where']['status'] = $wpdb->prepare( 'g.status != %s', 'hidden' );
		}

		$where = 'WHERE ' . join( ' AND ', $sql['where'] );
		$sql_col = $sql['select'] . ' ' . $where;

		return $wpdb->get_col( apply_filters( 'bp_groups_tags_get_user_groups_sql', $sql_col, $sql ) );
	}

	/**
	 * Copy BuddyPress BP_Groups_Theme_Compat->directory_dummy_post()
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function directory_dummy_post() {

		$title = apply_filters( 'bp_groups_directory_header', bp_get_directory_title( 'groups' ) );

		bp_theme_compat_reset_post( array(
			'ID'             => 0,
			'post_title'     => $title,
			'post_author'    => 0,
			'post_date'      => 0,
			'post_content'   => '',
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'is_page'        => true,
			'comment_status' => 'closed'
		) );
	}

	/**
	 * Copy BuddyPress BP_Groups_Theme_Compat->directory_content()
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function directory_content() {
		return bp_buffer_template_part( 'groups/index', null, false );
	}

	/**
	 * Append tags list to group entry
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function append_tags() {
		$group_id = bp_get_group_id();

		if ( empty( $group_id ) ) {
			return;
		}

		$tag_links = BP_Groups_Terms::get_the_term_list( $group_id, 'bp_group_tags', '<li>', '</li><li>', '</li>', bp_groups_taxo_loader()->params['taglink_description'] );

		if ( empty( $tag_links ) ) {
			return;
		}

		$tag_list  = '<ul class="group-tags">';
		$tag_list .= $tag_links;
		$tag_list .= '</ul>';

		echo apply_filters( 'bp_groups_taxo_append_tags', $tag_list, $group_id );
	}

	/**
	 * Prepend current tag info before groups loop
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function tag_infos() {
		if ( empty( $this->term ) ) {
			return;
		}

		$output  = '<div class="tag-infos">';
		$output .= '<h4>';
		$output .= sprintf( esc_html__( 'You are browsing Groups tagged : %s', 'bp-groups-taxo' ), $this->term->name ) ;
		$output .= '<a href="' . bp_get_groups_directory_permalink() . '" title="' . esc_html__( 'Show all Groups', 'bp-groups-taxo' ) . '" class="show-allgroups"></a>';
		$output .= '</h4>';

		if ( ! empty( $this->term->description ) ) {
			$output .= '<p>' . esc_html( $this->term->description ) . '</p>';
		}

		$output .= '</div>';

		echo apply_filters( 'bp_groups_taxo_tag_info', $output, $this->term );
	}

	/**
	 * Filter the tag cloud args
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function tag_cloud_args( $args = array() ) {
		if ( ! empty( $args['taxonomy'] ) && 'bp_group_tags' == $args['taxonomy'] ) {
			$args['topic_count_text_callback'] = array( $this, 'tag_cloud_title_callback' );
		}

		return $args;
	}

	/**
	 * Use "Group(s)" string instead of topic in the tag cloud
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function tag_cloud_title_callback( $count = 0 ) {
		return sprintf( _n( '%d Group', '%d Groups', $count, 'bp-groups-taxo' ), $count );
	}

	/**
	 * Build a form to choose tags for the current group
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 */
	public static function tag_editor( $group_id = 0 ) {
		if ( empty( $group_id ) ) {
			$group_id = bp_get_new_group_id() ? bp_get_new_group_id() : bp_get_current_group_id();
		}

		$group_terms = BP_Groups_Terms::get_object_terms( $group_id );
		$group_term_ids = array();

		if ( ! empty( $group_terms ) ) {
			$group_term_ids = wp_list_pluck( $group_terms, 'term_id' );
		}

		$terms = BP_Groups_Terms::get_terms( 'bp_group_tags', array(
			'hide_empty' => 0,
			'fields'     => 'id=>name'
		) );

		$output = '';

		if ( empty( $terms ) ) {
			// Display some feedbacks to admin so that they set tags more easily
			if ( is_admin() ) {
				$admin_tags_link = add_query_arg( 'page', 'bp-group-tags', bp_get_admin_url( 'admin.php' ) );
				?>
				<p>
					<?php printf(
						esc_html__( 'No tags have been set, you can define tags from the %s', 'bp-groups-taxo' ),
						'<a href="' . $admin_tags_link . '">' . esc_html__( 'Group Tags Administration', 'bp-groups-taxo' ) . '</a>'
					); ?>
				</p>
				<?php
			}

			return $output;
		}

		if ( ! is_admin() ) {
			$output =  '<label for="group-tags">' . esc_html__( 'Select the tag(s) of your choice.', 'bp-groups-taxo' ) . '</label>';
		}

		foreach ( $terms as $term_id => $term_name ) {
			$output .= '<span class="tag-cb"><input type="checkbox" name="_group_tags[]" value="' . $term_id . '" '. checked( in_array( $term_id, $group_term_ids ), true, false ) .'>' . esc_html( $term_name ) . '</span>';
		}

		if ( ! empty( $group_term_ids ) ) {
			$output .= '<input type="hidden" name="_group_previous_tags" value="' . implode( ',', $group_term_ids ) .'">';
		}

		if ( ! is_admin() ) {
			$output = bp_is_group_create() ? '<div class="inside">' . $output . '</div>' : '<p class="inside">' . $output . '</p>';
		}

		echo apply_filters( 'bp_groups_taxo_tag_editor', $output, $group_id, $group_term_ids, $terms );
	}

	/**
	 * Check if current user can manage tags
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 */
	public static function can_manage_tags() {
		$retval = false;

		if ( bp_is_group_create() && is_user_logged_in() ) {
			$retval = true;
		}

		if ( bp_is_item_admin() ) {
			$retval = true;
		}

		if ( bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		}

		return $retval;
	}

	/**
	 * Set group tags
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 */
	public static function set_group_tags( $group_id = 0 ) {
		if ( empty( $group_id ) ) {
			$group_id = bp_get_new_group_id() ? bp_get_new_group_id() : bp_get_current_group_id();
		}

		// A nicest way would be to use specific capabilities when registering term
		// and mapping those capabilities..
		if ( ! self::can_manage_tags() ) {
			return false;
		}

		$term_ids          = array();
		$previous_term_ids = array();

		if ( ! empty( $_POST['_group_tags'] ) ) {
			$term_ids = wp_parse_id_list( $_POST['_group_tags'] );
		}

		if ( ! empty( $_POST['_group_previous_tags'] ) ) {
			$previous_term_ids = wp_parse_id_list( $_POST['_group_previous_tags'] );
		}

		if ( empty( $term_ids ) && empty( $previous_term_ids ) ) {
			return false;
		}

		if ( empty( $term_ids ) && ! empty( $previous_term_ids ) ) {
			// Remove terms
			return BP_Groups_Terms::remove_object_terms( $group_id, $previous_term_ids );
		} else if ( $term_ids != $previous_term_ids ) {
			// Set terms
			return BP_Groups_Terms::set_object_terms( $group_id, $term_ids );
		}
	}

	/**
	 * Update term count if a group changed its visibility
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function group_changed_visibility( $group_id = 0 ) {
		if ( empty( $group_id ) ) {
			$group_id = bp_get_current_group_id();
		}

		// We need to update term count in case an hidden group changed its visibility and vice versa
		$group_terms = BP_Groups_Terms::get_object_terms( $group_id );
		$terms = wp_list_pluck( $group_terms, 'term_id');

		if ( ! empty( $terms ) ) {
			BP_Groups_Terms::update_term_count( $terms );
		}
	}

	/**
	 * Remove all group relationships
	 *
	 * In case a group is deleted.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function remove_relationships( $group_id = 0 ) {
		BP_Groups_Terms::delete_object_term_relationships( $group_id );
	}

	/**
	 * Add a entry to the template hierarchy for group tags
	 *
	 * @see https://codex.buddypress.org/themes/theme-compatibility-1-7/template-hierarchy/#single-group-pages
	 * for some explanations about BuddyPress Template Hierarchy
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function single_template_hierarchy( $templates = array() ) {
		$terms = BP_Groups_Terms::get_object_terms( bp_get_current_group_id(), 'bp_group_tags', array( 'orderby' => 'term_id' ) );

		if ( empty( $terms ) ) {
			return $templates;
		}

		$slugs = array_map( 'sanitize_file_name', wp_list_pluck( $terms, 'slug' ) );

		// The new template will take each tag in its name.
		$new_template = 'groups/single/index-tag-' . join( '-', $slugs ) . '.php';

		// Make sure index.php is at the last position
		$index = array_pop( $templates );

		return array_merge( $templates, array( $new_template, $index ) );
	}
}

endif;

add_action( 'bp_init', array( 'BP_Groups_Tag',   'start'  ), 12 );
