<?php
/**
 * Plugin Name:       Harry's WheelPros Importer
 * Plugin URI:        https://example.com/plugins/wheelpros-importer
 * Description:       Securely import WheelPros wheel data from CSV/JSON and display it as a custom post type.
 * Version:           1.11.1
 * Author:            Alex Macpherson | Indak Media
 * Author URI:        https://indakmedia.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       harrys-wheelpros
 *
 * This file is the main entry point for the plugin. It registers a custom post
 * type for wheels, loads supporting classes, hooks admin pages, and schedules
 * weekly imports. Sensitive data (such as SFTP credentials) is encrypted
 * before being stored in the WordPress options table. All admin-facing forms
 * are protected with nonces and capability checks to prevent unauthorized
 * access.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load the Plugin Update Checker library
require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Define plugin constants.
define( 'HP_WHEELPROS_PLUGIN_VERSION', '1.12.0' );
define( 'HP_WHEELPROS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HP_WHEELPROS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoload core and admin classes using PSR-4 style autoloader.
spl_autoload_register( function ( $class ) {
    // Only load our namespace classes.
    if ( 0 !== strpos( $class, 'HP_WheelPros_' ) ) {
        return;
    }
    $classname = strtolower( $class );
    $prefix    = 'hp_wheelpros_';
    if ( 0 === strpos( $classname, $prefix ) ) {
        // Normalize underscores to hyphens to match file names.
        $normalized = str_replace( '_', '-', $classname );
        // Determine directory: admin classes live in /admin/, others in /core/.
        $dir = ( false !== strpos( $normalized, 'admin' ) ) ? 'admin' : 'core';
        $file = HP_WHEELPROS_PLUGIN_DIR . $dir . '/class-' . $normalized . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
} );

// Load Brand Manager on both admin AND frontend (needed for filtering hidden brands)
$brand_manager_file = HP_WHEELPROS_PLUGIN_DIR . 'admin/class-hp-wheelpros-brand-manager.php';
if ( file_exists( $brand_manager_file ) ) {
    require_once $brand_manager_file;
}

// Load API diagnostics only in admin (not needed on frontend)
if ( is_admin() ) {
    $api_diagnostics_file = HP_WHEELPROS_PLUGIN_DIR . 'admin/class-hp-wheelpros-api-diagnostics.php';
    if ( file_exists( $api_diagnostics_file ) ) {
        require_once $api_diagnostics_file;
    }
}

/**
 * Activation hook – schedule weekly import cron.
 */
function hp_wheelpros_activate() {
    // Register the custom post type on activation so rewrite rules exist.
    HP_WheelPros_Core::register_cpt();
    HP_WheelPros_Core::register_taxonomies();

    // Create logs table.
    if ( class_exists( 'HP_WheelPros_Logger' ) ) {
        HP_WheelPros_Logger::install();
    }

    // Schedule weekly cron if not already scheduled.
    if ( ! wp_next_scheduled( 'hp_wheelpros_weekly_import' ) ) {
        // Weekly schedule. WordPress doesn't ship with a weekly schedule by default
        // so we'll add one when our plugin loads (see init hook below).
        wp_schedule_event( time(), 'weekly', 'hp_wheelpros_weekly_import' );
    }

    // Flush rewrite rules so custom post type permalinks work immediately.  Without
    // this, clicking on wheel links may redirect to the homepage until
    // permalinks are re‑saved.
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'hp_wheelpros_activate' );

/**
 * Deactivation hook – unschedule import cron.
 */
function hp_wheelpros_deactivate() {
    // Unschedule weekly event if scheduled.
    $timestamp = wp_next_scheduled( 'hp_wheelpros_weekly_import' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'hp_wheelpros_weekly_import' );
    }
}
register_deactivation_hook( __FILE__, 'hp_wheelpros_deactivate' );

/**
 * Initializes plugin components after WordPress loads.
 */
function hp_wheelpros_init() {
    // Register a weekly schedule if it doesn't exist.
    add_filter( 'cron_schedules', function ( $schedules ) {
        if ( ! isset( $schedules['weekly'] ) ) {
            $schedules['weekly'] = array(
                'interval' => WEEK_IN_SECONDS,
                'display'  => __( 'Once Weekly' )
            );
        }
        return $schedules;
    } );

    // Register the custom post type and taxonomy.
    HP_WheelPros_Core::register_cpt();
    HP_WheelPros_Core::register_taxonomies();

    // Register shortcodes for front-end display.
    if ( class_exists( 'HP_WheelPros_Shortcodes' ) ) {
        HP_WheelPros_Shortcodes::register();
    }

    // Initialize vehicle selector
    if ( class_exists( 'HP_WheelPros_Vehicle_Selector' ) ) {
        new HP_WheelPros_Vehicle_Selector();

        // Register vehicle selector shortcode
        add_shortcode( 'wheelpros_vehicle_search', 'hp_wheelpros_vehicle_search_shortcode' );
    }
}
add_action( 'init', 'hp_wheelpros_init' );

/**
 * Shortcode to display vehicle selector form
 *
 * Usage: [wheelpros_vehicle_search type="wheel" show_specs="true" callback="myCustomCallback"]
 */
function hp_wheelpros_vehicle_search_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'type' => '', // 'wheel' or 'tire'
        'show_specs' => false,
        'callback' => '',
    ), $atts, 'wheelpros_vehicle_search' );

    // Convert string boolean to actual boolean
    $atts['show_specs'] = filter_var( $atts['show_specs'], FILTER_VALIDATE_BOOLEAN );

    return HP_WheelPros_Vehicle_Selector::render_form( $atts );
}

/**
 * Load admin hooks only on the dashboard.
 */
function hp_wheelpros_admin_init() {
    if ( is_admin() ) {
        // Instantiate admin class to hook menus and forms.
        HP_WheelPros_Admin::get_instance();

        // Initialize admin columns for better wheel management
        if ( class_exists( 'HP_WheelPros_Admin_Columns' ) ) {
            HP_WheelPros_Admin_Columns::init();
        }

        // Note: Brand Manager self-initializes when its file is loaded (see line 58-62)
        // It's loaded on both frontend and admin for brand filtering to work everywhere

        // Initialize API Diagnostics - file is manually loaded above (admin only)
        if ( class_exists( 'HP_WheelPros_API_Diagnostics' ) ) {
            HP_WheelPros_API_Diagnostics::init();
        }
    }
}
add_action( 'plugins_loaded', 'hp_wheelpros_admin_init' );

/**
 * Cron event callback – fetches and imports data weekly.
 */
function hp_wheelpros_run_scheduled_import() {
    $importer = new HP_WheelPros_Importer();
    $importer->run_weekly_import();
}
add_action( 'hp_wheelpros_weekly_import', 'hp_wheelpros_run_scheduled_import' );

/**
 * Initialize the automatic update checker.
 */
function hp_wheelpros_init_update_checker() {
    // Use the version.json file from the version-file branch for more detailed update info
    $updateChecker = PucFactory::buildUpdateChecker(
        'https://raw.githubusercontent.com/MacphersonDesigns/wheelpros-hts/version-file/version.json',
        __FILE__,
        'harrys-wheelpros-importer'
    );

    // For private repositories, uncomment and add your GitHub token:
    // $updateChecker->setAuthentication('your_github_personal_access_token_here');
}// Initialize update checker after WordPress loads but before admin_init
add_action( 'init', 'hp_wheelpros_init_update_checker', 5 );
