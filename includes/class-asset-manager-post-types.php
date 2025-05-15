<?php
/**
 * Asset Manager Post Types.
 *
 * Handles registration of Custom Post Type and Taxonomy.
 *
 * @package AssetManager
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Asset_Manager_Post_Types {

    /**
     * Registers WordPress hooks.
     */
    public function register_hooks() {
        add_action('init', [$this, 'register_asset_post_type']);
        add_action('init', [$this, 'register_asset_taxonomy']);
    }

    /**
     * Registers the 'asset' custom post type.
     */
    public function register_asset_post_type() {
        $labels = [
            'name'                  => _x('Assets', 'post type general name', 'asset-manager'),
            'singular_name'         => _x('Asset', 'post type singular name', 'asset-manager'),
            'menu_name'             => _x('Assets', 'admin menu', 'asset-manager'),
            'name_admin_bar'        => _x('Asset', 'add new on admin bar', 'asset-manager'),
            'add_new'               => _x('Add New', 'asset', 'asset-manager'),
            'add_new_item'          => __('Add New Asset', 'asset-manager'),
            'new_item'              => __('New Asset', 'asset-manager'),
            'edit_item'             => __('Edit Asset', 'asset-manager'),
            'view_item'             => __('View Asset', 'asset-manager'),
            'all_items'             => __('All Assets', 'asset-manager'),
            'search_items'          => __('Search Assets', 'asset-manager'),
            'parent_item_colon'     => __('Parent Assets:', 'asset-manager'),
            'not_found'             => __('No assets found.', 'asset-manager'),
            'not_found_in_trash'    => __('No assets found in Trash.', 'asset-manager'),
            'attributes'            => __('Asset Attributes', 'asset-manager'),
            'filter_items_list'     => __('Filter assets list', 'asset-manager'),
            'items_list_navigation' => __('Assets list navigation', 'asset-manager'),
            'items_list'            => __('Assets list', 'asset-manager'),
        ];
        $args = [
            'labels'             => $labels,
            'public'             => false, // Not publicly queryable on the frontend unless explicitly set
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => ASSET_MANAGER_POST_TYPE],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'supports'           => ['title'], // Meta fields will handle other data
            'menu_icon'          => 'dashicons-archive',
            'show_in_rest'       => true, // Enable Gutenberg editor and REST API support
        ];
        register_post_type(ASSET_MANAGER_POST_TYPE, $args);
    }

    /**
     * Registers the 'asset_category' custom taxonomy.
     */
    public function register_asset_taxonomy() {
        $labels = [
            'name'              => _x('Asset Categories', 'taxonomy general name', 'asset-manager'),
            'singular_name'     => _x('Asset Category', 'taxonomy singular name', 'asset-manager'),
            'search_items'      => __('Search Asset Categories', 'asset-manager'),
            'all_items'         => __('All Asset Categories', 'asset-manager'),
            'parent_item'       => __('Parent Asset Category', 'asset-manager'),
            'parent_item_colon' => __('Parent Asset Category:', 'asset-manager'),
            'edit_item'         => __('Edit Asset Category', 'asset-manager'),
            'update_item'       => __('Update Asset Category', 'asset-manager'),
            'add_new_item'      => __('Add New Asset Category', 'asset-manager'),
            'new_item_name'     => __('New Asset Category Name', 'asset-manager'),
            'menu_name'         => __('Categories', 'asset-manager'),
        ];
        $args = [
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => ASSET_MANAGER_TAXONOMY],
            'show_in_rest'      => true, // Enable REST API support
        ];
        register_taxonomy(ASSET_MANAGER_TAXONOMY, ASSET_MANAGER_POST_TYPE, $args);
    }
}
