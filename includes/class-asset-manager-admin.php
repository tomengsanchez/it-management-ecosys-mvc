<?php
/**
 * Asset Manager Admin.
 *
 * Handles admin UI elements like columns, filters, search, and admin pages.
 *
 * @package AssetManager
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Asset_Manager_Admin {

    /**
     * Registers WordPress hooks.
     */
    public function register_hooks() {
        // Admin list table customizations for Assets
        add_filter('manage_' . ASSET_MANAGER_POST_TYPE . '_posts_columns', [$this, 'custom_admin_columns']);
        add_action('manage_' . ASSET_MANAGER_POST_TYPE . '_posts_custom_column', [$this, 'custom_admin_column_content'], 10, 2);

        // Admin list table filters for Assets
        add_action('restrict_manage_posts', [$this, 'add_filters_to_admin_list']);
        add_action('pre_get_posts', [$this, 'apply_filters_to_query']);

        // Admin list table search enhancements for Assets
        add_filter('posts_search', [$this, 'extend_admin_search'], 10, 2);
        add_filter('posts_join', [$this, 'extend_admin_search_join'], 10, 2);
        add_filter('posts_distinct', [$this, 'extend_admin_search_distinct'], 10, 2);

        // Admin pages for Assets
        add_action('admin_menu', [$this, 'register_admin_subpages']);
        add_action('admin_post_am_export_assets_pdf_action', [$this, 'handle_export_assets_pdf']);

        // --- Repair Request Admin Functionality ---
        // Admin list table customizations for Repair Requests
        add_filter('manage_' . ASSET_MANAGER_REPAIR_POST_TYPE . '_posts_columns', [$this, 'custom_repair_admin_columns']);
        add_action('manage_' . ASSET_MANAGER_REPAIR_POST_TYPE . '_posts_custom_column', [$this, 'custom_repair_admin_column_content'], 10, 2);

        // Admin list table filters for Repair Requests
        add_action('restrict_manage_posts', [$this, 'add_repair_filters_to_admin_list']);
        add_action('pre_get_posts', [$this, 'apply_repair_filters_to_query']);

         // Admin list table search enhancements for Repair Requests
        // Using posts_search filters for Repair Requests search as well
        add_filter('posts_search', [$this, 'extend_repair_admin_search'], 10, 2);
        add_filter('posts_join', [$this, 'extend_repair_admin_search_join'], 10, 2);
        add_filter('posts_distinct', [$this, 'extend_repair_admin_search_distinct'], 10, 2);

        // Admin pages for Repair Requests (if any, e.g., a dashboard or report)
        // For now, they will appear under the main menu.

    }

    /**
     * Customizes the columns displayed in the asset list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function custom_admin_columns($columns) {
        $new_columns = [
            'cb'                => $columns['cb'], // Checkbox
            'title'             => __('Title', 'asset-manager'),
            'asset_tag'         => __('Asset Tag', 'asset-manager'),
            'model'             => __('Model', 'asset-manager'),
            'serial_number'     => __('Serial Number', 'asset-manager'),
            'brand'             => __('Brand', 'asset-manager'),
            'asset_category'    => __('Category', 'asset-manager'),
            'location'          => __('Location', 'asset-manager'),
            'status'            => __('Status', 'asset-manager'),
            'issued_to'         => __('Issued To', 'asset-manager'),
            'date_purchased_col'=> __('Date Purchased', 'asset-manager'),
            'date'              => __('Date Created', 'asset-manager') // Original publish date
        ];
        return $new_columns;
    }

    /**
     * Renders the content for custom columns in the asset list table.
     *
     * @param string $column  The name of the column.
     * @param int    $post_id The ID of the current post.
     */
    public function custom_admin_column_content($column, $post_id) {
        switch ($column) {
            case 'asset_tag':
                echo esc_html(get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'asset_tag', true));
                break;
            case 'model':
                echo esc_html(get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'model', true));
                break;
            case 'serial_number':
                echo esc_html(get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'serial_number', true));
                break;
            case 'brand':
                echo esc_html(get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'brand', true));
                break;
            case 'asset_category':
                $terms = get_the_terms($post_id, ASSET_MANAGER_TAXONOMY);
                if (!empty($terms) && !is_wp_error($terms)) {
                    echo esc_html(implode(', ', wp_list_pluck($terms, 'name')));
                } else {
                    echo '—';
                }
                break;
            case 'location':
                echo esc_html(get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'location', true));
                break;
            case 'status':
                echo esc_html(get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'status', true));
                break;
            case 'issued_to':
                $user_id = get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'issued_to', true);
                if ($user_id) {
                    $user = get_userdata($user_id);
                    echo esc_html($user ? $user->display_name : __('Unknown User', 'asset-manager'));
                } else {
                    $status = get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'status', true);
                    echo ($status === 'Unassigned') ? __('Unassigned', 'asset-manager') : '—';
                }
                break;
            case 'date_purchased_col':
                $date_purchased = get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'date_purchased', true);
                echo esc_html($date_purchased ? date_i18n(get_option('date_format'), strtotime($date_purchased)) : '—');
                break;
        }
    }

    /**
     * Adds filter dropdowns (Category, Brand) to the asset list table.
     */
    public function add_filters_to_admin_list() {
        global $typenow;

        if ($typenow === ASSET_MANAGER_POST_TYPE) {
            // Category Filter
            $selected_category = isset($_GET['asset_category_filter']) ? sanitize_text_field($_GET['asset_category_filter']) : '';
            wp_dropdown_categories([
                'show_option_all' => __('All Categories', 'asset-manager'),
                'taxonomy'        => ASSET_MANAGER_TAXONOMY,
                'name'            => 'asset_category_filter', // Used in $_GET
                'orderby'         => 'name',
                'selected'        => $selected_category,
                'hierarchical'    => true,
                'depth'           => 3,
                'show_count'      => true,
                'hide_empty'      => true,
                'value_field'     => 'slug', // Filter by slug
            ]);

            // Brand Filter
            global $wpdb;
            $meta_key = ASSET_MANAGER_META_PREFIX . 'brand';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $brands = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value != '' ORDER BY meta_value ASC",
                $meta_key
            ));

            $selected_brand = isset($_GET['asset_brand_filter']) ? sanitize_text_field($_GET['asset_brand_filter']) : '';
            if (!empty($brands)) {
                echo "<select name='asset_brand_filter' id='asset_brand_filter' style='margin-left:5px;'>"; // Added margin
                echo "<option value=''>" . esc_html__('All Brands', 'asset-manager') . "</option>";
                foreach ($brands as $brand) {
                    printf(
                        "<option value='%s'%s>%s</option>",
                        esc_attr($brand),
                        selected($selected_brand, $brand, false),
                        esc_html($brand)
                    );
                }
                echo "</select>";
            }
        }
    }

    /**
     * Modifies the main query based on selected filters from the asset list table.
     *
     * @param WP_Query $query The main WordPress query object.
     */
    public function apply_filters_to_query($query) {
        global $pagenow;
        // Get current post type from the query or from $_GET if available
        $current_post_type = $query->get('post_type');
        if (empty($current_post_type) && isset($_GET['post_type'])) {
            $current_post_type = sanitize_text_field($_GET['post_type']);
        }

        if (is_admin() && $pagenow === 'edit.php' && $current_post_type === ASSET_MANAGER_POST_TYPE && $query->is_main_query()) {
            $meta_query_arr = $query->get('meta_query') ?: [];
            if (!is_array($meta_query_arr)) {
                $meta_query_arr = [];
            }

            // Category Filter
            if (isset($_GET['asset_category_filter']) && !empty($_GET['asset_category_filter'])) {
                $category_slug = sanitize_text_field($_GET['asset_category_filter']);
                $tax_query = $query->get('tax_query') ?: [];
                if (!is_array($tax_query)) {
                    $tax_query = [];
                }
                $tax_query[] = [
                    'taxonomy' => ASSET_MANAGER_TAXONOMY,
                    'field'    => 'slug',
                    'terms'    => $category_slug,
                ];
                $query->set('tax_query', $tax_query);
            }

            // Brand Filter
            if (isset($_GET['asset_brand_filter']) && !empty($_GET['asset_brand_filter'])) {
                $brand_name = sanitize_text_field($_GET['asset_brand_filter']);
                $meta_query_arr[] = [
                    'key'     => ASSET_MANAGER_META_PREFIX . 'brand',
                    'value'   => $brand_name,
                    'compare' => '=',
                ];
            }

            if (!empty($meta_query_arr)) {
                // Ensure 'relation' is set if there's more than one meta query clause
                if (count($meta_query_arr) > 1 && !isset($meta_query_arr['relation'])) {
                     // Check if the existing meta_query_arr already has a relation key
                    $has_relation = false;
                    foreach ($meta_query_arr as $key => $clause) {
                        if ($key === 'relation') {
                            $has_relation = true;
                            break;
                        }
                    }
                    if (!$has_relation) {
                         // If no relation is explicitly set among existing clauses, default to AND
                         // This prevents breaking existing meta queries if they exist
                         $meta_query_arr['relation'] = 'AND';
                    }
                }
                $query->set('meta_query', $meta_query_arr);
            }
        }
    }

    /**
     * Modifies the WHERE clause for asset admin search to include custom fields and taxonomy.
     *
     * @param string   $search Original WHERE clause generated by WordPress search.
     * @param WP_Query $query  The query object.
     * @return string Modified WHERE clause.
     */
    public function extend_admin_search($search, $query) {
        global $wpdb;

        // Ensure we are on the correct admin screen and it's the main query with a search term
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== ASSET_MANAGER_POST_TYPE || empty($query->get('s'))) {
            return $search;
        }

        $search_term = $query->get('s');
        $search_term_like = '%' . $wpdb->esc_like($search_term) . '%';

        // Remove the default WordPress search clause (which searches post_title and post_content)
        // We will build our own combined search clause.
        $search = '';

        $search_conditions = [];

        // 1. Search in custom meta fields
        $meta_keys_to_search = [
            ASSET_MANAGER_META_PREFIX . 'asset_tag',
            ASSET_MANAGER_META_PREFIX . 'model',
            ASSET_MANAGER_META_PREFIX . 'serial_number',
            ASSET_MANAGER_META_PREFIX . 'brand',
            ASSET_MANAGER_META_PREFIX . 'location',
            ASSET_MANAGER_META_PREFIX . 'status',
            ASSET_MANAGER_META_PREFIX . 'date_purchased',
            ASSET_MANAGER_META_PREFIX . 'description', // Include description in search
        ];

        foreach ($meta_keys_to_search as $meta_key) {
            $search_conditions[] = $wpdb->prepare(
                "($wpdb->postmeta.meta_key = %s AND $wpdb->postmeta.meta_value LIKE %s)",
                $meta_key,
                $search_term_like
            );
        }

        // 2. Search in "Issued To" user display name
        // Find user IDs matching the search term
        $user_query_args = [
            'search'         => '*' . esc_attr($search_term) . '*', // Add wildcards and escape
            'search_columns' => ['user_login', 'user_nicename', 'user_email', 'display_name'],
            'fields'         => 'ID', // Get only IDs
            'blog_id'        => 0, // Search users across the network if multisite
        ];
        $matching_user_ids = get_users($user_query_args);

        if (!empty($matching_user_ids)) {
             // Add a condition to find posts where the 'issued_to' meta value is one of the matching user IDs
             $user_ids_placeholder = implode(',', array_fill(0, count($matching_user_ids), '%d'));
             $search_conditions[] = $wpdb->prepare(
                "($wpdb->postmeta.meta_key = %s AND $wpdb->postmeta.meta_value IN ($user_ids_placeholder))",
                array_merge([ASSET_MANAGER_META_PREFIX . 'issued_to'], $matching_user_ids)
             );
        }

        // 3. Search in Category name
        // Find term IDs matching the search term
         $terms = get_terms([
            'taxonomy'   => ASSET_MANAGER_TAXONOMY,
            'hide_empty' => false,
            'name__like' => $search_term,
            'fields'     => 'term_id', // Get only term IDs
        ]);

        if (!empty($terms) && !is_wp_error($terms)) {
            // Add a condition to find posts associated with these term IDs
            $term_ids_placeholder = implode(',', array_fill(0, count($terms), '%d'));
             $search_conditions[] = $wpdb->prepare(
                "($wpdb->term_relationships.term_taxonomy_id IN (SELECT tt.term_taxonomy_id FROM $wpdb->term_taxonomy tt JOIN $wpdb->terms t ON tt.term_id = t.term_id WHERE t.term_id IN ($term_ids_placeholder) AND tt.taxonomy = %s))",
                array_merge($terms, [ASSET_MANAGER_TAXONOMY])
             );
        }

         // 4. Include search in post title (optional, but good for completeness)
         $search_conditions[] = $wpdb->prepare("($wpdb->posts.post_title LIKE %s)", $search_term_like);


        // Combine all conditions with OR
        if (!empty($search_conditions)) {
            // The original $search variable usually starts with " AND (..."
            // We want to replace that with our custom conditions.
            $search = " AND (" . implode(" OR ", $search_conditions) . ")";
        }

        return $search;
    }

    /**
     * Modifies the JOIN clause for asset custom admin search.
     * Joins necessary tables (postmeta, term_relationships, term_taxonomy, terms)
     * only when a search term is present for the asset post type.
     *
     * @param string   $join  Original JOIN clause.
     * @param WP_Query $query The query object.
     * @return string Modified JOIN clause.
     */
    public function extend_admin_search_join($join, $query) {
        global $wpdb;

        // Ensure we are on the correct admin screen and it's the main query with a search term
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== ASSET_MANAGER_POST_TYPE || empty($query->get('s'))) {
            return $join;
        }

        // Join postmeta table for searching in meta fields
        if (strpos($join, $wpdb->postmeta) === false) {
            $join .= " LEFT JOIN $wpdb->postmeta ON ($wpdb->posts.ID = $wpdb->postmeta.post_id) ";
        }
        // Join term tables for searching category names
        if (strpos($join, $wpdb->term_relationships) === false) {
            $join .= " LEFT JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) ";
        }
        if (strpos($join, $wpdb->term_taxonomy) === false) {
             $join .= " LEFT JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) ";
        }
        if (strpos($join, $wpdb->terms) === false) {
            $join .= " LEFT JOIN $wpdb->terms ON ($wpdb->term_taxonomy.term_id = $wpdb->terms.term_id) ";
        }


        return $join;
    }

    /**
     * Adds DISTINCT to the query for asset custom admin search to avoid duplicate results.
     * Applies only when a search term is present for the asset post type.
     *
     * @param string   $distinct Original DISTINCT clause.
     * @param WP_Query $query    The query object.
     * @return string Modified DISTINCT clause.
     */
    public function extend_admin_search_distinct($distinct, $query) {
        // Ensure we are on the correct admin screen and it's the main query with a search term
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== ASSET_MANAGER_POST_TYPE || empty($query->get('s'))) {
            return $distinct;
        }

        return 'DISTINCT';
    }


    /**
     * Registers admin submenu pages (Export, Dashboard).
     */
    public function register_admin_subpages() {
        add_submenu_page(
            'edit.php?post_type=' . ASSET_MANAGER_POST_TYPE,
            __('Export Assets', 'asset-manager'),
            __('Export to PDF', 'asset-manager'),
            'manage_options', // Capability
            'asset-export',   // Menu slug
            [$this, 'render_export_page_html']
        );
        add_submenu_page(
            'edit.php?post_type=' . ASSET_MANAGER_POST_TYPE,
            __('Asset Dashboard', 'asset-manager'),
            __('Dashboard', 'asset-manager'),
            'manage_options',
            'asset-dashboard',
            [$this, 'render_dashboard_page_html']
        );
    }

    /**
     * Renders the HTML for the Export Assets page.
     */
    public function render_export_page_html() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Export Assets to PDF', 'asset-manager'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="am_export_assets_pdf_action">
                <?php wp_nonce_field('am_export_assets_pdf_nonce_action', 'am_export_nonce'); ?>
                <?php submit_button(__('Export All Assets as PDF', 'asset-manager')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handles the PDF export action.
     */
    public function handle_export_assets_pdf() {
        if (!isset($_POST['am_export_nonce']) || !wp_verify_nonce($_POST['am_export_nonce'], 'am_export_assets_pdf_nonce_action')) {
            wp_die(__('Security check failed.', 'asset-manager'), __('Error', 'asset-manager'), ['response' => 403]);
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to export assets.', 'asset-manager'), __('Error', 'asset-manager'), ['response' => 403]);
        }

        $mpdf_autoloader = ASSET_MANAGER_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($mpdf_autoloader) && !class_exists('\Mpdf\Mpdf')) {
            require_once $mpdf_autoloader;
        }
        if (!class_exists('\Mpdf\Mpdf')) {
            wp_die(__('PDF Export library (mPDF) is missing. Please run "composer install" in the plugin directory or install it manually in the "vendor" folder.', 'asset-manager'), __('PDF Library Error', 'asset-manager'), ['back_link' => true]);
            return;
        }

        $assets_query_args = [
            'post_type'      => ASSET_MANAGER_POST_TYPE,
            'posts_per_page' => -1, // Get all assets
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        $assets_query = new WP_Query($assets_query_args);

        $html = '<style> table { width: 100%; border-collapse: collapse; font-size: 10px; } th, td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: top; word-wrap: break-word; } th { background-color: #f2f2f2; font-weight: bold; } </style>';
        $html .= '<h1>' . esc_html__('Asset List', 'asset-manager') . '</h1>';
        $html .= '<table><thead><tr>';
        $columns = [ // Columns for PDF
            'Title'          => __('Title', 'asset-manager'),
            'Asset Tag'      => __('Asset Tag', 'asset-manager'),
            'Model'          => __('Model', 'asset-manager'),
            'Serial No.'     => __('Serial No.', 'asset-manager'),
            'Brand'          => __('Brand', 'asset-manager'),
            'Category'       => __('Category', 'asset-manager'),
            'Location'       => __('Location', 'asset-manager'),
            'Status'         => __('Status', 'asset-manager'),
            'Issued To'      => __('Issued To', 'asset-manager'),
            'Date Purchased' => __('Date Purchased', 'asset-manager'),
            'Description'    => __('Description', 'asset-manager'),
        ];
        foreach ($columns as $column_label) {
            $html .= '<th>' . esc_html($column_label) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        if ($assets_query->have_posts()) {
            while ($assets_query->have_posts()) {
                $assets_query->the_post();
                $post_id = get_the_ID();
                $html .= '<tr>';
                $html .= '<td>' . esc_html(get_the_title()) . '</td>';
                $html .= '<td>' . esc_html(get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'asset_tag', true)) . '</td>';
                $html .= '<td>' . esc_html(get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'model', true)) . '</td>';
                $html .= '<td>' . esc_html(get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'serial_number', true)) . '</td>';
                $html .= '<td>' . esc_html(get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'brand', true)) . '</td>';

                $categories = get_the_terms($post_id, ASSET_MANAGER_TAXONOMY);
                if (!empty($categories) && !is_wp_error($categories)) {
                    echo '<td>' . esc_html(implode(', ', wp_list_pluck($categories, 'name'))) . '</td>';
                } else {
                    echo '<td>—</td>';
                }

                $html .= '<td>' . esc_html(get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'location', true)) . '</td>';
                $html .= '<td>' . esc_html(get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'status', true)) . '</td>';

                $user_id = get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'issued_to', true);
                $issued_to_name = '—';
                if ($user_id) {
                    $user = get_userdata($user_id);
                    $issued_to_name = $user ? $user->display_name : __('Unknown User', 'asset-manager');
                } elseif (get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'status', true) === 'Unassigned') {
                    $issued_to_name = __('Unassigned', 'asset-manager');
                }
                $html .= '<td>' . esc_html($issued_to_name) . '</td>';

                $date_purchased = get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'date_purchased', true);
                $date_purchased_formatted = $date_purchased ? date_i18n(get_option('date_format'), strtotime($date_purchased)) : '—';
                $html .= '<td>' . esc_html($date_purchased_formatted) . '</td>';

                $html .= '<td>' . nl2br(esc_html(get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'description', true))) . '</td>';
                $html .= '</tr>';
            }
            wp_reset_postdata();
        } else {
            $html .= '<tr><td colspan="' . count($columns) . '">' . esc_html__('No assets found.', 'asset-manager') . '</td></tr>';
        }
        $html .= '</tbody></table>';

        try {
            $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'tempDir' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mpdf']);
            $mpdf->SetTitle(esc_attr__('Asset List', 'asset-manager'));
            $mpdf->SetAuthor(esc_attr(get_bloginfo('name')));
            $mpdf->WriteHTML($html);
            $mpdf->Output('assets-' . date('Y-m-d') . '.pdf', 'D'); // D for download
            exit;
        } catch (\Mpdf\MpdfException $e) {
            wp_die(sprintf(esc_html__('Error generating PDF: %s. Ensure mPDF temporary directory is writable.', 'asset-manager'), esc_html($e->getMessage())), esc_html__('PDF Generation Error', 'asset-manager'), ['back_link' => true]);
        }
    }

    /**
     * Renders the HTML for the Asset Dashboard page.
     */
    public function render_dashboard_page_html() {
        ?>
        <div class="wrap asset-manager-dashboard">
            <h1><?php esc_html_e('Asset Dashboard', 'asset-manager'); ?></h1>
            <div class="dashboard-widgets-wrapper">
                <div class="dashboard-widget">
                    <h2><?php esc_html_e('Assets by Status', 'asset-manager'); ?></h2>
                    <div class="chart-container"><canvas id="assetStatusChart"></canvas></div>
                </div>
                <div class="dashboard-widget">
                    <h2><?php esc_html_e('Assets by User', 'asset-manager'); ?></h2>
                    <div class="chart-container"><canvas id="assetUserChart"></canvas></div>
                </div>
                <div class="dashboard-widget">
                    <h2><?php esc_html_e('Assets by Category', 'asset-manager'); ?></h2>
                    <div class="chart-container"><canvas id="assetCategoryChart"></canvas></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Retrieves and prepares data for the dashboard charts.
     *
     * @return array Data for dashboard charts.
     */
    public function get_dashboard_data_for_script() {
        $status_data = [];
        $user_data_counts = [];
        $category_data_counts = [];

        // Initialize status data with all possible statuses from meta field options
        // Note: Accessing status options this way is not ideal.
        // A better approach would be to have a dedicated function or constant for this.
        $meta_fields_handler = new Asset_Manager_Meta_Fields(); // Temporary instance
        // Assuming get_status_options is a public method in Asset_Manager_Meta_Fields
        // Or make $status_options property public and access directly
        $status_options = method_exists($meta_fields_handler, 'get_status_options') ? $meta_fields_handler->get_status_options() : ['Unassigned', 'Assigned', 'Returned', 'For Repair', 'Repairing', 'Archived', 'Disposed'];


        foreach ($status_options as $status_opt) {
            $status_data[$status_opt] = 0;
        }
        $status_data[__('Unknown', 'asset-manager')] = 0; // For assets with unrecognized status

        $all_categories = get_terms(['taxonomy' => ASSET_MANAGER_TAXONOMY, 'hide_empty' => false]);
        if (is_array($all_categories)) {
            foreach ($all_categories as $cat_term) {
                if (is_object($cat_term) && property_exists($cat_term, 'name')) {
                    $category_data_counts[esc_html($cat_term->name)] = 0;
                }
            }
        }
        $category_data_counts[__('Uncategorized', 'asset-manager')] = 0;
        $user_data_counts[__('Unassigned', 'asset-manager')] = 0;

        $assets_query = new WP_Query(['post_type' => ASSET_MANAGER_POST_TYPE, 'posts_per_page' => -1]);

        if ($assets_query->have_posts()) {
            while ($assets_query->have_posts()) {
                $assets_query->the_post();
                $post_id = get_the_ID();

                // Status Count
                $status_val = get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'status', true);
                $display_status = !empty($status_val) && in_array($status_val, $status_options, true) ? $status_val : __('Unknown', 'asset-manager');
                if (empty($status_val) && in_array('Unassigned', $status_options)) $display_status = 'Unassigned'; // Default to Unassigned if empty

                if (!isset($status_data[$display_status])) $status_data[$display_status] = 0;
                $status_data[$display_status]++;

                // User Count
                $user_id = get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'issued_to', true);
                $user_name_key = __('Unassigned', 'asset-manager');
                if ($user_id) {
                    $user = get_userdata($user_id);
                    $user_name_key = $user ? esc_html($user->display_name) : sprintf(__('Unknown User (ID: %d)', 'asset-manager'), $user_id);
                } elseif (get_post_meta($post_id, ASSET_MANAGER_META_PREFIX . 'status', true) === 'Unassigned' && empty($user_id)) {
                     $user_name_key = __('Needs User Assignment', 'asset-manager');
                }
                if (!isset($user_data_counts[$user_name_key])) $user_data_counts[$user_name_key] = 0;
                $user_data_counts[$user_name_key]++;

                // Category Count
                $terms = get_the_terms($post_id, ASSET_MANAGER_TAXONOMY);
                $category_name_key = __('Uncategorized', 'asset-manager');
                if (!empty($terms) && !is_wp_error($terms) && isset($terms[0]->name)) {
                    $category_name_key = esc_html($terms[0]->name);
                }
                if (!isset($category_data_counts[$category_name_key])) $category_data_counts[$category_name_key] = 0;
                $category_data_counts[$category_name_key]++;
            }
            wp_reset_postdata();
        }

        // Filter out zero counts for users and categories to keep charts cleaner, but keep all defined statuses
        return [
            'status'     => $status_data, // Keep all statuses, even with 0 count
            'users'      => array_filter($user_data_counts, function($count){ return $count > 0; }),
            'categories' => array_filter($category_data_counts, function($count){ return $count > 0; })
        ];
    }

    /**
     * Customizes the columns displayed in the repair request list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function custom_repair_admin_columns($columns) {
        $new_columns = [
            'cb'                    => $columns['cb'], // Checkbox
            'title'                 => __('Title', 'asset-manager'), // Auto-generated title
            'asset_linked'          => __('Linked Asset', 'asset-manager'),
            'repair_status'         => __('Status', 'asset-manager'),
            'requested_by'          => __('Requested By', 'asset-manager'),
            'date_requested_col'    => __('Date Requested', 'asset-manager'),
            'assigned_technician'   => __('Assigned Technician', 'asset-manager'),
            'date_started_col'      => __('Date Started', 'asset-manager'),
            'date_completed_col'    => __('Date Completed', 'asset-manager'),
            'date'                  => __('Date Created', 'asset-manager') // Original publish date
        ];
        // Remove default columns we don't need, like 'date' if we add our own date columns
        // Re-added 'date' column definition above, so no need to unset here unless you want to remove it.
        // unset($new_columns['date']);
        return $new_columns;
    }

    /**
     * Renders the content for custom columns in the repair request list table.
     *
     * @param string $column  The name of the column.
     * @param int    $post_id The ID of the current post.
     */
    public function custom_repair_admin_column_content($column, $post_id) {
        switch ($column) {
            case 'asset_linked':
                $asset_id = get_post_meta($post_id, ASSET_MANAGER_REPAIR_META_PREFIX . 'asset_id', true);
                if ($asset_id && get_post_type($asset_id) === ASSET_MANAGER_POST_TYPE) {
                    echo '<a href="' . esc_url(get_edit_post_link($asset_id)) . '">' . esc_html(get_the_title($asset_id)) . '</a>';
                } else {
                    echo '—';
                }
                break;
            case 'repair_status':
                $terms = get_the_terms($post_id, ASSET_MANAGER_REPAIR_TAXONOMY);
                if (!empty($terms) && !is_wp_error($terms)) {
                    // Assuming only one status term is assigned
                    $term = array_shift($terms);
                    echo esc_html($term->name);
                } else {
                    echo '—';
                }
                break;
            case 'requested_by':
                $user_id = get_post_meta($post_id, ASSET_MANAGER_REPAIR_META_PREFIX . 'requested_by', true);
                if ($user_id) {
                    $user = get_userdata($user_id);
                    echo esc_html($user ? $user->display_name : __('Unknown User', 'asset-manager'));
                } else {
                    echo '—';
                }
                break;
            case 'date_requested_col':
                $date_requested = get_post_meta($post_id, ASSET_MANAGER_REPAIR_META_PREFIX . 'date_requested', true);
                echo esc_html($date_requested ? date_i18n(get_option('date_format'), strtotime($date_requested)) : '—');
                break;
            case 'assigned_technician':
                 $user_id = get_post_meta($post_id, ASSET_MANAGER_REPAIR_META_PREFIX . 'assigned_technician', true);
                if ($user_id) {
                    $user = get_userdata($user_id);
                    echo esc_html($user ? $user->display_name : __('Unknown User', 'asset-manager'));
                } else {
                    echo '—';
                }
                break;
            case 'date_started_col':
                $date_started = get_post_meta($post_id, ASSET_MANAGER_REPAIR_META_PREFIX . 'date_started', true);
                echo esc_html($date_started ? date_i18n(get_option('date_format'), strtotime($date_started)) : '—');
                break;
            case 'date_completed_col':
                $date_completed = get_post_meta($post_id, ASSET_MANAGER_REPAIR_META_PREFIX . 'date_completed', true);
                echo esc_html($date_completed ? date_i18n(get_option('date_format'), strtotime($date_completed)) : '—');
                break;
             case 'date': // Content for the default date column (Date Created)
                 echo esc_html(get_the_date());
                 break;
        }
    }

     /**
     * Adds filter dropdowns (Linked Asset, Status, Requested By, Assigned Technician)
     * to the repair request list table.
     */
    public function add_repair_filters_to_admin_list() {
        global $typenow;

        if ($typenow === ASSET_MANAGER_REPAIR_POST_TYPE) {
            // Linked Asset Filter (Dropdown of Assets)
            $selected_asset_id = isset($_GET['repair_asset_filter']) ? absint($_GET['repair_asset_filter']) : 0;
             $assets = get_posts([
                'post_type'      => ASSET_MANAGER_POST_TYPE,
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'post_status'    => 'publish',
                'fields'         => 'id=>title', // Get ID and title
            ]);
            if (!empty($assets)) {
                echo "<select name='repair_asset_filter' id='repair_asset_filter' style='margin-left:5px;'>";
                echo "<option value='0'>" . esc_html__('All Assets', 'asset-manager') . "</option>";
                foreach ($assets as $asset_id => $asset_title) {
                    printf(
                        "<option value='%s'%s>%s</option>",
                        esc_attr($asset_id),
                        selected($selected_asset_id, $asset_id, false),
                        esc_html($asset_title)
                    );
                }
                echo "</select>";
            }


            // Status Filter (Dropdown of Repair Status Taxonomy Terms)
            $selected_status_slug = isset($_GET['repair_status_filter']) ? sanitize_text_field($_GET['repair_status_filter']) : '';
            wp_dropdown_categories([
                'show_option_all' => __('All Statuses', 'asset-manager'),
                'taxonomy'        => ASSET_MANAGER_REPAIR_TAXONOMY,
                'name'            => 'repair_status_filter', // Used in $_GET
                'orderby'         => 'name',
                'selected'        => $selected_status_slug,
                'hierarchical'    => false,
                'show_count'      => true,
                'hide_empty'      => true,
                'value_field'     => 'slug', // Filter by slug
            ]);

            // Requested By Filter (Dropdown of Users)
            $selected_requested_by_id = isset($_GET['repair_requested_by_filter']) ? absint($_GET['repair_requested_by_filter']) : 0;
             $users = get_users(['orderby' => 'display_name', 'fields' => ['ID', 'display_name']]);
             if (!empty($users)) {
                echo "<select name='repair_requested_by_filter' id='repair_requested_by_filter' style='margin-left:5px;'>";
                echo "<option value='0'>" . esc_html__('All Requesters', 'asset-manager') . "</option>";
                foreach ($users as $user) {
                    printf(
                        "<option value='%s'%s>%s</option>",
                        esc_attr($user->ID),
                        selected($selected_requested_by_id, $user->ID, false),
                        esc_html($user->display_name)
                    );
                }
                echo "</select>";
            }

             // Assigned Technician Filter (Dropdown of Users)
            $selected_assigned_tech_id = isset($_GET['repair_assigned_tech_filter']) ? absint($_GET['repair_assigned_tech_filter']) : 0;
             // Reuse the $users list
             if (!empty($users)) {
                echo "<select name='repair_assigned_tech_filter' id='repair_assigned_tech_filter' style='margin-left:5px;'>";
                echo "<option value='0'>" . esc_html__('All Technicians', 'asset-manager') . "</option>";
                foreach ($users as $user) {
                    printf(
                        "<option value='%s'%s>%s</option>",
                        esc_attr($user->ID),
                        selected($selected_assigned_tech_id, $user->ID, false),
                        esc_html($user->display_name)
                    );
                }
                echo "</select>";
            }
        }
    }

     /**
     * Modifies the main query based on selected filters from the repair request list table.
     *
     * @param WP_Query $query The main WordPress query object.
     */
    public function apply_repair_filters_to_query($query) {
        global $pagenow;
        // Get current post type from the query or from $_GET if available
        $current_post_type = $query->get('post_type');
        if (empty($current_post_type) && isset($_GET['post_type'])) {
            $current_post_type = sanitize_text_field($_GET['post_type']);
        }

        if (is_admin() && $pagenow === 'edit.php' && $current_post_type === ASSET_MANAGER_REPAIR_POST_TYPE && $query->is_main_query()) {
            $meta_query_arr = $query->get('meta_query') ?: [];
            if (!is_array($meta_query_arr)) {
                $meta_query_arr = [];
            }

            // Linked Asset Filter
            if (isset($_GET['repair_asset_filter']) && absint($_GET['repair_asset_filter']) > 0) {
                $asset_id = absint($_GET['repair_asset_filter']);
                 $meta_query_arr[] = [
                    'key'     => ASSET_MANAGER_REPAIR_META_PREFIX . 'asset_id',
                    'value'   => $asset_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ];
            }

            // Status Filter
            if (isset($_GET['repair_status_filter']) && !empty($_GET['repair_status_filter'])) {
                $status_slug = sanitize_text_field($_GET['repair_status_filter']);
                $tax_query = $query->get('tax_query') ?: [];
                if (!is_array($tax_query)) {
                    $tax_query = [];
                }
                $tax_query[] = [
                    'taxonomy' => ASSET_MANAGER_REPAIR_TAXONOMY,
                    'field'    => 'slug',
                    'terms'    => $status_slug,
                ];
                $query->set('tax_query', $tax_query);
            }

            // Requested By Filter
            if (isset($_GET['repair_requested_by_filter']) && absint($_GET['repair_requested_by_filter']) > 0) {
                $user_id = absint($_GET['repair_requested_by_filter']);
                 $meta_query_arr[] = [
                    'key'     => ASSET_MANAGER_REPAIR_META_PREFIX . 'requested_by',
                    'value'   => $user_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ];
            }

             // Assigned Technician Filter
            if (isset($_GET['repair_assigned_tech_filter']) && absint($_GET['repair_assigned_tech_filter']) > 0) {
                $user_id = absint($_GET['repair_assigned_tech_filter']);
                 $meta_query_arr[] = [
                    'key'     => ASSET_MANAGER_REPAIR_META_PREFIX . 'assigned_technician',
                    'value'   => $user_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ];
            }


            if (!empty($meta_query_arr)) {
                // Ensure 'relation' is set if there's more than one meta query clause
                if (count($meta_query_arr) > 1 && !isset($meta_query_arr['relation'])) {
                     $meta_query_arr['relation'] = 'AND';
                }
                $query->set('meta_query', $meta_query_arr);
            }
        }
    }

     /**
     * Modifies the WHERE clause for repair request admin search to include custom fields and taxonomy.
     *
     * @param string   $search Original WHERE clause generated by WordPress search.
     * @param WP_Query $query  The query object.
     * @return string Modified WHERE clause.
     */
    public function extend_repair_admin_search($search, $query) {
        global $wpdb;

        // Ensure we are on the correct admin screen and it's the main query with a search term
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== ASSET_MANAGER_REPAIR_POST_TYPE || empty($query->get('s'))) {
            return $search;
        }

        $search_term = $query->get('s');
        $search_term_like = '%' . $wpdb->esc_like($search_term) . '%';

        // Remove the default WordPress search clause
        $search = '';

        $search_conditions = [];

        // 1. Search in custom meta fields
        $meta_keys_to_search = [
            ASSET_MANAGER_REPAIR_META_PREFIX . 'issue_description',
            ASSET_MANAGER_REPAIR_META_PREFIX . 'date_requested',
            ASSET_MANAGER_REPAIR_META_PREFIX . 'date_started',
            ASSET_MANAGER_REPAIR_META_PREFIX . 'date_completed',
            ASSET_MANAGER_REPAIR_META_PREFIX . 'estimated_cost',
            ASSET_MANAGER_REPAIR_META_PREFIX . 'actual_cost',
            ASSET_MANAGER_REPAIR_META_PREFIX . 'parts_used',
            ASSET_MANAGER_REPAIR_META_PREFIX . 'notes_log', // Search within note content (requires careful handling)
        ];

        foreach ($meta_keys_to_search as $meta_key) {
             // Searching in serialized array meta (like notes log) requires a different approach
             if ($meta_key === ASSET_MANAGER_REPAIR_META_PREFIX . 'notes_log') {
                 // This is a simplified search for notes log. A more robust solution
                 // might involve a custom database query or iterating through notes after fetching posts.
                 // For basic LIKE search on the serialized string:
                 $search_conditions[] = $wpdb->prepare(
                    "($wpdb->postmeta.meta_key = %s AND $wpdb->postmeta.meta_value LIKE %s)",
                    $meta_key,
                    $search_term_like
                 );
             } else {
                $search_conditions[] = $wpdb->prepare(
                    "($wpdb->postmeta.meta_key = %s AND $wpdb->postmeta.meta_value LIKE %s)",
                    $meta_key,
                    $search_term_like
                );
             }
        }

        // 2. Search in Linked Asset Title
        // Find Asset IDs matching the search term in their title
        $asset_query_args = [
            'post_type'      => ASSET_MANAGER_POST_TYPE,
            's'              => $search_term, // Use default search for asset title
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];
        $matching_asset_ids = get_posts($asset_query_args);

        if (!empty($matching_asset_ids)) {
             // Add a condition to find repair requests linked to these asset IDs
             $asset_ids_placeholder = implode(',', array_fill(0, count($matching_asset_ids), '%d'));
             $search_conditions[] = $wpdb->prepare(
                "($wpdb->postmeta.meta_key = %s AND $wpdb->postmeta.meta_value IN ($asset_ids_placeholder))",
                array_merge([ASSET_MANAGER_REPAIR_META_PREFIX . 'asset_id'], $matching_asset_ids)
             );
        }


        // 3. Search in "Requested By" and "Assigned Technician" user display names
        // Find user IDs matching the search term
        $user_query_args = [
            'search'         => '*' . esc_attr($search_term) . '*',
            'search_columns' => ['user_login', 'user_nicename', 'user_email', 'display_name'],
            'fields'         => 'ID',
            'blog_id'        => 0,
        ];
        $matching_user_ids = get_users($user_query_args);

        if (!empty($matching_user_ids)) {
             // Add conditions for both requested_by and assigned_technician meta keys
             $user_ids_placeholder = implode(',', array_fill(0, count($matching_user_ids), '%d'));
             $search_conditions[] = $wpdb->prepare(
                "(" . $wpdb->postmeta . ".meta_key = %s AND " . $wpdb->postmeta . ".meta_value IN (" . $user_ids_placeholder . ")) OR (" . $wpdb->postmeta . ".meta_key = %s AND " . $wpdb->postmeta . ".meta_value IN (" . $user_ids_placeholder . "))",
                array_merge([ASSET_MANAGER_REPAIR_META_PREFIX . 'requested_by'], $matching_user_ids, [ASSET_MANAGER_REPAIR_META_PREFIX . 'assigned_technician'], $matching_user_ids)
             );
        }

        // 4. Search in Repair Status name
        // Find term IDs matching the search term
         $terms = get_terms([
            'taxonomy'   => ASSET_MANAGER_REPAIR_TAXONOMY,
            'hide_empty' => false,
            'name__like' => $search_term,
            'fields'     => 'term_id',
        ]);

        if (!empty($terms) && !is_wp_error($terms)) {
            // Add a condition to find repair requests associated with these term IDs
            $term_ids_placeholder = implode(',', array_fill(0, count($terms), '%d'));
             $search_conditions[] = $wpdb->prepare(
                "($wpdb->term_relationships.term_taxonomy_id IN (SELECT tt.term_taxonomy_id FROM $wpdb->term_taxonomy tt JOIN $wpdb->terms t ON tt.term_id = t.term_id WHERE t.term_id IN ($term_ids_placeholder) AND tt.taxonomy = %s))",
                array_merge($terms, [ASSET_MANAGER_REPAIR_TAXONOMY])
             );
        }

         // 5. Include search in post title (the repair request title)
         $search_conditions[] = $wpdb->prepare("($wpdb->posts.post_title LIKE %s)", $search_term_like);


        // Combine all conditions with OR
        if (!empty($search_conditions)) {
            $search = " AND (" . implode(" OR ", $search_conditions) . ")";
        }

        return $search;
    }

     /**
     * Modifies the JOIN clause for repair request custom admin search.
     * Joins necessary tables (postmeta, term_relationships, term_taxonomy, terms)
     * only when a search term is present for the repair request post type.
     *
     * @param string   $join  Original JOIN clause.
     * @param WP_Query $query The query object.
     * @return string Modified JOIN clause.
     */
    public function extend_repair_admin_search_join($join, $query) {
        global $wpdb;

        // Ensure we are on the correct admin screen and it's the main query with a search term
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== ASSET_MANAGER_REPAIR_POST_TYPE || empty($query->get('s'))) {
            return $join;
        }

        // Join postmeta table for searching in meta fields (including linked asset ID and user IDs)
        // Check if postmeta table is already joined to avoid duplicates
        if (strpos($join, $wpdb->postmeta) === false) {
            $join .= " LEFT JOIN $wpdb->postmeta ON ($wpdb->posts.ID = $wpdb->postmeta.post_id) ";
        }
        // Join term tables for searching status names
         // Check if term tables are already joined
        if (strpos($join, $wpdb->term_relationships) === false) {
            $join .= " LEFT JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) ";
        }
        if (strpos($join, $wpdb->term_taxonomy) === false) {
             $join .= " LEFT JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) ";
        }
        if (strpos($join, $wpdb->terms) === false) {
            $join .= " LEFT JOIN $wpdb->terms ON ($wpdb->term_taxonomy.term_id = $wpdb->terms.term_id) ";
        }

         // Join posts table again for searching in linked asset title
         // Use a unique alias to avoid conflicts and check if already joined
        if (strpos($join, "{$wpdb->posts} AS linked_asset_posts") === false) {
             $join .= " LEFT JOIN {$wpdb->posts} AS linked_asset_posts ON ({$wpdb->postmeta}.meta_value = linked_asset_posts.ID AND {$wpdb->postmeta}.meta_key = '" . ASSET_MANAGER_REPAIR_META_PREFIX . "asset_id') ";
        }


        return $join;
    }

    /**
     * Adds DISTINCT to the query for repair request custom admin search to avoid duplicate results.
     * Applies only when a search term is present for the repair request post type.
     *
     * @param string   $distinct Original DISTINCT clause.
     * @param WP_Query $query    The query object.
     * @return string Modified DISTINCT clause.
     */
    public function extend_repair_admin_search_distinct($distinct, $query) {
        // Ensure we are on the correct admin screen and it's the main query with a search term
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== ASSET_MANAGER_REPAIR_POST_TYPE || empty($query->get('s'))) {
            return $distinct;
        }

        return 'DISTINCT';
    }


}
