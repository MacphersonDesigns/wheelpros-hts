<?php
/**
 * Plugin Name:       WheelPros Integration for HTS
 * Plugin URI:        https://www.indakmedia.com
 * Description:       Integration with WheelPros APIs for vehicle/product search and inventory display.
 * Version:           0.0.0
 * Author:            Alex Macpherson
 * Author URI:        https://www.indakmedia.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wheelpros-hts
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants for the plugin.
define( 'WHEELPROS_HTS_VERSION', '0.0.0' );
define( 'WHEELPROS_HTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WHEELPROS_HTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WHEELPROS_HTS_TEXT_DOMAIN', 'wheelpros-hts' );

// Autoloader for namespace-based class loading (optional for later).
spl_autoload_register(function ($class) {
    if (strpos($class, 'WheelProsHTS\\') === 0) {
        $file = WHEELPROS_HTS_PLUGIN_DIR . 'core/' . str_replace('\\', '/', substr($class, strlen('WheelProsHTS\\'))) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

// Include core plugin files.
require_once WHEELPROS_HTS_PLUGIN_DIR . 'core/plugin-init.php';
require_once plugin_dir_path(__FILE__) . 'lib/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v4\PucFactory;

// Initialize the update checker.
$updateChecker = PucFactory::buildUpdateChecker(
    'https://your-hosted-version-file.json', // URL to the version info JSON file.
    __FILE__,                                // Path to the plugin's main file.
    'wheelpros-hts'                          // Plugin slug.
);
