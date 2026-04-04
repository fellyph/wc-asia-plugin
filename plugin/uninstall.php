<?php
/**
 * Uninstall handler for WC Asia Demo.
 *
 * Removes all plugin options from the database.
 *
 * @package WC_Asia_Demo
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wc_asia_demo_api_key' );
delete_option( 'wc_asia_demo_greeting' );
delete_option( 'wc_asia_demo_version' );
