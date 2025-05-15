<?php
/**
 * Asset Manager Loader.
 *
 * Orchestrates the plugin's components.
 *
 * @package AssetManager
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Asset_Manager_Loader {

    /**
     * Stores instances of handler classes.
     * @var array
     */
    private $handlers = [];

    /**
     * Constructor.
     *
     * Initializes the plugin components and registers hooks.
     */
    public function __construct() {
        $this->load_dependencies();
        $this->initialize_handlers();
    }

    /**
     * Load plugin's text domain.
     */
    private function load_textdomain() {
        load_plugin_textdomain(
            'asset-manager',
            false,
            dirname(plugin_basename(ASSET_MANAGER_PLUGIN_DIR)) . '/languages/'
        );
    }

    /**
     * Include required files.
     *
     * This is mostly handled by the main plugin file's includes.
     * This method is a placeholder if more complex dependency loading is needed later.
     */
    private function load_dependencies() {
        // Dependencies are already included in the main plugin file.
    }

    /**
     * Instantiate handler classes.
     */
    private function initialize_handlers() {
        $this->handlers['post_types'] = new Asset_Manager_Post_Types();
        $this->handlers['meta_fields'] = new Asset_Manager_Meta_Fields();
        $this->handlers['admin'] = new Asset_Manager_Admin();
        $this->handlers['callbacks'] = new Asset_Manager_Callbacks();
        $this->handlers['assets'] = new Asset_Manager_Assets();
    }

    /**
     * Run the loader.
     *
     * Loads text domain and registers hooks for all components.
     */
    public function run() {
        $this->load_textdomain();

        foreach ($this->handlers as $handler) {
            if (method_exists($handler, 'register_hooks')) {
                $handler->register_hooks();
            }
        }
        
        // Placeholder for shortcodes if they were to be implemented
        // add_action('init', [$this, 'register_shortcodes']);
    }

    /**
     * Plugin activation logic.
     *
     * Flushes rewrite rules after post types and taxonomies are registered.
     */
    public static function activate() {
        // Ensure post types and taxonomies are registered before flushing
        $post_types_handler = new Asset_Manager_Post_Types();
        $post_types_handler->register_asset_post_type();
        $post_types_handler->register_asset_taxonomy();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation logic.
     *
     * Placeholder for any deactivation tasks.
     */
    public static function deactivate() {
        // e.g., flush_rewrite_rules(); or clean up options
    }
    
    /**
     * Placeholder for shortcode registration.
     */
    public function register_shortcodes() {
        // Example: add_shortcode('my_asset_shortcode', [$this->handlers['shortcodes_handler'], 'render_shortcode']);
    }
}
