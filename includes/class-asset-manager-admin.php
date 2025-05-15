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
        // Admin list table customizations
        add_filter('manage_' . ASSET_MANAGER_POST_TYPE . '_posts_columns', [$this, 'custom_admin_columns']);
        add_action('manage_' . ASSET_MANAGER_POST_TYPE . '_posts_custom_column', [$this, 'custom_admin_column_content'], 10, 2);
        
        // Admin list table filters
        add_action('restrict_manage_posts', [$this, 'add_filters_to_admin_list']);
        add_action('pre_get_posts', [$this, 'apply_filters_to_query']);
        
        // Admin list table search enhancements
        add_action('pre_get_posts', [$this, 'extend_search_query']);

        // Admin pages
        add_action('admin_menu', [$this, 'register_admin_subpages']);
        add_action('admin_post_am_export_assets_pdf_action', [$this, 'handle_export_assets_pdf']);
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
     * Modifies the main query based on selected filters from the admin list table.
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
                if (count($meta_query_arr) > 1 && !isset($meta_query_arr['relation'])) {
                    $meta_query_arr['relation'] = 'AND';
                }
                $query->set('meta_query', $meta_query_arr);
            }
        }
    }

    /**
     * Extends the search functionality for assets to include custom fields.
     *
     * @param WP_Query $query The main WordPress query object.
     */
    public function extend_search_query($query) {
        global $pagenow;
        $search_term = $query->get('s');
        $current_post_type = $query->get('post_type');
         if (empty($current_post_type) && isset($_GET['post_type'])) { // Ensure we check GET if query var is empty
            $current_post_type = sanitize_text_field($_GET['post_type']);
        }


        // Apply only on the asset list admin page during a search, or if it's a global search.
        if (is_admin() && $query->is_main_query() && $pagenow === 'edit.php' && !empty($search_term)) {
            // If the search is specifically for assets OR it's a global admin search (post_type is empty)
            if ($current_post_type === ASSET_MANAGER_POST_TYPE || empty($current_post_type)) {
                
                // Store search parameters for use in subsequent filters
                $query->set('asset_manager_custom_search', true);
                $query->set('asset_manager_search_term', $search_term);

                add_filter('posts_join', [$this, 'search_join_clause'], 10, 2);
                add_filter('posts_where', [$this, 'search_where_clause'], 10, 2);
                add_filter('posts_distinct', [$this, 'search_distinct_clause'], 10, 2);
            }
        }
    }

    /**
     * Modifies the JOIN clause for custom search.
     * @param string   $join  Original JOIN clause.
     * @param WP_Query $query The query object.
     * @return string Modified JOIN clause.
     */
    public function search_join_clause($join, $query) {
        global $wpdb;
        if ($query->get('asset_manager_custom_search')) {
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
        }
        return $join;
    }

    /**
     * Modifies the WHERE clause for custom search.
     * @param string   $where Original WHERE clause.
     * @param WP_Query $query The query object.
     * @return string Modified WHERE clause.
     */
    public function search_where_clause($where, $query) {
        global $wpdb;
        if ($query->get('asset_manager_custom_search')) {
            $search_term = $query->get('asset_manager_search_term');
            $search_term_like = '%' . $wpdb->esc_like($search_term) . '%';

            $meta_keys_to_search = [
                ASSET_MANAGER_META_PREFIX . 'asset_tag',
                ASSET_MANAGER_META_PREFIX . 'model',
                ASSET_MANAGER_META_PREFIX . 'serial_number',
                ASSET_MANAGER_META_PREFIX . 'brand',
                ASSET_MANAGER_META_PREFIX . 'location',
                ASSET_MANAGER_META_PREFIX . 'status',
                ASSET_MANAGER_META_PREFIX . 'date_purchased',
            ];

            $custom_search_conditions = [];
            // Add conditions for meta keys
            foreach ($meta_keys_to_search as $meta_key) {
                $custom_search_conditions[] = $wpdb->prepare(
                    "($wpdb->postmeta.meta_key = %s AND $wpdb->postmeta.meta_value LIKE %s)",
                    $meta_key,
                    $search_term_like
                );
            }

            // Add condition for category name
            $custom_search_conditions[] = $wpdb->prepare(
                "($wpdb->term_taxonomy.taxonomy = %s AND $wpdb->terms.name LIKE %s)",
                ASSET_MANAGER_TAXONOMY,
                $search_term_like
            );
            
            // Add condition for "Issued To" user display name
            $user_query_args = ['search' => "*{$search_term}*", 'search_columns' => ['user_login', 'user_nicename', 'user_email', 'display_name'], 'fields' => 'ID'];
            $matching_user_ids = get_users($user_query_args);
            if (!empty($matching_user_ids)) {
                $user_id_placeholders = implode(',', array_map('intval', $matching_user_ids));
                if (!empty($user_id_placeholders)) { // Ensure not empty string if no users found
                    $custom_search_conditions[] = $wpdb->prepare(
                        "($wpdb->postmeta.meta_key = %s AND $wpdb->postmeta.meta_value IN ($user_id_placeholders))",
                        ASSET_MANAGER_META_PREFIX . 'issued_to'
                    );
                }
            }

            if (!empty($custom_search_conditions)) {
                // $where typically starts with " AND (((wp_posts.post_title LIKE %s%)) OR ((wp_posts.post_content LIKE %s%)))"
                // We want to OR our conditions with the default title/content search.
                // The final query should be for our post type only if it's a global search.
                
                $post_type_clause = $wpdb->prepare("AND $wpdb->posts.post_type = %s ", ASSET_MANAGER_POST_TYPE);
                
                // Remove existing post type restriction if it's too broad, we'll add our specific one.
                $where = preg_replace( "/\s*AND\s*$wpdb->posts.post_type\s*=\s*\'[a-zA-Z0-9_-]+\'\s*/", "", $where );


                // Rebuild the WHERE clause: ( (original title/content search) OR (our custom conditions) ) AND post_type = 'asset'
                $where = " AND ( (" . substr(trim($where), 3) . ") OR (" . implode(' OR ', $custom_search_conditions) . ") ) $post_type_clause";
            }
            
            // Clean up query vars and remove filters to prevent interference with other queries
            $query->set('asset_manager_custom_search', false);
            $query->set('asset_manager_search_term', null);
            remove_filter('posts_join', [$this, 'search_join_clause'], 10);
            remove_filter('posts_where', [$this, 'search_where_clause'], 10);
            remove_filter('posts_distinct', [$this, 'search_distinct_clause'], 10);
        }
        return $where;
    }

    /**
     * Adds DISTINCT to the query for custom search to avoid duplicate results.
     * @param string   $distinct Original DISTINCT clause.
     * @param WP_Query $query    The query object.
     * @return string Modified DISTINCT clause.
     */
    public function search_distinct_clause($distinct, $query) {
        if ($query->get('asset_manager_custom_search')) {
            return 'DISTINCT';
        }
        return $distinct;
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
                $category_name = (!empty($categories) && !is_wp_error($categories)) ? esc_html(implode(', ', wp_list_pluck($categories, 'name'))) : '—';
                $html .= '<td>' . $category_name . '</td>';
                
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
        $meta_fields_handler = new Asset_Manager_Meta_Fields(); // Temporary instance to access status options
        // This is not ideal. Status options should be accessible more globally or passed.
        // For now, let's hardcode them here or consider a better way to share.
        $status_options = ['Unassigned', 'Assigned', 'Returned', 'For Repair', 'Repairing', 'Archived', 'Disposed']; 
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
                } elseif ($status_val !== 'Unassigned' && empty($user_id)) {
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
}
