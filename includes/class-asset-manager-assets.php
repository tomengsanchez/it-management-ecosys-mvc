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
        global $post_type, $pagenow; // $post_type might be null on some screens

        // Determine if we are on an Asset Manager related screen
        $is_asset_cpt_edit_screen = ($pagenow === 'post.php' || $pagenow === 'post-new.php') && 
                                    ( (isset($_GET['post_type']) && $_GET['post_type'] === ASSET_MANAGER_POST_TYPE) || ($post_type === ASSET_MANAGER_POST_TYPE) );
        
        $is_asset_cpt_list_screen = ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === ASSET_MANAGER_POST_TYPE);
        
        $is_asset_dashboard_screen = ($hook === 'asset_page_asset-dashboard' || $hook === ASSET_MANAGER_POST_TYPE . '_page_asset-dashboard'); // Check both possible hook names
        $is_asset_export_screen = ($hook === 'asset_page_asset-export' || $hook === ASSET_MANAGER_POST_TYPE . '_page_asset-export');

        // Enqueue admin CSS for all relevant Asset Manager screens
        if ($is_asset_cpt_edit_screen || $is_asset_cpt_list_screen || $is_asset_dashboard_screen || $is_asset_export_screen) {
            wp_enqueue_style(
                'asset-manager-admin-css',
                ASSET_MANAGER_PLUGIN_URL . 'css/asset-manager.css',
                [],
                ASSET_MANAGER_VERSION
            );
        }

        // Enqueue media uploader scripts for asset image upload on edit/new asset screens
        if ($is_asset_cpt_edit_screen) {
            wp_enqueue_media(); // WordPress media uploader
            wp_enqueue_script(
                'asset-manager-admin-js',
                ASSET_MANAGER_PLUGIN_URL . 'js/asset-manager-admin.js',
                ['jquery'],
                ASSET_MANAGER_VERSION,
                true // In footer
            );
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
            // Need to get an instance of Asset_Manager_Admin or make get_dashboard_data_for_script static or accessible
            $admin_handler = new Asset_Manager_Admin(); // This is not ideal to instantiate here.
                                                        // Consider passing data via a filter or making the method static if it doesn't rely on instance state.
                                                        // For this refactor, we'll keep it simple but note this as an area for improvement.
            wp_localize_script(
                'asset-dashboard-js',
                'assetDashboardData', // Object name in JavaScript
                $admin_handler->get_dashboard_data_for_script()
            );
        }
    }
}
