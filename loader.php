<?php
/**
 * BP Groups Taxo uses WordPress built-in taxonomy to add tags to BuddyPress groups.
 *
 *
 * @package   BP Groups Taxo
 * @author    imath
 * @license   GPL-2.0+
 * @link      http://imathi.eu
 *
 * @buddypress-plugin
 * Plugin Name:       BP Groups Taxo
 * Plugin URI:        http://imathi.eu/2014/06/02/bp-groups-taxo/
 * Description:       Use WordPress built-in taxonomy to add tags to BuddyPress groups
 * Version:           1.0.0-beta2
 * Author:            imath
 * Author URI:        http://imathi.eu
 * Text Domain:       bp-groups-taxo
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages/
 * GitHub Plugin URI: https://github.com/imath/bp-groups-taxo
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BP_Groups_Taxo_Loader' ) ) :
/**
 * BP Groups Taxo Loader Class
 *
 * @since BP Groups Taxo (1.0.0)
 */
class BP_Groups_Taxo_Loader {
	/**
	 * Instance of this class.
	 *
	 * @package BP Groups Taxo
	 * @since   BP Groups Taxo (1.0.0)
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Required BuddyPress version
	 *
	 * @package BP Groups Taxo
	 * @since   BP Groups Taxo (1.0.0)
	 *
	 * @var      string
	 */
	public static $bp_version_required = '2.0';

	/**
	 * Version which fixed the ticket (#4017)
	 *
	 * @package BP Groups Taxo
	 * @since   BP Groups Taxo (1.0.0)
	 * @see https://buddypress.trac.wordpress.org/ticket/4017
	 *
	 * @var      string
	 */
	public static $bp_version_fixed = '';

	/**
	 * Some params to customize the plugin
	 *
	 * @package BP Groups Taxo
	 * @since   BP Groups Taxo (1.0.0)
	 *
	 * @var      array
	 */
	public $params;

	/**
	 * Initialize the plugin
	 *
	 * @package BP Groups Taxo
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function __construct() {
		$this->setup_globals();
		$this->setup_hooks();
	}

	/**
	 * Return an instance of this class.
	 *
	 * @package BP Groups Taxo
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 *
	 * @return object A single instance of this class.
	 * @static
	 */
	public static function start() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Sets some globals for the plugin
	 *
	 * @package BP Groups Taxo
	 * @access private
	 * @since BP Groups Taxo (1.0.0)
	 */
	private function setup_globals() {
		/** BP Groups Taxo globals ********************************************/
		$this->version                = '1.0.0-beta2';
		$this->domain                 = 'bp-groups-taxo';
		$this->file                   = __FILE__;
		$this->basename               = plugin_basename( $this->file );
		$this->plugin_dir             = plugin_dir_path( $this->file );
		$this->plugin_url             = plugin_dir_url( $this->file );
		$this->lang_dir               = trailingslashit( $this->plugin_dir . 'languages' );
		$this->includes_dir           = trailingslashit( $this->plugin_dir . 'bp-groups' );
		$this->includes_url           = trailingslashit( $this->plugin_url . 'bp-groups' );
		$this->plugin_js              = trailingslashit( $this->includes_url . 'js'  );
		$this->plugin_css             = trailingslashit( $this->includes_url . 'css' );
		$this->params                 = $this->set_params();

		/** BuddyPress & BP Groups Taxo configs **********************************/
		$this->config = $this->network_check();
	}

	/**
	 * Checks BuddyPress version
	 *
	 * @package BP Groups Taxo
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function version_check() {
		// taking no risk
		if ( ! defined( 'BP_VERSION' ) ) {
			return false;
		}

		$return = version_compare( BP_VERSION, self::$bp_version_required, '>=' );

		if ( ! empty( self::$bp_version_fixed ) && version_compare( BP_VERSION, self::$bp_version_fixed, '>=' ) ) {
			$return = false;
		}

		return $return;
	}

	/**
	 * Checks if current blog is the one where BuddyPress is activated
	 *
	 * @package BP Groups Taxo
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function root_blog_check() {

		if ( ! function_exists( 'bp_get_root_blog_id' ) )
			return false;

		if ( get_current_blog_id() != bp_get_root_blog_id() )
			return false;

		return true;
	}

	/**
	 * Checks if current blog is the one where BuddyPress is activated
	 *
	 * @package BP Groups Taxo
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function network_check() {
		/*
		 * network_active : BP Groups Taxo is activated on the network
		 * network_status : BuddyPress & BP Groups Taxo share the same network status
		 */
		$config = array( 'network_active' => false, 'network_status' => true );
		$network_plugins = get_site_option( 'active_sitewide_plugins', array() );

		// No Network plugins
		if ( empty( $network_plugins ) )
			return $config;

		$check = array( buddypress()->basename, $this->basename );
		$network_active = array_diff( $check, array_keys( $network_plugins ) );

		if ( count( $network_active ) == 1 )
			$config['network_status'] = false;

		$config['network_active'] = isset( $network_plugins[ $this->basename ] );

		return $config;
	}

	/**
	 * Includes the needed file
	 *
	 * @package BP Groups Taxo
	 * @access public
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function includes() {
		if ( bp_is_active( 'groups' ) ) {
			require( $this->includes_dir . 'bp-groups-taxonomy.php' );
			require( $this->includes_dir . 'bp-groups-tag.php' );

			if ( is_admin() ) {
				require( $this->includes_dir . 'bp-groups-admin.php' );
			}
		}
	}

	/**
	 * Sets the key hooks to add an action or a filter to
	 *
	 * @package BP Groups Taxo
	 * @access private
	 * @since BP Groups Taxo (1.0.0)
	 */
	private function setup_hooks() {
		// BP Groups Taxo && BuddyPress share the same config & BuddyPress version is ok
		if ( $this->version_check() && $this->root_blog_check() && $this->config['network_status'] ) {

			// Actions
			add_action( 'bp_include', array( $this, 'includes'           ), 10 );
			add_action( 'bp_init',    array( $this, 'register_taxonomy'  ), 10 );

			// Filters
			add_filter( 'groups_forbidden_names', array( $this, 'restrict_slug' ), 1, 1 );

		} else {
			add_action( $this->config['network_active'] ? 'network_admin_notices' : 'admin_notices', array( $this, 'admin_warning' ) );
		}

		// loads the languages..
		add_action( 'bp_init', array( $this, 'load_textdomain' ), 5 );

	}

	/**
	 * Regiter the taxonomy
	 *
	 * @package BP Groups Taxo
	 * @access private
	 * @since BP Groups Taxo (1.0.0)
	 */
	public function register_taxonomy() {
		if ( ! bp_is_root_blog() || ! bp_is_active( 'groups' ) ) {
			return;
		}

		$labels = array(
			'name'              => _x( 'Group Tags', 'taxonomy general name', 'bp-groups-taxo' ),
			'singular_name'     => _x( 'Group Tag', 'taxonomy singular name', 'bp-groups-taxo' ),
		);

		$bp = buddypress();
		$group_slug = bp_get_groups_slug();

		if ( ! empty( $bp->pages->groups->slug ) ) {
			$group_slug = $bp->pages->groups->slug;
		}

		$args = array(
			'hierarchical'          => false,
			'labels'                => $labels,
			'show_ui'               => false,
			'show_admin_column'     => false,
			'query_var'             => false,
			'show_tagcloud'         => true,
			'rewrite'               => array( 'slug' => $group_slug . '/tag', 'with_front' => false ),
			'update_count_callback' => array( 'BP_Groups_Terms', 'update_term_count' ),
		);

		register_taxonomy( 'bp_group_tags', array( 'bp_group' ), $args );
	}

	/**
	 * Allow people to edit some params
	 * 
	 * @package BP Groups Taxo
	 * @since 1.0.0
	 * 
	 * @uses apply_filters() call 'bp_group_tags_params' to override defaults with customs
	 * @uses bp_parse_args()
	 */ 
	public function set_params() {
		$customs = apply_filters( 'bp_group_tags_params', array() );

		return bp_parse_args( $customs, array(
			'taglink_description' => 0, // 0 no description in the title attribute, else number of words to keep
			'directory_hook'      => 'bp_directory_groups_item',
			'group_hook'          => 'bp_before_group_header_meta',
		) );
	}

	/**
	 * Make sure the "tag" slug will not be use by a group
	 *
	 * @package BP Groups Taxo
	 * @since 1.0.0
	 */
	public function restrict_slug( $groups_forbidden_names = array() ) {
		$groups_forbidden_names[] = 'tag';
		return $groups_forbidden_names;
	}

	/**
	 * Display a message to admin in case config is not as expected
	 *
	 * @package BP Groups Taxo
	 * @since 1.0.0
	 */
	public function admin_warning() {
		$warnings = array();

		if ( ! $this->version_check() ) {
			$warnings[] = sprintf( __( 'BP Groups Taxo requires at least version %s of BuddyPress.', 'bp-groups-taxo' ), self::$bp_version_required );
		}

		if ( ! bp_core_do_network_admin() && ! $this->root_blog_check() ) {
			$warnings[] = __( 'BP Groups Taxo requires to be activated on the blog where BuddyPress is activated.', 'bp-groups-taxo' );
		}

		if ( bp_core_do_network_admin() && ! is_plugin_active_for_network( $this->basename ) ) {
			$warnings[] = __( 'BP Groups Taxo and BuddyPress need to share the same network configuration.', 'bp-groups-taxo' );
		}

		if ( ! empty( $warnings ) ) :
		?>
		<div id="message" class="error">
			<?php foreach ( $warnings as $warning ) : ?>
				<p><?php echo esc_html( $warning ) ; ?>
			<?php endforeach ; ?>
		</div>
		<?php
		endif;
	}

	/**
	 * Loads the translation files
	 *
	 * @package BP Groups Taxo
	 * @since 1.0.0
	 *
	 * @uses get_locale() to get the language of WordPress config
	 * @uses load_texdomain() to load the translation if any is available for the language
	 */
	public function load_textdomain() {
		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		// Setup paths to current locale file
		$mofile_local  = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/bp-groups-taxo/' . $mofile;

		// Look in global /wp-content/languages/bp-groups-taxo folder
		load_textdomain( $this->domain, $mofile_global );

		// Look in local /wp-content/plugins/bp-groups-taxo/languages/ folder
		load_textdomain( $this->domain, $mofile_local );

		// Look in global /wp-content/languages/plugins/
		load_plugin_textdomain( $this->domain );
	}

}
endif;

// Let's start !
function bp_groups_taxo_loader() {
	return BP_Groups_Taxo_Loader::start();
}
add_action( 'bp_loaded', 'bp_groups_taxo_loader', 1 );
