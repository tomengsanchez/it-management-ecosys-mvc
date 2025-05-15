<?php
/**
 * Asset Manager Meta Fields.
 *
 * Handles meta boxes for assets.
 *
 * @package AssetManager
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Asset_Manager_Meta_Fields {

    /**
     * Define the custom fields for assets.
     * Order here determines the display order in the meta box.
     * @var array
     */
    private $fields = [
        'asset_tag', 
        'model', 
        'serial_number', 
        'brand', 
        'supplier',
        'date_purchased', 
        'issued_to', 
        'status', 
        'location', 
        'description'
    ];

    /**
     * Define the status options for assets.
     * "Unassigned" is the first option.
     * @var array
     */
    private $status_options = [
        'Unassigned', 
        'Assigned', 
        'Returned', 
        'For Repair', 
        'Repairing', 
        'Archived', 
        'Disposed'
    ];

    /**
     * Registers WordPress hooks.
     */
    public function register_hooks() {
        add_action('add_meta_boxes', [$this, 'add_asset_meta_boxes']);
        add_action('save_post_' . ASSET_MANAGER_POST_TYPE, [$this, 'save_asset_image_data']);
        add_action('save_post_' . ASSET_MANAGER_POST_TYPE, [$this, 'save_asset_details_data'], 10, 2);
    }

    /**
     * Adds all meta boxes for the asset post type.
     *
     * @param string $post_type The current post type.
     */
    public function add_asset_meta_boxes($post_type) {
        if ($post_type === ASSET_MANAGER_POST_TYPE) {
            // Asset Image Meta Box
            add_meta_box(
                'asset_image_metabox', // Unique ID
                __('Asset Image', 'asset-manager'),
                [$this, 'render_image_meta_box_content'],
                ASSET_MANAGER_POST_TYPE,
                'side',
                'default'
            );

            // Asset Details Meta Box
            add_meta_box(
                ASSET_MANAGER_META_PREFIX . 'details',
                __('Asset Details', 'asset-manager'),
                [$this, 'render_asset_fields_meta_box_content'],
                ASSET_MANAGER_POST_TYPE,
                'normal',
                'high'
            );

            // Asset History Meta Box
            add_meta_box(
                ASSET_MANAGER_META_PREFIX . 'history',
                __('Asset History', 'asset-manager'),
                [$this, 'render_history_meta_box_content'],
                ASSET_MANAGER_POST_TYPE,
                'normal',
                'default'
            );
        }
    }
    
    /**
     * Returns the labels for the custom fields.
     *
     * @return array Field labels.
     */
    private function get_field_labels() {
        return [
            'asset_tag'     => __('Asset Tag', 'asset-manager'),
            'serial_number' => __('Serial Number', 'asset-manager'),
            'brand'         => __('Brand', 'asset-manager'),
            'model'         => __('Model', 'asset-manager'),
            'supplier'      => __('Supplier', 'asset-manager'),
            'date_purchased'=> __('Date Purchased', 'asset-manager'),
            'issued_to'     => __('Issued To', 'asset-manager'),
            'status'        => __('Status', 'asset-manager'),
            'location'      => __('Location', 'asset-manager'),
            'description'   => __('Description', 'asset-manager'),
            ASSET_MANAGER_META_PREFIX . 'asset_category' => __('Category', 'asset-manager'), // Used in validation
        ];
    }

    /**
     * Renders the content for the Asset Image meta box.
     *
     * @param WP_Post $post The current post object.
     */
    public function render_image_meta_box_content($post) {
        $image_id = get_post_meta($post->ID, '_asset_image_id', true);
        $image_url = $image_id ? wp_get_attachment_url($image_id) : '';

        wp_nonce_field('save_asset_image_nonce', 'asset_image_nonce');
        ?>
        <div>
            <img id="asset-image-preview" src="<?php echo esc_url($image_url); ?>" style="max-width:100%; height:auto; <?php echo empty($image_url) ? 'display:none;' : ''; ?>" />
            <input type="hidden" name="asset_image_id" id="asset-image-id" value="<?php echo esc_attr($image_id); ?>" />
            <button type="button" class="button" id="upload-asset-image"><?php _e('Upload Image', 'asset-manager'); ?></button>
            <button type="button" class="button" id="remove-asset-image" style="<?php echo empty($image_url) ? 'display:none;' : ''; ?>"><?php _e('Remove Image', 'asset-manager'); ?></button>
        </div>
        <script>
            jQuery(document).ready(function($){
                var mediaUploader;
                $('#upload-asset-image').click(function(e) {
                    e.preventDefault();
                    if (mediaUploader) { mediaUploader.open(); return; }
                    mediaUploader = wp.media({
                        title: '<?php esc_js(__('Select Asset Image', 'asset-manager')); ?>',
                        button: { text: '<?php esc_js(__('Use this image', 'asset-manager')); ?>' },
                        multiple: false
                    });
                    mediaUploader.on('select', function() {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                        $('#asset-image-id').val(attachment.id);
                        $('#asset-image-preview').attr('src', attachment.url).show();
                        $('#remove-asset-image').show();
                    });
                    mediaUploader.open();
                });
                $('#remove-asset-image').click(function() {
                    $('#asset-image-id').val('');
                    $('#asset-image-preview').attr('src', '').hide();
                    $(this).hide();
                });
            });
        </script>
        <?php
    }

    /**
     * Saves the asset image ID.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save_asset_image_data($post_id) {
        if (!isset($_POST['asset_image_nonce']) || !wp_verify_nonce($_POST['asset_image_nonce'], 'save_asset_image_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        // No need to check post type here as save_post_ASSET_MANAGER_POST_TYPE hook is used.

        $image_id = isset($_POST['asset_image_id']) ? intval($_POST['asset_image_id']) : '';
        if ($image_id) {
            update_post_meta($post_id, '_asset_image_id', $image_id);
        } else {
            delete_post_meta($post_id, '_asset_image_id');
        }
    }

    /**
     * Renders the content for the Asset Details meta box.
     *
     * @param WP_Post $post The current post object.
     */
    public function render_asset_fields_meta_box_content($post) {
        wp_nonce_field(ASSET_MANAGER_META_PREFIX . 'save_details_nonce_action', ASSET_MANAGER_META_PREFIX . 'details_nonce');
        
        $meta_values = [];
        $all_meta = get_post_meta($post->ID); // Get all meta for efficiency
        foreach ($this->fields as $field_key) {
            $meta_key_with_prefix = ASSET_MANAGER_META_PREFIX . $field_key;
            $meta_values[$field_key] = isset($all_meta[$meta_key_with_prefix][0]) ? $all_meta[$meta_key_with_prefix][0] : '';
        }

        $users = get_users(['orderby' => 'display_name', 'fields' => ['ID', 'display_name', 'user_email']]);
        $categories = get_terms(['taxonomy' => ASSET_MANAGER_TAXONOMY, 'hide_empty' => false]);
        $field_labels = $this->get_field_labels();
        ?>
        <div class="asset-fields">
            <?php foreach ($this->fields as $field_key) :
                $field_id = 'am_' . str_replace('_', '-', $field_key);
                $field_name_attr = ASSET_MANAGER_META_PREFIX . $field_key;
                $label_text = isset($field_labels[$field_key]) ? $field_labels[$field_key] : ucfirst(str_replace('_', ' ', $field_key));
                $value = $meta_values[$field_key];
            ?>
            <p>
                <label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($label_text); ?>: <span class="required">*</span></label>
                <?php if ($field_key === 'issued_to') : ?>
                    <select id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name_attr); ?>" class="widefat" required>
                        <option value=""><?php esc_html_e('-- Select User --', 'asset-manager'); ?></option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($value, $user->ID); ?>>
                                <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($field_key === 'status') : ?>
                    <select id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name_attr); ?>" class="widefat" required>
                        <option value=""><?php esc_html_e('-- Select Status --', 'asset-manager'); ?></option>
                        <?php foreach ($this->status_options as $status_option) : ?>
                            <option value="<?php echo esc_attr($status_option); ?>" <?php selected($value, $status_option); ?>>
                                <?php echo esc_html($status_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($field_key === 'description') : ?>
                    <textarea id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name_attr); ?>" class="widefat" rows="5" required><?php echo esc_textarea($value); ?></textarea>
                <?php elseif ($field_key === 'date_purchased') : ?>
                    <input type="date" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name_attr); ?>" value="<?php echo esc_attr($value); ?>" class="widefat" required>
                <?php else : ?>
                    <input type="text" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name_attr); ?>" value="<?php echo esc_attr($value); ?>" class="widefat" required>
                <?php endif; ?>
            </p>
            <?php endforeach; ?>

            <p>
                <label for="am_asset_category"><?php esc_html_e('Category:', 'asset-manager'); ?> <span class="required">*</span></label>
                <select id="am_asset_category" name="<?php echo esc_attr(ASSET_MANAGER_META_PREFIX . 'asset_category'); ?>" class="widefat" required>
                    <option value=""><?php esc_html_e('-- Select Category --', 'asset-manager'); ?></option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected(has_term($cat->term_id, ASSET_MANAGER_TAXONOMY, $post)); ?>>
                            <?php echo esc_html($cat->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
        </div>
        <?php
    }

    /**
     * Renders the content for the Asset History meta box.
     *
     * @param WP_Post $post The current post object.
     */
    public function render_history_meta_box_content($post) {
        $history = get_post_meta($post->ID, ASSET_MANAGER_META_PREFIX . 'history', true);
        if (empty($history) || !is_array($history)) {
            echo '<p>' . esc_html__('No history available.', 'asset-manager') . '</p>';
            return;
        }
        echo '<ul class="asset-history">';
        foreach (array_reverse($history) as $entry) {
            $user_info = '';
            if (!empty($entry['user'])) {
                $user_data = get_userdata($entry['user']);
                if ($user_data) {
                    $user_info = ' (' . esc_html($user_data->display_name) . ')';
                }
            }
            $formatted_date = !empty($entry['date']) ? mysql2date(get_option('date_format') . ' @ ' . get_option('time_format'), $entry['date']) : __('Unknown Date', 'asset-manager');
            echo '<li><strong>' . esc_html($formatted_date) . esc_html($user_info) . ':</strong> ' . wp_kses_post($entry['note']) . '</li>';
        }
        echo '</ul>';
    }

    /**
     * Validates asset data from the form submission.
     *
     * @param array $form_data Raw data from $_POST.
     * @return array Array of error messages.
     */
    private function _validate_asset_data(array $form_data): array {
        $errors = [];
        $field_labels = $this->get_field_labels();

        foreach ($this->fields as $field_key) {
            $post_field_key = ASSET_MANAGER_META_PREFIX . $field_key;
            $value = isset($form_data[$post_field_key]) ? trim($form_data[$post_field_key]) : '';

            if ($field_key === 'date_purchased') {
                if (empty($value)) {
                    $errors[] = sprintf(__('The %s field is required.', 'asset-manager'), $field_labels[$field_key]);
                } else {
                    $date = DateTime::createFromFormat('Y-m-d', $value);
                    if (!$date || $date->format('Y-m-d') !== $value) {
                        $errors[] = sprintf(__('The %s field has an invalid date format. Please use YYYY-MM-DD.', 'asset-manager'), $field_labels[$field_key]);
                    }
                }
            } elseif ($field_key === 'status') {
                 if ($value === '') {
                     $errors[] = sprintf(__('The %s field is required; please select a status.', 'asset-manager'), $field_labels[$field_key]);
                 } elseif (!in_array($value, $this->status_options, true)) {
                     $errors[] = sprintf(__('Invalid value selected for the %s field.', 'asset-manager'), $field_labels[$field_key]);
                 }
            } elseif (empty($value) && $value !== '0') { // '0' can be a valid input for some text fields.
                if ($field_key === 'issued_to' && $form_data[$post_field_key] === '') {
                    $status_value = isset($form_data[ASSET_MANAGER_META_PREFIX . 'status']) ? trim($form_data[ASSET_MANAGER_META_PREFIX . 'status']) : '';
                    if ($status_value !== 'Unassigned') { // If status is not 'Unassigned', 'issued_to' is required.
                        $errors[] = sprintf(__('The %s field is required; please select a user unless status is Unassigned.', 'asset-manager'), $field_labels[$field_key]);
                    }
                } elseif ($field_key !== 'issued_to') { // All other text fields are generally required.
                    $errors[] = sprintf(__('The %s field is required.', 'asset-manager'), $field_labels[$field_key]);
                }
            }
        }

        $category_post_key = ASSET_MANAGER_META_PREFIX . 'asset_category';
        $category_value = isset($form_data[$category_post_key]) ? $form_data[$category_post_key] : '';
        if (empty($category_value)) {
            $errors[] = sprintf(__('The %s field is required; please select a category.', 'asset-manager'), $field_labels[$category_post_key]);
        }
        return $errors;
    }

    /**
     * Saves asset meta data and logs history.
     *
     * @param int     $post_id The ID of the post being saved.
     * @param WP_Post $post    The post object.
     */
    public function save_asset_details_data($post_id, $post) {
        // Check nonce
        if (!isset($_POST[ASSET_MANAGER_META_PREFIX . 'details_nonce']) || !wp_verify_nonce($_POST[ASSET_MANAGER_META_PREFIX . 'details_nonce'], ASSET_MANAGER_META_PREFIX . 'save_details_nonce_action')) {
            return;
        }
        // Check if autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        // Post type check is implicitly handled by save_post_ASSET_MANAGER_POST_TYPE hook.

        $errors = $this->_validate_asset_data($_POST);
        
        if (!empty($errors)) {
            set_transient('asset_manager_errors_' . $post_id . '_' . get_current_user_id(), $errors, 45); // Store errors in a transient
            // To prevent WordPress from showing its own "Post updated." message when we have errors:
            add_filter('redirect_post_location', function($location) use ($post_id) {
                if (get_transient('asset_manager_errors_' . $post_id . '_' . get_current_user_id())) {
                    return remove_query_arg('message', $location); // Remove the 'message' query arg
                }
                return $location;
            }, 99);
            return; // Stop saving process
        }

        $changes = [];
        $current_history = get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'history', true) ?: [];
        if (!is_array($current_history)) $current_history = [];
        
        $field_labels = $this->get_field_labels();
        $current_status_from_form = isset($_POST[ASSET_MANAGER_META_PREFIX . 'status']) ? sanitize_text_field($_POST[ASSET_MANAGER_META_PREFIX . 'status']) : null;

        // Save custom fields
        foreach ($this->fields as $field_key) {
            $meta_key = ASSET_MANAGER_META_PREFIX . $field_key;
            $new_value_raw = isset($_POST[$meta_key]) ? $_POST[$meta_key] : null;
            $old_value = get_post_meta($post_id, $meta_key, true);
            $new_value_sanitized = '';

            switch ($field_key) {
                case 'description':
                    $new_value_sanitized = sanitize_textarea_field($new_value_raw);
                    break;
                case 'date_purchased': // Already validated for YYYY-MM-DD format
                    $new_value_sanitized = sanitize_text_field($new_value_raw);
                    break;
                case 'issued_to':
                    $new_value_sanitized = ($current_status_from_form === 'Unassigned' && empty($new_value_raw)) ? '' : absint($new_value_raw);
                    break;
                case 'status': // Already validated against $this->status_options
                    $new_value_sanitized = sanitize_text_field($new_value_raw);
                    break;
                default: 
                    $new_value_sanitized = sanitize_text_field($new_value_raw);
                    break;
            }
            
            // Normalize for comparison (e.g. user ID 0 vs '')
            $old_value_comp = ($field_key === 'issued_to' && ($old_value === '0' || $old_value === 0)) ? '' : $old_value;
            $new_value_comp = ($field_key === 'issued_to' && ($new_value_sanitized === '0' || $new_value_sanitized === 0)) ? '' : $new_value_sanitized;

            if ($new_value_comp !== $old_value_comp) {
                update_post_meta($post_id, $meta_key, $new_value_sanitized);
                $label = $field_labels[$field_key];
                $old_display = empty($old_value_comp) ? __('empty', 'asset-manager') : (string)$old_value_comp;
                $new_display = empty($new_value_comp) ? __('empty', 'asset-manager') : (string)$new_value_comp;

                if ($field_key === 'description') {
                    $changes[] = sprintf(esc_html__('%1$s changed.', 'asset-manager'), esc_html($label));
                } elseif ($field_key === 'issued_to') {
                    $old_user_display = __('Unassigned', 'asset-manager');
                    if (!empty($old_value_comp)) {
                        $old_user_data = get_userdata($old_value_comp);
                        $old_user_display = $old_user_data ? $old_user_data->display_name : sprintf(__('Unknown User (ID: %s)', 'asset-manager'), $old_value_comp);
                    }
                    $new_user_display = __('Unassigned', 'asset-manager'); 
                    if (!empty($new_value_comp)) { 
                        $new_user_data = get_userdata($new_value_comp);
                        $new_user_display = $new_user_data ? $new_user_data->display_name : sprintf(__('Unknown User (ID: %s)', 'asset-manager'), $new_value_comp);
                    }
                    if ($old_user_display !== $new_user_display) {
                         $changes[] = sprintf(esc_html__('%1$s changed from "%2$s" to "%3$s"', 'asset-manager'), esc_html($label), esc_html($old_user_display), esc_html($new_user_display));
                    }
                } else { 
                    if ($old_display !== $new_display) {
                        $changes[] = sprintf(esc_html__('%1$s changed from "%2$s" to "%3$s"', 'asset-manager'), esc_html($label), esc_html($old_display), esc_html($new_display));
                    }
                }
            }
        }

        // Save category (taxonomy term)
        $category_post_key = ASSET_MANAGER_META_PREFIX . 'asset_category';
        if (isset($_POST[$category_post_key])) {
            $new_term_id = absint($_POST[$category_post_key]);
            $old_terms = wp_get_post_terms($post_id, ASSET_MANAGER_TAXONOMY, ['fields' => 'ids']);
            $old_term_id = !empty($old_terms) && isset($old_terms[0]) ? absint($old_terms[0]) : 0;

            if ($new_term_id !== $old_term_id) {
                 wp_set_post_terms($post_id, ($new_term_id ? [$new_term_id] : []), ASSET_MANAGER_TAXONOMY, false);
                 $old_term_obj = $old_term_id ? get_term($old_term_id, ASSET_MANAGER_TAXONOMY) : null;
                 $new_term_obj = $new_term_id ? get_term($new_term_id, ASSET_MANAGER_TAXONOMY) : null;
                 $old_term_name = ($old_term_obj && !is_wp_error($old_term_obj)) ? $old_term_obj->name : __('None', 'asset-manager');
                 $new_term_name = ($new_term_obj && !is_wp_error($new_term_obj)) ? $new_term_obj->name : __('None', 'asset-manager');
                 $changes[] = sprintf(esc_html__('Category changed from "%1$s" to "%2$s"', 'asset-manager'), esc_html($old_term_name), esc_html($new_term_name));
            }
        }

        // Log changes to history
        if (!empty($changes)) {
            $history_entry = [
                'date' => current_time('mysql'),
                'user' => get_current_user_id(),
                'note' => implode('; ', $changes)
            ];
            $current_history[] = $history_entry;
            update_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'history', $current_history);
        }

        // Fallback title update if auto_increment_title didn't run or title is still empty
        // This runs after the wp_insert_post_data hook for auto_increment_title.
        $current_post_obj = get_post($post_id); // Get fresh post object
        if ($current_post_obj && (empty($current_post_obj->post_title) || $current_post_obj->post_title === __('Auto Draft'))) {
            // This means the auto-increment title was not set (e.g., user cleared it and saved, or it was an old draft).
            // We can set a title based on Asset Tag or ID as a fallback.
            // However, the `auto_increment_title` function should ideally handle all cases of empty titles on save.
            // If `auto_increment_title` is robust, this block might become less critical or act as a final safety net.
            // For now, let's keep a simplified version of the original fallback.
            $asset_tag_val = get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'asset_tag', true);
            $new_fallback_title = !empty($asset_tag_val) 
                                ? sprintf(__('Asset: %s', 'asset-manager'), $asset_tag_val) 
                                : sprintf(__('Asset #%d', 'asset-manager'), $post_id);

            if ($new_fallback_title !== $current_post_obj->post_title) {
                // Temporarily remove this action to prevent recursion during wp_update_post
                remove_action('save_post_' . ASSET_MANAGER_POST_TYPE, [$this, 'save_asset_details_data'], 10);
                wp_update_post(['ID' => $post_id, 'post_title' => $new_fallback_title]);
                // Re-add the action
                add_action('save_post_' . ASSET_MANAGER_POST_TYPE, [$this, 'save_asset_details_data'], 10, 2);
            }
        }
    }
}
