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
        // Add AJAX actions for saving notes
        add_action('wp_ajax_am_save_asset_note', [$this, 'handle_ajax_save_asset_note']);
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

        // Enqueue media uploader scripts and specific CSS/JS for asset image upload and notes on edit/new asset screens
        if ($is_asset_cpt_edit_screen) {
            wp_enqueue_media(); // WordPress media uploader

            // Enqueue JS for asset image upload (if asset-manager-admin.js exists and is used for this)
            // If asset-manager-admin.js specifically handles the image upload, keep this.
            // If the image upload JS is inline in the meta fields class, this might not be needed for image.
            // Let's assume asset-manager-admin.js is for general admin screen JS or image upload.
            wp_enqueue_script(
                'asset-manager-admin-js', // JS for asset admin edit screens
                ASSET_MANAGER_PLUGIN_URL . 'js/asset-manager-admin.js', // Ensure this file exists if you plan to add JS here
                ['jquery'],
                ASSET_MANAGER_VERSION,
                true // In footer
            );

            // Enqueue JS for the Notes meta box
            wp_enqueue_script(
                'asset-manager-notes-js', // JS for notes meta box
                ASSET_MANAGER_PLUGIN_URL . 'js/asset-manager-notes.js',
                ['jquery', 'media-upload', 'thickbox'], // Dependencies: jQuery, WP Media Uploader, Thickbox
                ASSET_MANAGER_VERSION,
                true // In footer
            );

            // Localize script for the Notes meta box to pass AJAX URL and nonce
            wp_localize_script(
                'asset-manager-notes-js',
                'assetManagerNotes', // Object name in JavaScript
                [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce'   => wp_create_nonce('am_save_asset_note_nonce'), // Create a nonce for the AJAX action
                    'postId'  => get_the_ID(), // Pass the current post ID
                    'addAttachmentText' => esc_html__('Add Attachment(s)', 'asset-manager'),
                    'removeAttachmentText' => esc_html__('Remove', 'asset-manager'),
                ]
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

    /**
     * Handles the AJAX request to save a new note for an asset.
     */
    public function handle_ajax_save_asset_note() {
        // Check nonce for security
        check_ajax_referer('am_save_asset_note_nonce', 'nonce');

        // Verify user permissions
        if (!current_user_can('edit_post', $_POST['post_id'])) {
            wp_send_json_error(['message' => __('You do not have permission to add notes to this asset.', 'asset-manager')]);
        }

        $post_id = absint($_POST['post_id']);
        $note_content = isset($_POST['note_content']) ? sanitize_textarea_field($_POST['note_content']) : '';
        $attachment_ids = isset($_POST['attachment_ids']) ? array_map('absint', explode(',', sanitize_text_field($_POST['attachment_ids']))) : [];
        $attachment_ids = array_filter($attachment_ids); // Remove any empty or zero values

        // Ensure there's either a note or attachments
        if (empty($note_content) && empty($attachment_ids)) {
            wp_send_json_error(['message' => __('Note content and attachments are empty.', 'asset-manager')]);
        }

        // Get existing notes
        $existing_notes = get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'notes', true);
        if (!is_array($existing_notes)) {
            $existing_notes = [];
        }

        // Create the new note entry
        $new_note_entry = [
            'date'        => current_time('mysql'),
            'user'        => get_current_user_id(),
            'note'        => $note_content,
            'attachments' => $attachment_ids,
        ];

        // Add the new note to the array
        $existing_notes[] = $new_note_entry;

        // Update the post meta
        update_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'notes', $existing_notes);

        // Prepare the HTML for the new note to be added to the list without a page reload
        ob_start();
        $user_info = '';
        $user_data = get_userdata(get_current_user_id());
        if ($user_data) {
            $user_info = ' (' . esc_html($user_data->display_name) . ')';
        }
        $formatted_date = mysql2date(get_option('date_format') . ' @ ' . get_option('time_format'), $new_note_entry['date']);
        ?>
        <li>
            <strong><?php echo esc_html($formatted_date) . esc_html($user_info); ?>:</strong> <?php echo wp_kses_post(nl2br($new_note_entry['note'])); ?>
            <?php if (!empty($new_note_entry['attachments']) && is_array($new_note_entry['attachments'])) : ?>
                <div class="asset-note-attachments">
                    <strong><?php esc_html_e('Attachments:', 'asset-manager'); ?></strong>
                    <ul>
                        <?php foreach ($new_note_entry['attachments'] as $attachment_id) :
                            $attachment_url = wp_get_attachment_url($attachment_id);
                            $attachment_title = get_the_title($attachment_id);
                            if ($attachment_url) : ?>
                                <li><a href="<?php echo esc_url($attachment_url); ?>" target="_blank"><?php echo esc_html($attachment_title); ?></a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </li>
        <?php
        $new_note_html = ob_get_clean();

        wp_send_json_success([
            'message' => __('Note added successfully.', 'asset-manager'),
            'note_html' => $new_note_html
        ]);
    }
}
