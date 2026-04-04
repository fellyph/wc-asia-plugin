<?php
/**
 * Plugin Name: WC Asia Demo
 * Description: A demo plugin to demonstrate a testing pipeline with WordPress Playground.
 * Version: 1.0.0
 * Author: Fellyph Cintra
 * Text Domain: wc-asia-demo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register plugin settings.
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
}
add_action( 'admin_init', 'wc_asia_demo_register_settings' );

/**
 * Add settings page under Settings menu.
 */
function wc_asia_demo_add_settings_page() {
	add_options_page(
		'WC Asia Demo Settings',
		'WC Asia Demo',
		'manage_options',
		'wc-asia-demo',
		'wc_asia_demo_render_settings_page'
	);
}
add_action( 'admin_menu', 'wc_asia_demo_add_settings_page' );

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
			?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="wc_asia_demo_api_key">API Key</label>
					</th>
					<td>
						<input
							type="text"
							id="wc_asia_demo_api_key"
							name="wc_asia_demo_api_key"
							value="<?php echo esc_attr( get_option( 'wc_asia_demo_api_key', '' ) ); ?>"
							class="regular-text"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wc_asia_demo_greeting">Greeting Message</label>
					</th>
					<td>
						<input
							type="text"
							id="wc_asia_demo_greeting"
							name="wc_asia_demo_greeting"
							value="<?php echo esc_attr( get_option( 'wc_asia_demo_greeting', 'Hello, WC Asia!' ) ); ?>"
							class="regular-text"
						/>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save Changes' ); ?>
		</form>
	</div>
	<?php
}

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
add_action( 'rest_api_init', 'wc_asia_demo_register_rest_routes' );

/**
 * REST API callback for the greeting endpoint.
 */
function wc_asia_demo_rest_greeting() {
	$greeting = get_option( 'wc_asia_demo_greeting', 'Hello, WC Asia!' );

	return rest_ensure_response( array(
		'greeting' => $greeting,
		'plugin'   => 'wc-asia-demo',
		'version'  => '1.0.0',
	) );
}

/**
 * Register the [wc_asia_greeting] shortcode.
 */
function wc_asia_demo_greeting_shortcode( $atts ) {
	$greeting = get_option( 'wc_asia_demo_greeting', 'Hello, WC Asia!' );

	return sprintf(
		'<div data-testid="wc-asia-greeting" class="wc-asia-greeting">%s</div>',
		esc_html( $greeting )
	);
}
add_shortcode( 'wc_asia_greeting', 'wc_asia_demo_greeting_shortcode' );
