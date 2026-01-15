<?php
/**
 * Brand Manager - Hide/Show Brands from Frontend
 *
 * Provides admin interface to quickly hide or show specific brands
 * without deleting the products from the database
 *
 * @package Harry_WheelPros_Importer
 * @since 1.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HP_WheelPros_Brand_Manager {

    /**
     * Track if already initialized
     */
    private static $initialized = false;

    /**
     * Initialize
     * Note: This runs on both admin and frontend for brand filtering to work
     */
    public static function init() {
        // Prevent double initialization
        if ( self::$initialized ) {
            return;
        }
        self::$initialized = true;

        // Add admin menu - only in admin context
        if ( is_admin() ) {
            add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 30 );

            // AJAX handlers - only need to be registered in admin
            add_action( 'wp_ajax_hp_update_brand_visibility', array( __CLASS__, 'ajax_update_brand_visibility' ) );
            add_action( 'wp_ajax_hp_bulk_brand_action', array( __CLASS__, 'ajax_bulk_brand_action' ) );
        }

        // Filter queries - runs on FRONTEND only (not admin)
        // These hooks filter out hidden brands from public-facing queries
        if ( ! is_admin() ) {
            add_action( 'pre_get_posts', array( __CLASS__, 'filter_hidden_brands' ), 5 );
            add_filter( 'posts_where', array( __CLASS__, 'filter_hidden_brands_sql' ), 10, 2 );
        }
    }

    /**
     * Add admin menu page
     */
    public static function add_menu_page() {
        add_submenu_page(
            'hp-wheelpros',
            'Manage Brands',
            'Manage Brands',
            'manage_options',
            'wheelpros-brand-manager',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Render admin page
     */
    public static function render_page() {
        // Get all unique brands from posts
        // Note: Brand is stored as 'hp_brand' meta key (with hp_ prefix like other fields)
        global $wpdb;
        $brands = $wpdb->get_results(
            "SELECT DISTINCT meta_value as brand, COUNT(*) as count
             FROM {$wpdb->postmeta}
             WHERE meta_key = 'hp_brand'
             AND meta_value != ''
             GROUP BY meta_value
             ORDER BY meta_value ASC"
        );

        $hidden_brands = get_option( 'hp_hidden_brands', array() );

        ?>
        <div class="wrap">
            <h1>Manage Brands</h1>
            <p>Control which brands appear on your site. Hidden brands won't show in search results or listings, but remain in the database.</p>
            <p><strong>🔄 Dynamic Brand Detection:</strong> <?php echo self::get_brand_info(); ?></p>

            <div class="hp-brand-manager">
                <div class="hp-brand-actions" style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
                    <h3 style="margin-top: 0;">Brand Visibility</h3>
                    <p><strong>Checked = Hidden</strong> | Unchecked = Visible</p>

                    <div style="margin-bottom: 15px;">
                        <button type="button" class="button" id="hp-select-all">Hide All</button>
                        <button type="button" class="button" id="hp-deselect-all">Show All</button>
                        <button type="button" class="button button-primary" id="hp-save-brands">Save Changes</button>
                        <span class="spinner" style="float: none; margin: 0 10px;"></span>
                    </div>

                    <hr>

                    <h3>Bulk Actions for Hidden Brands</h3>
                    <p>These actions only affect currently hidden brands:</p>
                    <div>
                        <button type="button" class="button" id="hp-bulk-draft">Convert Hidden to Drafts</button>
                        <button type="button" class="button button-primary" id="hp-bulk-publish" style="margin-left: 10px;">Restore Hidden to Published</button>
                        <button type="button" class="button button-danger" id="hp-bulk-delete" style="margin-left: 10px;">Delete Hidden Products</button>
                        <span class="spinner" id="hp-bulk-spinner" style="float: none; margin: 0 10px;"></span>
                    </div>
                    <p class="description">
                        <strong>Convert to Drafts:</strong> Hidden brand products become drafts (easily restorable)<br>
                        <strong>Restore to Published:</strong> Changes hidden brand products from draft back to published<br>
                        <strong>Delete:</strong> Permanently removes hidden brand products from database
                    </p>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">
                                Hide
                            </th>
                            <th>Brand Name</th>
                            <th style="width: 120px; text-align: center;">Product Count</th>
                            <th style="width: 120px; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $brands ) ): ?>
                            <tr>
                                <td colspan="4">No brands found. Import products first.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ( $brands as $brand ): ?>
                                <?php
                                $is_hidden = in_array( $brand->brand, $hidden_brands );
                                $status_class = $is_hidden ? 'hp-status-hidden' : 'hp-status-visible';
                                $status_text = $is_hidden ? 'Hidden' : 'Visible';
                                ?>
                                <tr>
                                    <td style="text-align: center;">
                                        <input
                                            type="checkbox"
                                            class="hp-brand-checkbox"
                                            value="<?php echo esc_attr( $brand->brand ); ?>"
                                            <?php checked( $is_hidden ); ?>
                                        />
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( $brand->brand ); ?></strong>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php echo number_format( $brand->count ); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="<?php echo esc_attr( $status_class ); ?>" style="
                                            display: inline-block;
                                            padding: 4px 12px;
                                            border-radius: 3px;
                                            font-weight: bold;
                                            <?php echo $is_hidden ? 'background: #dc3232; color: #fff;' : 'background: #46b450; color: #fff;'; ?>
                                        ">
                                            <?php echo esc_html( $status_text ); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <style>
        .hp-status-hidden {
            color: #d63638;
            font-weight: 600;
        }
        .hp-status-visible {
            color: #00a32a;
            font-weight: 600;
        }
        .hp-brand-manager table {
            margin-top: 10px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Select all (hide all)
            $('#hp-select-all').on('click', function() {
                $('.hp-brand-checkbox').prop('checked', true);
            });

            // Deselect all (show all)
            $('#hp-deselect-all').on('click', function() {
                $('.hp-brand-checkbox').prop('checked', false);
            });

            // Save changes
            $('#hp-save-brands').on('click', function() {
                var $btn = $(this);
                var $spinner = $('.spinner').first();

                // Get all checked brands (these are the ones to hide)
                var hiddenBrands = [];
                $('.hp-brand-checkbox:checked').each(function() {
                    hiddenBrands.push($(this).val());
                });

                $btn.prop('disabled', true);
                $spinner.addClass('is-active');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'hp_update_brand_visibility',
                        nonce: '<?php echo wp_create_nonce( 'hp_brand_visibility' ); ?>',
                        hidden_brands: hiddenBrands
                    },
                    success: function(response) {
                        if (response.success) {
                            // Reload page to show updated status
                            location.reload();
                        } else {
                            alert('Error: ' + (response.data || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('Error updating brand visibility');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    }
                });
            });

            // Convert hidden brands to drafts
            $('#hp-bulk-draft').on('click', function() {
                if (!confirm('Convert all hidden brand products to drafts? This will make them unpublished but not deleted.')) {
                    return;
                }

                bulkAction('draft', $(this));
            });

            // Restore hidden brands to published
            $('#hp-bulk-publish').on('click', function() {
                if (!confirm('Restore all hidden brand products to published status?')) {
                    return;
                }

                bulkAction('publish', $(this));
            });

            // Delete hidden brands
            $('#hp-bulk-delete').on('click', function() {
                if (!confirm('⚠️ WARNING: This will PERMANENTLY DELETE all products from hidden brands!\n\nAre you absolutely sure?')) {
                    return;
                }

                if (!confirm('This action cannot be undone. Delete all hidden brand products?')) {
                    return;
                }

                bulkAction('delete', $(this));
            });

            function bulkAction(action, $btn) {
                var $spinner = $('#hp-bulk-spinner');

                $btn.prop('disabled', true);
                $spinner.addClass('is-active');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'hp_bulk_brand_action',
                        nonce: '<?php echo wp_create_nonce( 'hp_bulk_brand_action' ); ?>',
                        bulk_action: action
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + (response.data || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('Error performing bulk action');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    }
                });
            }
        });
        </script>
        <?php
    }

    /**
     * AJAX: Update brand visibility
     */
    public static function ajax_update_brand_visibility() {
        check_ajax_referer( 'hp_brand_visibility', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $hidden_brands = isset( $_POST['hidden_brands'] ) ? array_map( 'sanitize_text_field', $_POST['hidden_brands'] ) : array();

        update_option( 'hp_hidden_brands', $hidden_brands );

        // Clear the shortcode cache so changes appear immediately
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hp_wheel_display_styles%' OR option_name LIKE '_transient_timeout_hp_wheel_display_styles%'" );
        wp_cache_flush();

        wp_send_json_success( array(
            'message' => 'Brand visibility updated',
            'hidden_count' => count( $hidden_brands )
        ) );
    }

    /**
     * AJAX: Bulk actions for hidden brands
     */
    public static function ajax_bulk_brand_action() {
        check_ajax_referer( 'hp_bulk_brand_action', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $action = sanitize_text_field( $_POST['bulk_action'] ?? '' );
        $hidden_brands = get_option( 'hp_hidden_brands', array() );

        if ( empty( $hidden_brands ) ) {
            wp_send_json_error( 'No hidden brands to process' );
        }

        global $wpdb;

        // Get all post IDs for hidden brands
        $placeholders = implode( ',', array_fill( 0, count( $hidden_brands ), '%s' ) );
        $post_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = 'hp_brand'
             AND meta_value IN ($placeholders)",
            ...$hidden_brands
        ) );

        if ( empty( $post_ids ) ) {
            wp_send_json_error( 'No products found for hidden brands' );
        }

        $count = count( $post_ids );

        if ( $action === 'draft' ) {
            // Convert to drafts
            foreach ( $post_ids as $post_id ) {
                wp_update_post( array(
                    'ID' => $post_id,
                    'post_status' => 'draft'
                ) );
            }

            wp_send_json_success( array(
                'message' => "$count products from hidden brands converted to drafts"
            ) );

        } elseif ( $action === 'publish' ) {
            // Restore to published
            foreach ( $post_ids as $post_id ) {
                wp_update_post( array(
                    'ID' => $post_id,
                    'post_status' => 'publish'
                ) );
            }

            wp_send_json_success( array(
                'message' => "$count products from hidden brands restored to published"
            ) );

        } elseif ( $action === 'delete' ) {
            // Permanently delete
            foreach ( $post_ids as $post_id ) {
                wp_delete_post( $post_id, true );
            }

            wp_send_json_success( array(
                'message' => "$count products from hidden brands permanently deleted"
            ) );

        } else {
            wp_send_json_error( 'Invalid action' );
        }
    }

    /**
     * Filter hidden brands from frontend queries
     */
    public static function filter_hidden_brands( $query ) {
        // Skip admin area
        if ( is_admin() ) {
            return;
        }

        // Only filter hp_wheel post type queries
        $post_type = $query->get( 'post_type' );
        if ( $post_type !== 'hp_wheel' && ! is_post_type_archive( 'hp_wheel' ) && ! $query->is_main_query() ) {
            return;
        }

        $hidden_brands = get_option( 'hp_hidden_brands', array() );

        if ( empty( $hidden_brands ) ) {
            return;
        }

        // Add meta query to exclude hidden brands
        $meta_query = $query->get( 'meta_query' );
        if ( ! is_array( $meta_query ) ) {
            $meta_query = array();
        }

        $meta_query[] = array(
            'key' => 'hp_brand',
            'value' => $hidden_brands,
            'compare' => 'NOT IN',
        );

        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Filter hidden brands via SQL for more comprehensive coverage
     * This catches queries that don't go through pre_get_posts
     */
    public static function filter_hidden_brands_sql( $where, $query ) {
        global $wpdb;

        // Only on frontend
        if ( is_admin() ) {
            return $where;
        }

        // Check if this is an hp_wheel query
        $post_type = $query->get( 'post_type' );
        if ( $post_type !== 'hp_wheel' && ! is_post_type_archive( 'hp_wheel' ) ) {
            return $where;
        }

        $hidden_brands = get_option( 'hp_hidden_brands', array() );

        if ( empty( $hidden_brands ) ) {
            return $where;
        }

        // Create NOT IN clause for SQL - properly escape each brand
        $escaped_brands = array_map( 'esc_sql', $hidden_brands );
        $brand_list = "'" . implode( "','", $escaped_brands ) . "'";

        $where .= " AND {$wpdb->posts}.ID NOT IN (
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = 'hp_brand'
            AND meta_value IN ($brand_list)
        )";

        return $where;
    }

    /**
     * Check if a brand should be imported
     * Call this during import to skip hidden brands
     *
     * @param string $brand Brand name
     * @return bool True if brand should be imported, false if hidden
     */
    public static function should_import_brand( $brand ) {
        $hidden_brands = get_option( 'hp_hidden_brands', array() );
        return ! in_array( $brand, $hidden_brands );
    }

    /**
     * Get helper text about dynamic brand detection
     *
     * @return string Information about how brands are detected
     */
    public static function get_brand_info() {
        return 'Brands are automatically detected from imported products. When you import a CSV with new brands, they will automatically appear in this list alphabetically. The brand list is dynamic and updates based on what products exist in your database.';
    }

    /**
     * CENTRALIZED: Get all visible brands (published posts, excluding hidden brands)
     * Use this everywhere you need brand list for dropdowns/filters
     *
     * @return array Array of brand names
     */
    public static function get_visible_brands() {
        global $wpdb;

        $hidden_brands = get_option( 'hp_hidden_brands', array() );

        // Build query - use hp_brand meta key (with prefix like other fields)
        $query = "SELECT DISTINCT pm.meta_value
                  FROM {$wpdb->postmeta} pm
                  INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                  WHERE pm.meta_key = 'hp_brand'
                  AND pm.meta_value != ''
                  AND p.post_type = 'hp_wheel'
                  AND p.post_status = 'publish'";
        
        // Exclude hidden brands
        if ( ! empty( $hidden_brands ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $hidden_brands ), '%s' ) );
            $query = $wpdb->prepare( $query . " AND pm.meta_value NOT IN ($placeholders)", ...$hidden_brands );
        }
        
        $query .= " ORDER BY pm.meta_value";
        
        $result = $wpdb->get_col( $query );
        
        // DEBUG: Temporarily log what we found
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'get_visible_brands() query: ' . $query );
            error_log( 'Hidden brands: ' . print_r( $hidden_brands, true ) );
            error_log( 'Result count: ' . count( $result ) );
            error_log( 'Results: ' . print_r( $result, true ) );
        }
        
        return $result;
    }

    /**
     * CENTRALIZED: Build SQL WHERE clause to exclude hidden brands
     * Use this in custom queries that need to filter out hidden brands
     *
     * @param bool $include_and Whether to include "AND" at the start (default true)
     * @return string SQL WHERE clause fragment
     */
    public static function get_hidden_brands_sql_filter( $include_and = true ) {
        global $wpdb;
        
        $hidden_brands = get_option( 'hp_hidden_brands', array() );
        
        if ( empty( $hidden_brands ) ) {
            return '';
        }
        
        $placeholders = implode( ',', array_fill( 0, count( $hidden_brands ), '%s' ) );
        $where = "p.ID NOT IN (
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = 'hp_brand'
            AND meta_value IN ($placeholders)
        )";

        $where = $wpdb->prepare( $where, ...$hidden_brands );

        return ( $include_and ? ' AND ' : '' ) . $where;
    }
}

// Initialize
HP_WheelPros_Brand_Manager::init();
