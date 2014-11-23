<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BP_Groups_Terms' ) ) :

class BP_Groups_Terms {

	public static $wp_term_taxonomy = '';
	public static $wp_term_relationships = '';
	public static $wp_terms = '';
	public static $bp_term_taxonomy = '';
	public static $bp_term_relationships = '';
	public static $bp_terms = '';

	/**
	 * Start the class
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 */
	public static function start() {
		$bp = buddypress();

		if ( empty( $bp->groups->terms ) ) {
			$bp->groups->terms = new self;
		}

		return $bp->groups->terms;
	}

	/**
	 * Constructor
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function __construct() {
		$this->setup_globals();
	}

	/**
	 * Set globals
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function setup_globals() {
		global $wpdb;

		self::$wp_term_taxonomy      = $wpdb->term_taxonomy;
		self::$wp_term_relationships = $wpdb->term_relationships;
		self::$wp_terms              = $wpdb->terms;

		$bp_prefix = bp_core_get_table_prefix();

		self::$bp_term_taxonomy      = $bp_prefix . 'term_taxonomy';
		self::$bp_term_relationships = $bp_prefix . 'term_relationships';
		self::$bp_terms              = $bp_prefix . 'terms';
	}

	/**
	 * Set needed $wpdb->tables to be the one of root blog id
	 *
	 * This is needed for Multisite configs in case a groups tag
	 * loop is build from a child blog.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 */
	public static function set_tables() {
		global $wpdb;

		$wpdb->term_taxonomy      = self::$bp_term_taxonomy;
		$wpdb->term_relationships = self::$bp_term_relationships;
		$wpdb->terms              = self::$bp_terms;
	}

	/**
	 * Reset $wpdb->tables to the one set by WordPress
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 */
	public static function reset_tables() {
		global $wpdb;

		$wpdb->term_taxonomy      = self::$wp_term_taxonomy;
		$wpdb->term_relationships = self::$wp_term_relationships;
		$wpdb->terms              = self::$wp_terms;
	}

	/**
	 * Update term count
	 * Hidden groups mustn't be in the taxonomy count
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 */
	public static function update_term_count( $terms, $taxonomy = 'bp_group_tags' ) {
		global $wpdb;
		$bp = buddypress();

		self::set_tables();

		if ( ! is_object( $taxonomy ) ) {
			$taxonomy = get_taxonomy( $taxonomy );
		}

		if ( empty( $taxonomy ) ) {
			return;
		}

		$object_types = (array) $taxonomy->object_type;

		if ( false === array_search( 'bp_group', $object_types ) ) {
			_update_generic_term_count( $terms, $taxonomy );
		} else {
			$other_type = array_diff( $object_types, array( 'bp_group' ) );

			$sql_get = array(
				'select' => "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr, {$bp->groups->table_name} g",
				'where' => array(
					'join'   => 'g.id = tr.object_id',
					'status' => $wpdb->prepare( 'g.status != %s', 'hidden' ),
				)
			);

			// Update term count for group tags
			foreach ( (array) $terms as $term ) {
				$count = 0;

				$sql_get['where']['term_taxo_id'] = $wpdb->prepare(  'term_taxonomy_id = %d', $term );

				$count += (int) $wpdb->get_var( $sql_get['select'] . ' WHERE ' . join( ' AND ', $sql_get['where'] ) );

				$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
			}

			// If somebody use this taxonomy, he'll have to handle the term count
			if ( ! empty( $other_type ) ) {
				do_action( 'bp_groups_taxo_terms_update_count', $terms, $taxonomy );
			}
		}

		self::reset_tables();
	}

	/**
	 * Get group tags
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 *
	 * @uses wp_get_object_terms()
	 */
	public static function get_object_terms( $object_ids, $taxonomies = 'bp_group_tags', $args = array() ) {
		global $wpdb;
		self::set_tables();
		$return = wp_get_object_terms( $object_ids, $taxonomies, $args );
		self::reset_tables();
		return $return;
	}

	/**
	 * Set group tags
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 *
	 * @uses wp_set_object_terms()
	 */
	public static function set_object_terms( $object_id, $terms, $taxonomy = 'bp_group_tags', $append = false ) {
		global $wpdb;
		self::set_tables();
		$return = wp_set_object_terms( $object_id, $terms, $taxonomy, $append );
		self::reset_tables();
		return $return;
	}

	/**
	 * Insert a term based on arguments provided.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 *
	 * @uses wp_insert_term()
	 */
	public static function insert_term( $term, $taxonomy, $args = array() ) {
		self::set_tables();
		$return = wp_insert_term( $term, $taxonomy, $args );
		self::reset_tables();
		return $return;
	}

	/**
	 * Update term based on arguments provided.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 *
	 * @uses wp_update_term()
	 */
	public static function update_term( $term_id, $taxonomy, $args = array() ) {
		self::set_tables();
		$return = wp_update_term( $term_id, $taxonomy, $args );
		self::reset_tables();
		return $return;
	}

	/**
	 * Remove group tags
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 *
	 * @uses wp_remove_object_terms()
	 */
	public static function remove_object_terms( $object_id, $terms, $taxonomy = 'bp_group_tags' ) {
		global $wpdb;
		self::set_tables();
		$return = wp_remove_object_terms( $object_id, $terms, $taxonomy );
		self::reset_tables();
		return $return;
	}

	/**
	 * Remove all group relationships
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 *
	 * @uses wp_delete_object_term_relationships()
	 */
	public static function delete_object_term_relationships( $object_id, $taxonomies = 'bp_group_tags' ) {
		global $wpdb;
		self::set_tables();
		$return = wp_delete_object_term_relationships( $object_id, $taxonomies );
		self::reset_tables();
		return $return;
	}

	/**
	 * Delete term based on arguments provided.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 *
	 * @uses wp_delete_term()
	 */
	public static function delete_term( $term_id, $taxonomy, $args = array() ) {
		self::set_tables();
		$return = wp_delete_term( $term_id, $taxonomy, $args );
		self::reset_tables();
		return $return;
	}

	/**
	 * Get group ids for a given tag
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 *
	 * @uses get_objects_in_term()
	 */
	public static function get_objects_in_term( $term_ids, $taxonomies = 'bp_group_tags', $args = array() ) {
		global $wpdb;
		self::set_tables();
		$return = get_objects_in_term( $term_ids, $taxonomies, $args );
		self::reset_tables();
		return $return;
	}

	/**
	 * Get all Term data from database by Term ID
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 *
	 * @uses get_term()
	 */
	public static function get_term( $term, $taxonomy, $output = OBJECT, $filter = 'raw' ) {
		self::set_tables();
		$return = get_term( $term, $taxonomy, $output, $filter );
		self::reset_tables();
		return $return;
	}

	/**
	 * Get all terms for a given taxonomy
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 *
	 * @uses get_terms()
	 */
	public static function get_terms( $taxonomies = 'bp_group_tags', $args = '' ) {
		global $wpdb;
		self::set_tables();
		$return = get_terms( $taxonomies, $args );
		self::reset_tables();
		return $return;
	}

	/**
	 * Get term thanks to a specific field
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 *
	 * @uses get_term_by()
	 */
	public static function get_term_by( $field, $value, $taxonomy = 'bp_group_tags', $output = OBJECT, $filter = 'raw' ) {
		global $wpdb;
		self::set_tables();
		$return = get_term_by( $field, $value, $taxonomy, $output, $filter = 'raw' );
		self::reset_tables();
		return $return;
	}

	/**
	 * Get the term link
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 *
	 * @uses get_term_link()
	 */
	public static function get_term_link( $term, $taxonomy = 'bp_group_tags' ) {
		global $wpdb;
		self::set_tables();
		$return = get_term_link( $term, $taxonomy );
		self::reset_tables();
		return $return;
	}

	/**
	 * Copy WordPress get_the_term_list without using get_the_terms()
	 * function as it checks for an existing post.
	 *
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 * @static
	 *
	 * @uses self::get_object_terms()
	 * @uses self::get_term_link()
	 */
	public static function get_the_term_list( $group_id, $taxonomy = 'bp_group_tags', $before = '', $sep = '', $after = '' ) {

		$terms = self::get_object_terms( $group_id, $taxonomy );

		if ( is_wp_error( $terms ) )
			return $terms;

		if ( empty( $terms ) )
			return false;

		foreach ( $terms as $term ) {
			$link = self::get_term_link( $term, $taxonomy );
			if ( is_wp_error( $link ) )
				return $link;
			$term_links[] = '<a href="' . esc_url( $link ) . '" rel="tag">' . $term->name . '</a>';
		}

		$term_links = apply_filters( "term_links-$taxonomy", $term_links );

		return $before . join( $sep, $term_links ) . $after;
	}
}

endif;

add_action( 'bp_init', array( 'BP_Groups_Terms', 'start'  ), 11 );

