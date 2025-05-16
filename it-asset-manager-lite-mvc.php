<?php
/**
 * Plugin Name: Asset Manager MVC VErsio
 * Description: Custom post type for managing assets and repair requests with history tracking, custom fields, PDF export, and more.
 * Version: 1.10.0 // Updated version for new module
 * Author: Your Name
 * Text Domain: asset-manager
 * Domain Path: /languages
 * Requires PHP: 7.0
 * Requires at least: 5.2
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Define constants
define('ASSET_MANAGER_VERSION', '1.10.0'); // Updated version
define('ASSET_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ASSET_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ASSET_MANAGER_POST_TYPE', 'asset'); // Existing Asset Post Type
define('ASSET_MANAGER_TAXONOMY', 'asset_category'); // Existing Asset Taxonomy
define('ASSET_MANAGER_META_PREFIX', '_asset_manager_'); // Existing Meta Prefix

// Define constants for Repair Request module
define('ASSET_MANAGER_REPAIR_POST_TYPE', 'repair_request'); // New Repair Request Post Type
define('ASSET_MANAGER_REPAIR_TAXONOMY', 'repair_status'); // New Repair Status Taxonomy
define('ASSET_MANAGER_REPAIR_META_PREFIX', '_am_repair_'); // New Meta Prefix for Repair

// Include class files
require_once ASSET_MANAGER_PLUGIN_DIR . 'includes/class-asset-manager-loader.php';
require_once ASSET_MANAGER_PLUGIN_DIR . 'includes/class-asset-manager-post-types.php';
require_once ASSET_MANAGER_PLUGIN_DIR . 'includes/class-asset-manager-meta-fields.php';
require_once ASSET_MANAGER_PLUGIN_DIR . 'includes/class-asset-manager-admin.php';
require_once ASSET_MANAGER_PLUGIN_DIR . 'includes/class-asset-manager-callbacks.php';
require_once ASSET_MANAGER_PLUGIN_DIR . 'includes/class-asset-manager-assets.php';

// Include new class files for Repair Request module
require_once ASSET_MANAGER_PLUGIN_DIR . 'includes/class-asset-manager-repair-post-types.php';
require_once ASSET_MANAGER_PLUGIN_DIR . 'includes/class-asset-manager-repair-meta-fields.php';
// Note: Repair Admin and Repair Assets handling might be integrated into existing classes
// or require new ones depending on complexity.

/**
 * Initializes the plugin.
 *
 * Loads the plugin's main class and runs it.
 */
function asset_manager_run() {
    $plugin = new Asset_Manager_Loader();
    $plugin->run();
}
add_action('plugins_loaded', 'asset_manager_run');

/**
 * Activation hook.
 *
 * Runs on plugin activation.
 */
register_activation_hook(__FILE__, ['Asset_Manager_Loader', 'activate']);

/**
 * Deactivation hook.
 *
 * Runs on plugin deactivation.
 */
register_deactivation_hook(__FILE__, ['Asset_Manager_Loader', 'deactivate']);

