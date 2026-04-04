<?php
/**
 * Plugin Name: WC Asia Demo
 * Plugin URI:  https://github.com/fellyph/wc-asia-plugin
 * Description: A demo plugin to demonstrate a testing pipeline with WordPress Playground.
 * Version:     1.0.0
 * Author:      Fellyph Cintra
 * Author URI:  https://developer.developer.developer.developer.developer
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-asia-demo
 * Domain Path: /languages
 * Requires at least: 6.3
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WC_ASIA_DEMO_VERSION', '1.0.0' );
define( 'WC_ASIA_DEMO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_ASIA_DEMO_PLUGIN_FILE', __FILE__ );

/**
 * Bootstrap the plugin — all hooks registered from a single entry point.
 */
function wc_asia_demo_init() {
	load_plugin_textdomain( 'wc-asia-demo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Front-end hooks.
	add_shortcode( 'wc_asia_greeting', 'wc_asia_demo_greeting_shortcode' );
	add_action( 'rest_api_init', 'wc_asia_demo_register_rest_routes' );

	// Admin-only hooks.
	if ( is_admin() ) {
		add_action( 'admin_init', 'wc_asia_demo_register_settings' );
		add_action( 'admin_menu', 'wc_asia_demo_add_settings_page' );
		add_action( 'wp_dashboard_setup', 'wc_asia_demo_register_dashboard_widget' );
		add_action( 'admin_enqueue_scripts', 'wc_asia_demo_enqueue_dashboard_styles' );
	}
}
add_action( 'plugins_loaded', 'wc_asia_demo_init' );

/*
|--------------------------------------------------------------------------
| Activation / Deactivation
|--------------------------------------------------------------------------
*/

register_activation_hook( __FILE__, 'wc_asia_demo_activate' );
register_deactivation_hook( __FILE__, 'wc_asia_demo_deactivate' );

/**
 * Run on plugin activation.
 */
function wc_asia_demo_activate() {
	// Store the plugin version for future upgrade routines.
	add_option( 'wc_asia_demo_version', WC_ASIA_DEMO_VERSION );
}

/**
 * Run on plugin deactivation.
 */
function wc_asia_demo_deactivate() {
	// Nothing to clean up on deactivation — data is removed on uninstall.
}

/*
|--------------------------------------------------------------------------
| Settings API
|--------------------------------------------------------------------------
*/

/**
 * Register plugin settings, section, and fields.
 */
function wc_asia_demo_register_settings() {
	register_setting( 'wc_asia_demo_settings', 'wc_asia_demo_api_key', array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => '',
	) );

	register_setting( 'wc_asia_demo_settings', 'wc_asia_demo_greeting', array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => 'Hello, WC Asia!',
	) );

	add_settings_section(
		'wc_asia_demo_general',
		__( 'General Settings', 'wc-asia-demo' ),
		'__return_null',
		'wc-asia-demo'
	);

	add_settings_field(
		'wc_asia_demo_api_key',
		__( 'API Key', 'wc-asia-demo' ),
		'wc_asia_demo_render_api_key_field',
		'wc-asia-demo',
		'wc_asia_demo_general',
		array( 'label_for' => 'wc_asia_demo_api_key' )
	);

	add_settings_field(
		'wc_asia_demo_greeting',
		__( 'Greeting Message', 'wc-asia-demo' ),
		'wc_asia_demo_render_greeting_field',
		'wc-asia-demo',
		'wc_asia_demo_general',
		array( 'label_for' => 'wc_asia_demo_greeting' )
	);
}

/**
 * Render the API Key field.
 *
 * @param array $args Field arguments from add_settings_field().
 */
function wc_asia_demo_render_api_key_field( $args ) {
	$value = get_option( 'wc_asia_demo_api_key', '' );
	printf(
		'<input type="text" id="%s" name="wc_asia_demo_api_key" value="%s" class="regular-text" />',
		esc_attr( $args['label_for'] ),
		esc_attr( $value )
	);
}

/**
 * Render the Greeting Message field.
 *
 * @param array $args Field arguments from add_settings_field().
 */
function wc_asia_demo_render_greeting_field( $args ) {
	$value = get_option( 'wc_asia_demo_greeting', 'Hello, WC Asia!' );
	printf(
		'<input type="text" id="%s" name="wc_asia_demo_greeting" value="%s" class="regular-text" />',
		esc_attr( $args['label_for'] ),
		esc_attr( $value )
	);
}

/**
 * Add settings page under the Settings menu.
 */
function wc_asia_demo_add_settings_page() {
	add_options_page(
		__( 'WC Asia Demo Settings', 'wc-asia-demo' ),
		__( 'WC Asia Demo', 'wc-asia-demo' ),
		'manage_options',
		'wc-asia-demo',
		'wc_asia_demo_render_settings_page'
	);
}

/**
 * Render the settings page.
 */
function wc_asia_demo_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	settings_errors();
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'wc_asia_demo_settings' );
			do_settings_sections( 'wc-asia-demo' );
			submit_button( esc_html__( 'Save Changes', 'wc-asia-demo' ) );
			?>
		</form>
	</div>
	<?php
}

/*
|--------------------------------------------------------------------------
| Dashboard Widget
|--------------------------------------------------------------------------
*/

/**
 * Register the LED greeting dashboard widget.
 */
function wc_asia_demo_register_dashboard_widget() {
	wp_add_dashboard_widget(
		'wc_asia_demo_greeting_widget',
		__( 'WC Asia Greeting', 'wc-asia-demo' ),
		'wc_asia_demo_render_dashboard_widget'
	);
}

/**
 * Render the LED-style greeting on the dashboard.
 */
function wc_asia_demo_render_dashboard_widget() {
	$greeting = get_option( 'wc_asia_demo_greeting', 'Hello, WC Asia!' );
	printf(
		'<div class="wc-asia-led-display">%s</div>',
		esc_html( $greeting )
	);
}

/**
 * Enqueue inline CSS for the retro LED display on the dashboard.
 *
 * @param string $hook The current admin page hook.
 */
function wc_asia_demo_enqueue_dashboard_styles( $hook ) {
	if ( 'index.php' !== $hook ) {
		return;
	}

	$css = '
		#wc_asia_demo_greeting_widget .inside {
			padding: 0;
			margin: 0;
		}
		.wc-asia-led-display {
			background: #111;
			border-radius: 12px;
			padding: 24px 32px;
			font-family: "Courier New", Courier, monospace;
			font-size: 48px;
			font-weight: bold;
			color: #39ff14;
			text-transform: uppercase;
			letter-spacing: 4px;
			text-shadow:
				0 0 7px #39ff14,
				0 0 10px #39ff14,
				0 0 21px #39ff14,
				0 0 42px #0fa,
				0 0 82px #0fa;
			text-align: center;
			overflow: hidden;
			white-space: nowrap;
			text-overflow: ellipsis;
		}
	';

	wp_register_style( 'wc-asia-demo-dashboard', false );
	wp_enqueue_style( 'wc-asia-demo-dashboard' );
	wp_add_inline_style( 'wc-asia-demo-dashboard', $css );
}

/*
|--------------------------------------------------------------------------
| REST API
|--------------------------------------------------------------------------
*/

/**
 * Register custom REST API endpoint.
 */
function wc_asia_demo_register_rest_routes() {
	register_rest_route( 'wc-asia-demo/v1', '/greeting', array(
		'methods'             => 'GET',
		'callback'            => 'wc_asia_demo_rest_greeting',
		'permission_callback' => '__return_true',
	) );
}

/**
 * REST API callback for the greeting endpoint.
 *
 * @return WP_REST_Response
 */
function wc_asia_demo_rest_greeting() {
	$greeting = get_option( 'wc_asia_demo_greeting', 'Hello, WC Asia!' );

	return rest_ensure_response( array(
		'greeting' => $greeting,
		'plugin'   => 'wc-asia-demo',
		'version'  => WC_ASIA_DEMO_VERSION,
	) );
}

/*
|--------------------------------------------------------------------------
| Shortcode
|--------------------------------------------------------------------------
*/

/**
 * Render the [wc_asia_greeting] shortcode.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string HTML output.
 */
function wc_asia_demo_greeting_shortcode( $atts ) {
	$greeting = get_option( 'wc_asia_demo_greeting', 'Hello, WC Asia!' );

	return sprintf(
		'<div data-testid="wc-asia-greeting" class="wc-asia-greeting">%s</div>',
		esc_html( $greeting )
	);
}
