<?php
/**
 * Asset Manager Callbacks.
 *
 * Handles general WordPress callbacks like auto-incrementing titles and admin notices.
 *
 * @package AssetManager
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Asset_Manager_Callbacks {

    /**
     * Registers WordPress hooks.
     */
    public function register_hooks() {
        add_filter('wp_insert_post_data', [$this, 'auto_increment_asset_title'], 20, 2);
        add_action('admin_notices', [$this, 'display_validation_admin_notices']);
    }

    /**
     * Auto-increments asset titles to "ASSETXXXXX" format.
     *
     * @param array $data    An array of slashed post data.
     * @param array $postarr An array of sanitized, but otherwise unmodified post data.
     * @return array The modified post data.
     */
    public function auto_increment_asset_title($data, $postarr) {
        if ($data['post_type'] !== ASSET_MANAGER_POST_TYPE) {
            return $data;
        }

        // Conditions to NOT generate a new title:
        // 1. It's an 'auto-draft' for a new post (ID is not yet set or 0).
        // 2. A meaningful title is already provided (not empty and not 'Auto Draft').
        if (($data['post_status'] === 'auto-draft' && empty($postarr['ID'])) ||
            (!empty($data['post_title']) && $data['post_title'] !== __('Auto Draft'))) {
            return $data;
        }
        
        // Proceed to generate title if it's a manual save of a new/draft post with an empty/auto title.
        $args = [
            'post_type'      => ASSET_MANAGER_POST_TYPE,
            'post_status'    => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => 1, // Fetch only the latest one by title
            'orderby'        => 'title', // Order by title to get the highest number
            'order'          => 'DESC',
            'suppress_filters' => true, // Avoid interference from other pre_get_posts filters
        ];

        $latest_posts = get_posts($args);
        $last_number = 0;

        if (!empty($latest_posts)) {
            // Regex to extract number from "ASSET" followed by digits. Case-insensitive for "ASSET".
            if (isset($latest_posts[0]->post_title) && preg_match('/^ASSET0*(\\d+)$/i', $latest_posts[0]->post_title, $matches)) {
                $last_number = intval($matches[1]);
            }
        }

        $next_number = $last_number + 1;
        $data['post_title'] = 'ASSET' . str_pad($next_number, 5, '0', STR_PAD_LEFT);
        
        // If the slug is empty, WordPress will generate it from the new title.
        // $data['post_name'] = sanitize_title($data['post_title']); // Optionally set slug

        return $data;
    }

    /**
     * Displays admin notices for validation errors.
     * Errors are stored in a transient by Asset_Manager_Meta_Fields::save_asset_details_data().
     */
    public function display_validation_admin_notices() {
        global $pagenow, $post;

        if (($pagenow === 'post.php' || $pagenow === 'post-new.php') &&
            isset($post->ID) && isset($post->post_type) && $post->post_type === ASSET_MANAGER_POST_TYPE) {
            
            $transient_key = 'asset_manager_errors_' . $post->ID . '_' . get_current_user_id();
            $errors = get_transient($transient_key);

            if (!empty($errors) && is_array($errors)) {
                echo '<div id="message" class="notice notice-error is-dismissible">';
                echo '<p><strong>' . esc_html__('Please correct the following errors:', 'asset-manager') . '</strong></p>';
                echo '<ul>';
                foreach ($errors as $error) {
                    echo '<li>' . esc_html($error) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
                delete_transient($transient_key); // Clear the transient after displaying
            }
        }
    }
}
