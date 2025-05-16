<?php
/**
 * Asset Manager Repair Meta Fields.
 *
 * Handles meta boxes for Repair Requests.
 *
 * @package AssetManager
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Asset_Manager_Repair_Meta_Fields {

    /**
     * Define the custom fields for repair requests (excluding status, which is taxonomy).
     * @var array
     */
    private $fields = [
        'asset_id', // Link to the Asset post type
        'issue_description',
        'requested_by', // Link to a WordPress user
        'date_requested',
        'assigned_technician', // Link to a WordPress user
        // 'status', // Status is handled by the 'repair_status' taxonomy
        'date_started',
        'date_completed',
        'estimated_cost',
        'actual_cost',
        'parts_used',
    ];

    /**
     * Registers WordPress hooks.
     */
    public function register_hooks() {
        add_action('add_meta_boxes', [$this, 'add_repair_request_meta_boxes']);
        add_action('save_post_repair_request', [$this, 'save_repair_request_data'], 10, 2);
        // Add AJAX action for saving repair notes
        add_action('wp_ajax_am_save_repair_note', [$this, 'handle_ajax_save_repair_note']);
    }

    /**
     * Adds all meta boxes for the repair_request post type.
     *
     * @param string $post_type The current post type.
     */
    public function add_repair_request_meta_boxes($post_type) {
        if ($post_type === 'repair_request') {
            // Repair Details Meta Box
            add_meta_box(
                ASSET_MANAGER_META_PREFIX . 'repair_details', // Unique ID
                __('Repair Request Details', 'asset-manager'),
                [$this, 'render_repair_details_meta_box_content'],
                'repair_request', // Post type
                'normal',
                'high'
            );

            // Repair Notes Meta Box
             add_meta_box(
                ASSET_MANAGER_META_PREFIX . 'repair_notes', // Unique ID
                __('Repair Notes', 'asset-manager'),
                [$this, 'render_repair_notes_meta_box_content'],
                'repair_request', // Post type
                'normal',
                'default'
            );
        }
    }

     /**
     * Returns the labels for the custom fields (including status for validation/display).
     *
     * @return array Field labels.
     */
    private function get_field_labels() {
        return [
            'asset_id'              => __('Asset', 'asset-manager'),
            'issue_description'     => __('Issue Description', 'asset-manager'),
            'requested_by'          => __('Requested By', 'asset-manager'),
            'date_requested'        => __('Date Requested', 'asset-manager'),
            'assigned_technician'   => __('Assigned Technician', 'asset-manager'),
            'status'                => __('Status', 'asset-manager'), // Label for the status field (used in validation/UI)
            'date_started'          => __('Date Started', 'asset-manager'),
            'date_completed'        => __('Date Completed', 'asset-manager'),
            'estimated_cost'        => __('Estimated Cost', 'asset-manager'),
            'actual_cost'           => __('Actual Cost', 'asset-manager'),
            'parts_used'            => __('Parts Used', 'asset-manager'),
        ];
    }


    /**
     * Renders the content for the Repair Request Details meta box.
     *
     * @param WP_Post $post The current post object.
     */
    public function render_repair_details_meta_box_content($post) {
        wp_nonce_field(ASSET_MANAGER_META_PREFIX . 'save_repair_details_nonce_action', ASSET_MANAGER_META_PREFIX . 'repair_details_nonce');

        $meta_values = [];
        $all_meta = get_post_meta($post->ID); // Get all meta for efficiency
        foreach ($this->fields as $field_key) {
            $meta_key_with_prefix = ASSET_MANAGER_REPAIR_META_PREFIX . $field_key; // Use repair prefix
            $meta_values[$field_key] = isset($all_meta[$meta_key_with_prefix][0]) ? $all_meta[$meta_key_with_prefix][0] : '';
        }

        // Get all Assets for the dropdown
        $assets = get_posts([
            'post_type'      => ASSET_MANAGER_POST_TYPE,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish', // Only show published assets
            'fields'         => 'ids', // Get only IDs for efficiency
        ]);

        // Get all users for "Requested By" and "Assigned Technician" dropdowns
        $users = get_users(['orderby' => 'display_name', 'fields' => ['ID', 'display_name', 'user_email']]);

        // Get Repair Status terms for the dropdown
        $statuses = get_terms(['taxonomy' => ASSET_MANAGER_REPAIR_TAXONOMY, 'hide_empty' => false]);

        $field_labels = $this->get_field_labels();

        // Get the current status term ID(s) for the post
        $current_terms = wp_get_post_terms($post->ID, ASSET_MANAGER_REPAIR_TAXONOMY, ['fields' => 'ids']);
        // Assuming only one status term can be assigned
        $current_status_id = !empty($current_terms) && !is_wp_error($current_terms) ? $current_terms[0] : 0;

        ?>
        <div class="asset-fields"> <?php // Reuse asset-fields class for basic styling ?>
            <?php foreach ($this->fields as $field_key) :
                $field_id = 'am_repair_' . str_replace('_', '-', $field_key);
                $field_name_attr = ASSET_MANAGER_REPAIR_META_PREFIX . $field_key; // Use repair prefix
                $label_text = isset($field_labels[$field_key]) ? $field_labels[$field_key] : ucfirst(str_replace('_', ' ', $field_key));
                $value = $meta_values[$field_key];
                $required = in_array($field_key, ['asset_id', 'issue_description', 'requested_by', 'date_requested']); // Define required fields (status handled separately)
            ?>
            <p>
                <label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($label_text); ?>: <?php if ($required) echo '<span class="required">*</span>'; ?></label>
                <?php if ($field_key === 'asset_id') : ?>
                    <select id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name_attr); ?>" class="widefat" <?php if ($required) echo 'required'; ?>>
                        <option value=""><?php esc_html_e('-- Select Asset --', 'asset-manager'); ?></option>
                        <?php
                        // Fetch asset titles efficiently after getting IDs
                        $asset_titles = [];
                        if (!empty($assets)) {
                            $asset_posts = get_posts(['post_type' => ASSET_MANAGER_POST_TYPE, 'post__in' => $assets, 'posts_per_page' => -1, 'orderby' => 'post__in']);
                            foreach ($asset_posts as $asset_post) {
                                $asset_titles[$asset_post->ID] = $asset_post->post_title;
                            }
                        }
                        ?>
                        <?php foreach ($assets as $asset_id_option): ?>
                             <?php if (isset($asset_titles[$asset_id_option])) : ?>
                                <option value="<?php echo esc_attr($asset_id_option); ?>" <?php selected($value, $asset_id_option); ?>>
                                    <?php echo esc_html($asset_titles[$asset_id_option]); ?>
                                </option>
                             <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($field_key === 'requested_by' || $field_key === 'assigned_technician') : ?>
                     <select id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name_attr); ?>" class="widefat" <?php if ($required) echo 'required'; ?>>
                        <option value=""><?php esc_html_e('-- Select User --', 'asset-manager'); ?></option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($value, $user->ID); ?>>
                                <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($field_key === 'issue_description' || $field_key === 'parts_used') : ?>
                    <textarea id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name_attr); ?>" class="widefat" rows="4" <?php if ($required) echo 'required'; ?>><?php echo esc_textarea($value); ?></textarea>
                <?php elseif (in_array($field_key, ['date_requested', 'date_started', 'date_completed'])) : ?>
                     <input type="date" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name_attr); ?>" value="<?php echo esc_attr($value); ?>" class="widefat" <?php if ($required) echo 'required'; ?>>
                <?php elseif (in_array($field_key, ['estimated_cost', 'actual_cost'])) : ?>
                     <input type="number" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name_attr); ?>" value="<?php echo esc_attr($value); ?>" class="widefat" step="0.01">
                <?php else : ?>
                    <input type="text" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name_attr); ?>" value="<?php echo esc_attr($value); ?>" class="widefat" <?php if ($required) echo 'required'; ?>>
                <?php endif; ?>
            </p>
            <?php endforeach; ?>

            <?php // Status dropdown handled separately as it's a taxonomy ?>
             <p>
                <label for="am_repair_status"><?php esc_html_e('Status:', 'asset-manager'); ?> <span class="required">*</span></label>
                <select id="am_repair_status" name="<?php echo esc_attr(ASSET_MANAGER_REPAIR_TAXONOMY); ?>" class="widefat" required>
                     <option value=""><?php esc_html_e('-- Select Status --', 'asset-manager'); ?></option>
                    <?php if (!empty($statuses) && !is_wp_error($statuses)) : ?>
                        <?php foreach ($statuses as $status_term) : ?>
                            <option value="<?php echo esc_attr($status_term->term_id); ?>" <?php selected($current_status_id, $status_term->term_id); ?>>
                                <?php echo esc_html($status_term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </p>
        </div>
        <?php
    }

     /**
     * Renders the content for the Repair Notes meta box.
     * This is a simple textarea for notes. A more advanced system
     * with history and attachments would require separate implementation.
     *
     * @param WP_Post $post The current post object.
     */
    public function render_repair_notes_meta_box_content($post) {
        // Note: Nonce for saving notes is handled by the main save_repair_request_data function
        $notes = get_post_meta($post->ID, ASSET_MANAGER_REPAIR_META_PREFIX . 'notes_log', true); // Use a different meta key for notes log
        if (empty($notes) || !is_array($notes)) {
            $notes = [];
        }
         // Reverse to show latest notes first
        $notes = array_reverse($notes);
        ?>
        <div class="asset-notes"> <?php // Reuse asset-notes class for basic styling ?>
            <div class="asset-notes-list">
                 <?php if (empty($notes)) : ?>
                    <p><?php esc_html_e('No notes added yet.', 'asset-manager'); ?></p>
                <?php else : ?>
                    <ul>
                        <?php foreach ($notes as $note_entry) :
                            $user_info = '';
                            if (!empty($note_entry['user'])) {
                                $user_data = get_userdata($note_entry['user']);
                                if ($user_data) {
                                    $user_info = ' (' . esc_html($user_data->display_name) . ')';
                                }
                            }
                            $formatted_date = !empty($note_entry['date']) ? mysql2date(get_option('date_format') . ' @ ' . get_option('time_format'), $note_entry['date']) : __('Unknown Date', 'asset-manager');
                            ?>
                            <li>
                                <strong><?php echo esc_html($formatted_date) . esc_html($user_info); ?>:</strong> <?php echo wp_kses_post(nl2br($note_entry['note'])); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="asset-add-note">
                <h3><?php esc_html_e('Add New Note', 'asset-manager'); ?></h3>
                <textarea name="new_repair_note_content" id="new-repair-note-content" class="widefat" rows="4" placeholder="<?php esc_attr_e('Enter your repair note here...', 'asset-manager'); ?>"></textarea>
                 <button type="button" class="button button-secondary" id="add-repair-note"><?php esc_html_e('Add Note', 'asset-manager'); ?></button>
                 <span class="spinner" id="repair-note-spinner"></span>
                 <div id="repair-note-message" style="display:none; margin-top: 10px;"></div>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($){
                 // AJAX for adding repair notes
                $('#add-repair-note').on('click', function(e) {
                    e.preventDefault();

                    var $addNoteButton = $(this);
                    var $noteTextarea = $('#new-repair-note-content');
                    var $spinner = $('#repair-note-spinner');
                    var $messageDiv = $('#repair-note-message');
                    var noteContent = $noteTextarea.val().trim();
                    var postId = <?php echo json_encode($post->ID); ?>;
                    var nonce = '<?php echo wp_create_nonce("am_save_repair_note_nonce"); ?>'; // Create a unique nonce for repair notes AJAX

                    if (noteContent === '') {
                        $messageDiv.removeClass('notice notice-success notice-error').addClass('notice notice-warning').html('<p><?php esc_html_e("Note content is empty.", "asset-manager"); ?></p>').show();
                        return;
                    }

                    $addNoteButton.prop('disabled', true);
                    $spinner.css('visibility', 'visible');
                    $messageDiv.hide().empty();

                    $.ajax({
                        url: ajaxurl, // WordPress AJAX URL
                        type: 'POST',
                        data: {
                            action: 'am_save_repair_note', // Custom AJAX action
                            nonce: nonce,
                            post_id: postId,
                            note_content: noteContent
                        },
                        success: function(response) {
                            if (response.success) {
                                // Prepend the new note HTML to the list
                                var $notesListUl = $('.asset-notes-list ul');
                                if ($notesListUl.length === 0) {
                                    $('.asset-notes-list p:contains("No notes added yet.")').remove();
                                    $notesListUl = $('<ul>').appendTo('.asset-notes-list');
                                }
                                $notesListUl.prepend(response.data.note_html);

                                // Clear textarea and show success
                                $noteTextarea.val('');
                                $messageDiv.removeClass('notice notice-error notice-warning').addClass('notice notice-success').html('<p>' + response.data.message + '</p>').show();
                            } else {
                                // Show error
                                $messageDiv.removeClass('notice notice-success notice-warning').addClass('notice notice-error').html('<p>' + response.data.message + '</p>').show();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', status, error);
                            $messageDiv.removeClass('notice notice-success notice-warning').addClass('notice notice-error').html('<p><?php esc_html_e("An error occurred while saving the note.", "asset-manager"); ?></p>').show();
                        },
                        complete: function() {
                            $addNoteButton.prop('disabled', false);
                            $spinner.css('visibility', 'hidden');
                        }
                    });
                });
            });
        </script>
        <?php
    }


    /**
     * Validates repair request data from the form submission.
     *
     * @param array $form_data Raw data from $_POST.
     * @return array Array of error messages.
     */
    private function _validate_repair_request_data(array $form_data): array {
        $errors = [];
        $field_labels = $this->get_field_labels();
        // Required fields now exclude status, as it's handled via taxonomy validation
        $required_fields = ['asset_id', 'issue_description', 'requested_by', 'date_requested'];

        foreach ($this->fields as $field_key) {
            $post_field_key = ASSET_MANAGER_REPAIR_META_PREFIX . $field_key; // Use repair prefix
            $value = isset($form_data[$post_field_key]) ? trim($form_data[$post_field_key]) : '';

            // Check required fields
            if (in_array($field_key, $required_fields) && empty($value) && $value !== '0') {
                 $errors[] = sprintf(__('The %s field is required.', 'asset-manager'), $field_labels[$field_key]);
            }

            // Specific validation for date fields
            if (in_array($field_key, ['date_requested', 'date_started', 'date_completed']) && !empty($value)) {
                 $date = DateTime::createFromFormat('Y-m-d', $value);
                 if (!$date || $date->format('Y-m-d') !== $value) {
                     $errors[] = sprintf(__('The %s field has an invalid date format. Please use YYYY-MM-DD.', 'asset-manager'), $field_labels[$field_key]);
                 }
            }

            // Specific validation for cost fields (optional, but ensure numeric if entered)
            if (in_array($field_key, ['estimated_cost', 'actual_cost']) && !empty($value) && !is_numeric($value)) {
                 $errors[] = sprintf(__('The %s field must be a number.', 'asset-manager'), $field_labels[$field_key]);
            }
        }

        // Validate the selected Asset ID
        $asset_id = isset($form_data[ASSET_MANAGER_REPAIR_META_PREFIX . 'asset_id']) ? absint($form_data[ASSET_MANAGER_REPAIR_META_PREFIX . 'asset_id']) : 0;
        if (!empty($asset_id) && get_post_type($asset_id) !== ASSET_MANAGER_POST_TYPE) {
             $errors[] = sprintf(__('Invalid Asset selected for the %s field.', 'asset-manager'), $field_labels['asset_id']);
        }

        // Validate the selected Status Term ID from the taxonomy dropdown
        $status_term_id = isset($form_data[ASSET_MANAGER_REPAIR_TAXONOMY]) ? absint($form_data[ASSET_MANAGER_REPAIR_TAXONOMY]) : 0;
        if (empty($status_term_id)) {
             $errors[] = sprintf(__('The %s field is required; please select a status.', 'asset-manager'), $field_labels['status']);
        } else {
            $status_term = get_term($status_term_id, ASSET_MANAGER_REPAIR_TAXONOMY);
            if (is_wp_error($status_term) || !$status_term) {
                 $errors[] = sprintf(__('Invalid Status selected for the %s field.', 'asset-manager'), $field_labels['status']);
            }
        }

        return $errors;
    }

    /**
     * Saves repair request meta data and updates taxonomy terms.
     *
     * @param int     $post_id The ID of the post being saved.
     * @param WP_Post $post    The post object.
     */
    public function save_repair_request_data($post_id, $post) {
        // Check nonce
        if (!isset($_POST[ASSET_MANAGER_META_PREFIX . 'repair_details_nonce']) || !wp_verify_nonce($_POST[ASSET_MANAGER_META_PREFIX . 'repair_details_nonce'], ASSET_MANAGER_META_PREFIX . 'save_repair_details_nonce_action')) {
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
        // Post type check is implicitly handled by save_post_repair_request hook.

        $errors = $this->_validate_repair_request_data($_POST);

        if (!empty($errors)) {
            // Store errors in a transient and redirect back with errors
            set_transient('asset_manager_repair_errors_' . $post_id . '_' . get_current_user_id(), $errors, 45);
             // Prevent default WordPress update message
            add_filter('redirect_post_location', function($location) use ($post_id) {
                if (get_transient('asset_manager_repair_errors_' . $post_id . '_' . get_current_user_id())) {
                    return remove_query_arg('message', $location);
                }
                return $location;
            }, 99);
            return; // Stop saving process
        }

        // Save custom fields
        foreach ($this->fields as $field_key) {
            // Skip 'status' as it's handled by taxonomy
            if ($field_key === 'status') {
                continue;
            }

            $meta_key = ASSET_MANAGER_REPAIR_META_PREFIX . $field_key; // Use repair prefix
            $new_value_raw = isset($_POST[$meta_key]) ? $_POST[$meta_key] : null;
            $new_value_sanitized = '';

            switch ($field_key) {
                case 'issue_description':
                case 'parts_used':
                    $new_value_sanitized = sanitize_textarea_field($new_value_raw);
                    break;
                case 'asset_id':
                case 'requested_by':
                case 'assigned_technician':
                    $new_value_sanitized = absint($new_value_raw); // Store IDs as integers
                    break;
                case 'date_requested':
                case 'date_started':
                case 'date_completed':
                    $new_value_sanitized = sanitize_text_field($new_value_raw); // Already validated YYYY-MM-DD
                    break;
                 case 'estimated_cost':
                 case 'actual_cost':
                     $new_value_sanitized = sanitize_text_field($new_value_raw); // Store as string, validate numeric
                     break;
                default:
                    $new_value_sanitized = sanitize_text_field($new_value_raw);
                    break;
            }

            // Save or delete meta based on value
            if (!empty($new_value_sanitized) || $new_value_sanitized === '0') { // Keep '0' for potential user ID 0 or cost 0
                update_post_meta($post_id, $meta_key, $new_value_sanitized);
            } else {
                delete_post_meta($post_id, $meta_key);
            }
        }

        // Save Status (Taxonomy Term)
        $status_term_id = isset($_POST[ASSET_MANAGER_REPAIR_TAXONOMY]) ? absint($_POST[ASSET_MANAGER_REPAIR_TAXONOMY]) : 0;
        wp_set_post_terms($post_id, ($status_term_id ? [$status_term_id] : []), ASSET_MANAGER_REPAIR_TAXONOMY, false);

        // Note: History logging for repair requests can be added here, similar to asset history.
        // This would involve comparing old and new meta values and logging changes.

         // Fallback title update if title is empty (similar to assets)
        $current_post_obj = get_post($post_id);
        if ($current_post_obj && (empty($current_post_obj->post_title) || $current_post_obj->post_title === __('Auto Draft'))) {
            $asset_id_val = get_post_meta($post_id, ASSET_MANAGER_REPAIR_META_PREFIX . 'asset_id', true);
            $asset_title = $asset_id_val ? get_the_title($asset_id_val) : __('Unknown Asset', 'asset-manager');
            $new_fallback_title = sprintf(__('Repair Request for %s', 'asset-manager'), $asset_title);

            if ($new_fallback_title !== $current_post_obj->post_title) {
                // Temporarily remove this action to prevent recursion
                remove_action('save_post_repair_request', [$this, 'save_repair_request_data'], 10);
                wp_update_post(['ID' => $post_id, 'post_title' => $new_fallback_title]);
                // Re-add the action
                add_action('save_post_repair_request', [$this, 'save_repair_request_data'], 10, 2);
            }
        }
    }

     /**
     * Handles the AJAX request to save a new note for a repair request.
     * This function is called via admin-ajax.php.
     */
    public function handle_ajax_save_repair_note() {
        // Check nonce for security
        check_ajax_referer('am_save_repair_note_nonce', 'nonce');

        // Verify user permissions
        if (!current_user_can('edit_post', $_POST['post_id'])) {
            wp_send_json_error(['message' => __('You do not have permission to add notes to this repair request.', 'asset-manager')]);
        }

        $post_id = absint($_POST['post_id']);
        $note_content = isset($_POST['note_content']) ? sanitize_textarea_field($_POST['note_content']) : '';

        // Ensure note content is not empty
        if (empty($note_content)) {
            wp_send_json_error(['message' => __('Note content is empty.', 'asset-manager')]);
        }

        // Get existing notes log
        $existing_notes = get_post_meta($post_id, ASSET_MANAGER_REPAIR_META_PREFIX . 'notes_log', true);
        if (!is_array($existing_notes)) {
            $existing_notes = [];
        }

        // Create the new note entry
        $new_note_entry = [
            'date' => current_time('mysql'),
            'user' => get_current_user_id(),
            'note' => $note_content,
        ];

        // Add the new note to the array
        $existing_notes[] = $new_note_entry;

        // Update the post meta
        update_post_meta($post_id, ASSET_MANAGER_REPAIR_META_PREFIX . 'notes_log', $existing_notes);

        // Prepare the HTML for the new note to be added to the list
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
        </li>
        <?php
        $new_note_html = ob_get_clean();

        wp_send_json_success([
            'message' => __('Repair note added successfully.', 'asset-manager'),
            'note_html' => $new_note_html
        ]);
    }
}
