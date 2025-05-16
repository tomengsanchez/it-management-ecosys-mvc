<?php
/**
 * Asset Manager Repair Post Types.
 *
 * Handles registration of Custom Post Type and Taxonomy for Repair Requests.
 *
 * @package AssetManager
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Asset_Manager_Repair_Post_Types {

    /**
     * Registers WordPress hooks.
     */
    public function register_hooks() {
        add_action('init', [$this, 'register_repair_request_post_type']);
        add_action('init', [$this, 'register_repair_status_taxonomy']);
    }

    /**
     * Registers the 'repair_request' custom post type.
     */
    public function register_repair_request_post_type() {
        $labels = [
            'name'                  => _x('Repair Requests', 'post type general name', 'asset-manager'),
            'singular_name'         => _x('Repair Request', 'post type singular name', 'asset-manager'),
            'menu_name'             => _x('Repair Requests', 'admin menu', 'asset-manager'),
            'name_admin_bar'        => _x('Repair Request', 'add new on admin bar', 'asset-manager'),
            'add_new'               => _x('Add New', 'repair_request', 'asset-manager'),
            'add_new_item'          => __('Add New Repair Request', 'asset-manager'),
            'new_item'              => __('New Repair Request', 'asset-manager'),
            'edit_item'             => __('Edit Repair Request', 'asset-manager'),
            'view_item'             => __('View Repair Request', 'asset-manager'),
            'all_items'             => __('All Repair Requests', 'asset-manager'),
            'search_items'          => __('Search Repair Requests', 'asset-manager'),
            'parent_item_colon'     => __('Parent Repair Requests:', 'asset-manager'),
            'not_found'             => __('No repair requests found.', 'asset-manager'),
            'not_found_in_trash'    => __('No repair requests found in Trash.', 'asset-manager'),
            'attributes'            => __('Repair Request Attributes', 'asset-manager'),
            'filter_items_list'     => __('Filter repair requests list', 'asset-manager'),
            'items_list_navigation' => __('Repair requests list navigation', 'asset-manager'),
            'items_list'            => __('Repair requests list', 'asset-manager'),
        ];
        $args = [
            'labels'             => $labels,
            'public'             => false, // Not publicly queryable on the frontend
            'show_ui'            => true,
            'show_in_menu'       => true, // Show under the main menu
            'menu_position'      => 25, // Position below Assets
            'query_var'          => true,
            'rewrite'            => ['slug' => 'repair-request'], // Custom slug for repair requests
            'capability_type'    => 'post', // Use standard post capabilities
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => ['title'], // We'll use meta fields for details
            'menu_icon'          => 'dashicons-hammer', // Repair icon
            'show_in_rest'       => true, // Enable Gutenberg and REST API
        ];
        register_post_type('repair_request', $args); // Register the post type
    }

    /**
     * Registers the 'repair_status' custom taxonomy.
     * This will be used for the status dropdown in the meta box.
     */
    public function register_repair_status_taxonomy() {
        $labels = [
            'name'              => _x('Repair Statuses', 'taxonomy general name', 'asset-manager'),
            'singular_name'     => _x('Repair Status', 'taxonomy singular name', 'asset-manager'),
            'search_items'      => __('Search Repair Statuses', 'asset-manager'),
            'all_items'         => __('All Repair Statuses', 'asset-manager'),
            'parent_item'       => __('Parent Repair Status', 'asset-manager'),
            'parent_item_colon' => __('Parent Repair Status:', 'asset-manager'),
            'edit_item'         => __('Edit Repair Status', 'asset-manager'),
            'update_item'       => __('Update Repair Status', 'asset-manager'),
            'add_new_item'      => __('Add New Repair Status', 'asset-manager'),
            'new_item_name'     => __('New Repair Status Name', 'asset-manager'),
            'menu_name'         => __('Statuses', 'asset-manager'),
        ];
        $args = [
            'hierarchical'      => false, // Statuses are not hierarchical
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'repair-status'],
            'show_in_rest'      => true, // Enable REST API support
        ];
        register_taxonomy('repair_status', 'repair_request', $args); // Register the taxonomy for repair_request
    }
}
