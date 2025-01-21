<?php
/**
 * Core plugin initialization.
 */

namespace WheelProsHTS;

// Hook into plugin activation and deactivation.
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate_plugin' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate_plugin' );

/**
 * Run during plugin activation.
 */
function activate_plugin() {
    // Perform tasks like database table creation if needed.
    flush_rewrite_rules();
}

/**
 * Run during plugin deactivation.
 */
function deactivate_plugin() {
    // Cleanup tasks if needed.
    flush_rewrite_rules();
}

// Initialize the plugin.
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init_plugin' );

/**
 * Initialize the plugin.
 */
function init_plugin() {
    load_plugin_textdomain( WHEELPROS_HTS_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    // Additional initialization logic here.
}
