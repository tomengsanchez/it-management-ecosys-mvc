<?php
/**
 * Asset Manager Assets.
 *
 * Handles enqueueing of scripts and styles.
 *
 * @package AssetManager
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Asset_Manager_Assets {

    /**
     * Registers WordPress hooks.
     */
    public function register_hooks() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts_and_styles']);
    }

    /**
     * Enqueues scripts and styles for the admin area.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_scripts_and_styles($hook) {
        global $post_type, $pagenow; 

        // Determine if we are on an Asset Manager related screen
        $is_asset_cpt_edit_screen = ($pagenow === 'post.php' || $pagenow === 'post-new.php') && 
                                    ( (isset($_GET['post_type']) && $_GET['post_type'] === ASSET_MANAGER_POST_TYPE) || ($post_type === ASSET_MANAGER_POST_TYPE) );
        
        $is_asset_cpt_list_screen = ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === ASSET_MANAGER_POST_TYPE);
        
        $is_asset_dashboard_screen = ($hook === 'asset_page_asset-dashboard' || $hook === ASSET_MANAGER_POST_TYPE . '_page_asset-dashboard'); 
        $is_asset_export_screen = ($hook === 'asset_page_asset-export' || $hook === ASSET_MANAGER_POST_TYPE . '_page_asset-export');

        // Enqueue admin CSS for all relevant Asset Manager screens
        if ($is_asset_cpt_edit_screen || $is_asset_cpt_list_screen || $is_asset_dashboard_screen || $is_asset_export_screen) {
            wp_enqueue_style(
                'asset-manager-admin-css', // Main CSS handle
                ASSET_MANAGER_PLUGIN_URL . 'css/asset-manager.css',
                [],
                ASSET_MANAGER_VERSION
            );
        }

        // Enqueue media uploader scripts and specific CSS for asset image upload on edit/new asset screens
        if ($is_asset_cpt_edit_screen) {
            wp_enqueue_media(); // WordPress media uploader
            wp_enqueue_script(
                'asset-manager-admin-js', // JS for asset admin edit screens
                ASSET_MANAGER_PLUGIN_URL . 'js/asset-manager-admin.js', // Ensure this file exists if you plan to add JS here
                ['jquery'],
                ASSET_MANAGER_VERSION,
                true // In footer
            );

            // Add inline style to hide the title field container on asset add/edit screens
            // The #titlewrap div contains the title label and input field.
            $custom_css_to_hide_title = "#titlewrap { display: none !important; }";
            // Attach this inline style to your existing stylesheet's handle.
            // It's important that 'asset-manager-admin-css' is enqueued on this screen for this to work.
            wp_add_inline_style('asset-manager-admin-css', $custom_css_to_hide_title);
        }
        
        // Enqueue scripts for the Asset Dashboard
        if ($is_asset_dashboard_screen) {
            wp_enqueue_script(
                'chart-js', // Handle for Chart.js
                'https://cdn.jsdelivr.net/npm/chart.js',
                [],          // Dependencies
                '4.4.1',     // Version of Chart.js
                true         // In footer
            );
            wp_enqueue_script(
                'asset-dashboard-js',
                ASSET_MANAGER_PLUGIN_URL . 'js/asset-dashboard.js',
                ['jquery', 'chart-js'], // Depends on jQuery and Chart.js
                ASSET_MANAGER_VERSION,
                true // In footer
            );

            // Localize script with data for the dashboard
            if (class_exists('Asset_Manager_Admin')) { // Ensure class exists before instantiating
                $admin_handler = new Asset_Manager_Admin(); 
                wp_localize_script(
                    'asset-dashboard-js',
                    'assetDashboardData', // Object name in JavaScript
                    $admin_handler->get_dashboard_data_for_script()
                );
            }
        }
    }
}
