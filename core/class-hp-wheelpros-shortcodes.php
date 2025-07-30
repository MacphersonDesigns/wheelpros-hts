<?php
/**
 * Shortcodes for WheelPros importer.
 *
 * Defines a [hp_wheels] shortcode that outputs a list of wheel posts with
 * optional filters for brand, finish, and display style.  Filtering is
 * performed using query parameters (?brand=..., ?finish=..., ?style=...),
 * which are sanitized before being used         ob_start();
        ?>
        <form method="get" class="hp-wheels-filter" action="" style="margin-bottom:2em; padding:20px; background:#f8f9fa; border-radius:8px; display:flex; flex-wrap:wrap; gap:15px; align-items:end;"><?phpa WP_Query.  The output is
 * intentionally simple; themes can style the generated markup as needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HP_WheelPros_Shortcodes {
    /**
     * Register the shortcode hooks.
     */
    public static function register() {
        add_shortcode( 'hp_wheels', array( __CLASS__, 'render_wheels_shortcode' ) );

        // AJAX handlers for wheel variations
        add_action( 'wp_ajax_hp_get_wheel_variations', array( __CLASS__, 'ajax_get_wheel_variations' ) );
        add_action( 'wp_ajax_nopriv_hp_get_wheel_variations', array( __CLASS__, 'ajax_get_wheel_variations' ) );
    }

    /**
     * AJAX handler to get all variations for a specific display style.
     */
    public static function ajax_get_wheel_variations() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'hp_wheel_variations' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }

        $display_style_no = sanitize_text_field( $_POST['style_slug'] );

        if ( empty( $display_style_no ) ) {
            wp_send_json_error( 'Display style number required' );
        }

        // Get all wheels for this DisplayStyleNo
        $wheel_query_args = array(
            'post_type'      => 'hp_wheel',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => 'hp_display_style_no',
                    'value'   => $display_style_no,
                    'compare' => '=',
                ),
            ),
            'orderby'        => 'meta_value',
            'meta_key'       => 'hp_part_number',
            'order'          => 'ASC',
        );

        $wheel_query = new WP_Query( $wheel_query_args );

        if ( ! $wheel_query->have_posts() ) {
            wp_send_json_error( 'No wheels found for this style' );
        }

        $options = get_option( 'hp_wheelpros_options' );
        $call_phone = isset( $options['call_phone'] ) ? $options['call_phone'] : '';
        $quote_url = isset( $options['quote_url'] ) ? $options['quote_url'] : '';

        ob_start();
        ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <?php while ( $wheel_query->have_posts() ) : $wheel_query->the_post();
                $post_id = get_the_ID();
                $image_url = get_post_meta( $post_id, 'hp_image_url', true );
                $part_number = get_post_meta( $post_id, 'hp_part_number', true );
                $part_description = get_post_meta( $post_id, 'hp_part_description', true );
                $size = get_post_meta( $post_id, 'hp_size', true );
                $brand = get_post_meta( $post_id, 'hp_brand', true );
                $finish = get_post_meta( $post_id, 'hp_finish', true );
                $offset_val = get_post_meta( $post_id, 'hp_offset', true );
                $bolt = get_post_meta( $post_id, 'hp_bolt_pattern', true );
                $center_bore = get_post_meta( $post_id, 'hp_center_bore', true );
                $load_rating = get_post_meta( $post_id, 'hp_load_rating', true );
                $msrp = get_post_meta( $post_id, 'hp_msrp_usd', true );
                $map = get_post_meta( $post_id, 'hp_map_usd', true );
                $qoh = get_post_meta( $post_id, 'hp_total_qoh', true );
                ?>
                <div style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: #fff; transition: transform 0.2s ease, box-shadow 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                    <?php if ( $image_url ) : ?>
                        <div style="text-align: center; margin-bottom: 15px;">
                            <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $part_number ); ?>" style="max-width: 100%; height: 180px; object-fit: contain; border-radius: 4px;">
                        </div>
                    <?php endif; ?>

                    <h4 style="margin: 0 0 8px 0; font-size: 1.1em; font-weight: 600; color: #2c3e50;">
                        <?php echo esc_html( $part_number ); ?>
                    </h4>

                    <?php if ( $part_description ) : ?>
                        <p style="margin: 0 0 10px 0; font-size: 0.9em; color: #555; line-height: 1.4;">
                            <?php echo esc_html( $part_description ); ?>
                        </p>
                    <?php endif; ?>

                    <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 0.85em;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <div><strong>Brand:</strong><br><?php echo esc_html( $brand ); ?></div>
                            <div><strong>Finish:</strong><br><?php echo esc_html( $finish ); ?></div>
                            <div><strong>Size:</strong><br><?php echo esc_html( $size ); ?></div>
                            <div><strong>Bolt:</strong><br><?php echo esc_html( $bolt ); ?></div>
                            <?php if ( $offset_val ) : ?>
                                <div><strong>Offset:</strong><br><?php echo esc_html( $offset_val ); ?></div>
                            <?php endif; ?>
                            <?php if ( $center_bore ) : ?>
                                <div><strong>Center Bore:</strong><br><?php echo esc_html( $center_bore ); ?></div>
                            <?php endif; ?>
                            <?php if ( $load_rating ) : ?>
                                <div><strong>Load Rating:</strong><br><?php echo esc_html( $load_rating ); ?></div>
                            <?php endif; ?>
                            <?php if ( $qoh ) : ?>
                                <div><strong>In Stock:</strong><br><?php echo esc_html( $qoh ); ?> units</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ( $msrp || $map ) : ?>
                        <div style="background: #e8f5e8; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center;">
                            <?php if ( $msrp ) : ?>
                                <div style="font-size: 0.85em; color: #666; text-decoration: line-through;">MSRP: $<?php echo esc_html( number_format( (float)$msrp, 2 ) ); ?></div>
                            <?php endif; ?>
                            <?php if ( $map ) : ?>
                                <div style="font-size: 1.1em; font-weight: 600; color: #27ae60;">Our Price: $<?php echo esc_html( number_format( (float)$map, 2 ) ); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <?php if ( $call_phone ) : ?>
                            <a href="tel:<?php echo esc_attr( preg_replace( '/[^\d\+]/', '', $call_phone ) ); ?>" style="flex: 1; min-width: 120px; padding: 10px 12px; background: #0073aa; color: #fff; border-radius: 6px; text-decoration: none; font-size: 0.85em; text-align: center; font-weight: 600; transition: background 0.3s ease;" onmouseover="this.style.background='#005a87'" onmouseout="this.style.background='#0073aa'">
                                📞 Call for Order
                            </a>
                        <?php endif; ?>
                        <?php if ( $quote_url ) : ?>
                            <a href="<?php echo esc_url( $quote_url ); ?>" target="_blank" style="flex: 1; min-width: 120px; padding: 10px 12px; background: #27ae60; color: #fff; border-radius: 6px; text-decoration: none; font-size: 0.85em; text-align: center; font-weight: 600; transition: background 0.3s ease;" onmouseover="this.style.background='#219a52'" onmouseout="this.style.background='#27ae60'">
                                💬 Request Quote
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <?php
        wp_reset_postdata();

        $html = ob_get_clean();
        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * Render the [hp_wheels] shortcode.
     *
     * @param array $atts Shortcode attributes (unused currently).
     * @return string
     */
    public static function render_wheels_shortcode( $atts ) {
        // Sanitize filter query variables.
        $brand       = isset( $_GET['brand'] ) ? sanitize_text_field( wp_unslash( $_GET['brand'] ) ) : '';
        $finish      = isset( $_GET['finish'] ) ? sanitize_text_field( wp_unslash( $_GET['finish'] ) ) : '';
        $size        = isset( $_GET['size'] ) ? sanitize_text_field( wp_unslash( $_GET['size'] ) ) : '';
        $bolt_pattern = isset( $_GET['bolt_pattern'] ) ? sanitize_text_field( wp_unslash( $_GET['bolt_pattern'] ) ) : '';
        $style       = isset( $_GET['style'] ) ? sanitize_text_field( wp_unslash( $_GET['style'] ) ) : '';

        // Determine pagination for groups.
        $paged     = max( 1, absint( get_query_var( 'paged' ) ) );
        $per_page  = 24; // number of groups per page

        // Get unique DisplayStyleNo values with filtering
        global $wpdb;

        // TEMPORARILY DISABLE DEBUG - Memory optimization
        // First, let's do a simple check to see if we have any hp_wheel posts at all
        $total_wheels = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'hp_wheel' AND post_status = 'publish'" );

        // Debug: Check if we have any wheels with display style meta
        $wheels_with_meta = $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'hp_wheel' AND p.post_status = 'publish' AND pm.meta_key = 'hp_display_style_no'" );

        // If no wheels at all, return early with debug info
        if ( $total_wheels == 0 ) {
            return '<div style="padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;"><h3>Debug: No Wheels Found</h3><p>Total hp_wheel posts in database: ' . $total_wheels . '</p><p>This suggests the import may not have worked or the post type is different.</p></div>';
        }

        // If wheels exist but no meta, show different debug info
        if ( $wheels_with_meta == 0 ) {
            return '<div style="padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; color: #856404;"><h3>Debug: Wheels Found But No Meta Data</h3><p>Total hp_wheel posts: ' . $total_wheels . '</p><p>Wheels with hp_display_style_no meta: ' . $wheels_with_meta . '</p><p>This suggests the meta fields may not be saved correctly during import. Please run the migration tool in WP Admin → Harry\'s WheelPros → Import Wheels.</p></div>';
        }

        // Memory-efficient approach: Get display style numbers directly via SQL
        $display_style_sql = "SELECT DISTINCT pm.meta_value as display_style_no
                             FROM {$wpdb->posts} p
                             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                             WHERE p.post_type = 'hp_wheel'
                             AND p.post_status = 'publish'
                             AND pm.meta_key = 'hp_display_style_no'
                             AND pm.meta_value != ''
                             ORDER BY pm.meta_value";

        $all_display_styles = $wpdb->get_col( $display_style_sql );
        $total_groups = count( $all_display_styles );

        if ( empty( $all_display_styles ) ) {
            return '<div style="padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;"><h3>Debug: No Display Styles Found</h3><p>Total hp_wheel posts: ' . $total_wheels . '</p><p>Wheels with hp_display_style_no meta: ' . $wheels_with_meta . '</p><p>No unique display styles found. Please check the migration or import process.</p></div>';
        }

        // Apply filtering if needed
        if ( $brand || $finish || $size || $bolt_pattern || $style ) {
            $filtered_display_styles = array();

            foreach ( $all_display_styles as $display_style_no ) {
                // Check if this display style has wheels matching the filters
                $filter_sql = "SELECT COUNT(p.ID) FROM {$wpdb->posts} p
                              INNER JOIN {$wpdb->postmeta} pm_display ON p.ID = pm_display.post_id AND pm_display.meta_key = 'hp_display_style_no' AND pm_display.meta_value = %s";

                $join_conditions = array();
                $where_conditions = array();

                if ( $brand ) {
                    $join_conditions[] = "INNER JOIN {$wpdb->postmeta} pm_brand ON p.ID = pm_brand.post_id AND pm_brand.meta_key = 'hp_brand' AND pm_brand.meta_value = %s";
                    $where_conditions[] = $brand;
                }
                if ( $finish ) {
                    $join_conditions[] = "INNER JOIN {$wpdb->postmeta} pm_finish ON p.ID = pm_finish.post_id AND pm_finish.meta_key = 'hp_finish' AND pm_finish.meta_value = %s";
                    $where_conditions[] = $finish;
                }
                if ( $size ) {
                    $join_conditions[] = "INNER JOIN {$wpdb->postmeta} pm_size ON p.ID = pm_size.post_id AND pm_size.meta_key = 'hp_size' AND pm_size.meta_value = %s";
                    $where_conditions[] = $size;
                }
                if ( $bolt_pattern ) {
                    $join_conditions[] = "INNER JOIN {$wpdb->postmeta} pm_bolt ON p.ID = pm_bolt.post_id AND pm_bolt.meta_key = 'hp_bolt_pattern' AND pm_bolt.meta_value = %s";
                    $where_conditions[] = $bolt_pattern;
                }
                if ( $style ) {
                    $join_conditions[] = "INNER JOIN {$wpdb->postmeta} pm_style ON p.ID = pm_style.post_id AND pm_style.meta_key = 'hp_style' AND pm_style.meta_value = %s";
                    $where_conditions[] = $style;
                }

                $full_sql = $filter_sql . ' ' . implode( ' ', $join_conditions ) . " WHERE p.post_type = 'hp_wheel' AND p.post_status = 'publish'";

                $prep_values = array_merge( array( $display_style_no ), $where_conditions );
                $count = $wpdb->get_var( $wpdb->prepare( $full_sql, $prep_values ) );

                if ( $count > 0 ) {
                    $filtered_display_styles[] = $display_style_no;
                }
            }

            $all_display_styles = $filtered_display_styles;
            $total_groups = count( $all_display_styles );
        }

        // Paginate the display style groups
        $offset = ( $paged - 1 ) * $per_page;
        $paginated_display_style_nos = array_slice( $all_display_styles, $offset, $per_page );

        // Convert to object format for compatibility with existing code
        $display_style_groups = array();
        foreach ( $paginated_display_style_nos as $display_style_no ) {
            $display_style_groups[] = (object) array( 'display_style_no' => $display_style_no );
        }

        // Contact options.
        $options    = get_option( 'hp_wheelpros_options' );
        $call_phone = isset( $options['call_phone'] ) ? $options['call_phone'] : '';
        $quote_url  = isset( $options['quote_url'] ) ? $options['quote_url'] : '';

        // For filter dropdowns, gather full lists of available values
        $brands = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'hp_brand' AND meta_value != '' ORDER BY meta_value" );
        $finishes = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'hp_finish' AND meta_value != '' ORDER BY meta_value" );
        $sizes = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'hp_size' AND meta_value != '' ORDER BY meta_value" );
        $bolt_patterns = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'hp_bolt_pattern' AND meta_value != '' ORDER BY meta_value" );
        $styles = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'hp_style' AND meta_value != '' ORDER BY meta_value" );

        ob_start();
        ?>
        <form method="get" class="hp-wheels-filter" action="" style="margin-bottom:2em; padding:20px; background:#f8f9fa; border-radius:8px; display:flex; flex-wrap:wrap; gap:15px; align-items:end;">
            <div style="flex:1; min-width:180px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#2c3e50; font-size:0.9em;"><?php esc_html_e( 'Brand', 'wheelpros-importer' ); ?></label>
                <select name="brand" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:0.9em; background:#fff;">
                    <option value=""><?php esc_html_e( 'All Brands', 'wheelpros-importer' ); ?></option>
                    <?php foreach ( $brands as $brand_option ) : ?>
                        <option value="<?php echo esc_attr( $brand_option ); ?>" <?php selected( $brand, $brand_option ); ?>><?php echo esc_html( $brand_option ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1; min-width:180px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#2c3e50; font-size:0.9em;"><?php esc_html_e( 'Finish', 'wheelpros-importer' ); ?></label>
                <select name="finish" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:0.9em; background:#fff;">
                    <option value=""><?php esc_html_e( 'All Finishes', 'wheelpros-importer' ); ?></option>
                    <?php foreach ( $finishes as $finish_option ) : ?>
                        <option value="<?php echo esc_attr( $finish_option ); ?>" <?php selected( $finish, $finish_option ); ?>><?php echo esc_html( $finish_option ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1; min-width:180px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#2c3e50; font-size:0.9em;"><?php esc_html_e( 'Size', 'wheelpros-importer' ); ?></label>
                <select name="size" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:0.9em; background:#fff;">
                    <option value=""><?php esc_html_e( 'All Sizes', 'wheelpros-importer' ); ?></option>
                    <?php foreach ( $sizes as $size_option ) : ?>
                        <option value="<?php echo esc_attr( $size_option ); ?>" <?php selected( $size, $size_option ); ?>><?php echo esc_html( $size_option ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1; min-width:180px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#2c3e50; font-size:0.9em;"><?php esc_html_e( 'Bolt Pattern', 'wheelpros-importer' ); ?></label>
                <select name="bolt_pattern" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:0.9em; background:#fff;">
                    <option value=""><?php esc_html_e( 'All Bolt Patterns', 'wheelpros-importer' ); ?></option>
                    <?php foreach ( $bolt_patterns as $bolt_option ) : ?>
                        <option value="<?php echo esc_attr( $bolt_option ); ?>" <?php selected( $bolt_pattern, $bolt_option ); ?>><?php echo esc_html( $bolt_option ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1; min-width:180px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#2c3e50; font-size:0.9em;"><?php esc_html_e( 'Style', 'wheelpros-importer' ); ?></label>
                <select name="style" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:0.9em; background:#fff;">
                    <option value=""><?php esc_html_e( 'All Styles', 'wheelpros-importer' ); ?></option>
                    <?php foreach ( $styles as $style_option ) : ?>
                        <option value="<?php echo esc_attr( $style_option ); ?>" <?php selected( $style, $style_option ); ?>><?php echo esc_html( $style_option ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:0 0 auto;">
                <button type="submit" style="padding:10px 20px; background:#2c3e50; color:#fff; border:none; border-radius:6px; font-size:0.9em; font-weight:600; cursor:pointer; transition:background 0.3s ease;" onmouseover="this.style.background='#34495e'" onmouseout="this.style.background='#2c3e50'"><?php esc_html_e( 'Filter Results', 'wheelpros-importer' ); ?></button>
            </div>
        </form>
        <?php if ( ! empty( $display_style_groups ) ) : ?>
            <div class="hp-wheels-grid">
                <?php foreach ( $display_style_groups as $group ) : ?>
                    <?php
                    // Get a representative wheel for this DisplayStyleNo
                    $wheel_query_args = array(
                        'post_type'      => 'hp_wheel',
                        'posts_per_page' => 1,
                        'meta_query'     => array(
                            array(
                                'key'     => 'hp_display_style_no',
                                'value'   => $group->display_style_no,
                                'compare' => '=',
                            ),
                            array(
                                'key'     => 'hp_image_url',
                                'value'   => '',
                                'compare' => '!=',
                            ),
                        ),
                    );

                    // Apply additional filters if set
                    if ( $brand || $finish || $size || $bolt_pattern || $style ) {
                        $meta_query = $wheel_query_args['meta_query'];

                        if ( $brand ) {
                            $meta_query[] = array(
                                'key'     => 'hp_brand',
                                'value'   => $brand,
                                'compare' => '=',
                            );
                        }
                        if ( $finish ) {
                            $meta_query[] = array(
                                'key'     => 'hp_finish',
                                'value'   => $finish,
                                'compare' => '=',
                            );
                        }
                        if ( $size ) {
                            $meta_query[] = array(
                                'key'     => 'hp_size',
                                'value'   => $size,
                                'compare' => '=',
                            );
                        }
                        if ( $bolt_pattern ) {
                            $meta_query[] = array(
                                'key'     => 'hp_bolt_pattern',
                                'value'   => $bolt_pattern,
                                'compare' => '=',
                            );
                        }
                        if ( $style ) {
                            $meta_query[] = array(
                                'key'     => 'hp_style',
                                'value'   => $style,
                                'compare' => '=',
                            );
                        }

                        $meta_query['relation'] = 'AND';
                        $wheel_query_args['meta_query'] = $meta_query;
                    }

                    $wheel_query = new WP_Query( $wheel_query_args );

                    // Count total variations in this DisplayStyleNo group
                    $count_args = array(
                        'post_type'      => 'hp_wheel',
                        'posts_per_page' => -1,
                        'fields'         => 'ids',
                        'meta_query'     => array(
                            array(
                                'key'     => 'hp_display_style_no',
                                'value'   => $group->display_style_no,
                                'compare' => '=',
                            ),
                        ),
                    );

                    $count_query = new WP_Query( $count_args );
                    $variation_count = $count_query->found_posts;
                    wp_reset_postdata();

                    // Get a good title for this group
                    $group_title = '';
                    $group_description = '';

                    if ( $wheel_query->have_posts() ) : $wheel_query->the_post();
                        $post_id = get_the_ID();

                        // Try to get a good title from the data
                        $part_description = get_post_meta( $post_id, 'hp_part_description', true );
                        $style_meta = get_post_meta( $post_id, 'hp_style', true );
                        $brand_meta = get_post_meta( $post_id, 'hp_brand', true );

                        // Create a title hierarchy: Style > PartDescription > DisplayStyleNo
                        if ( ! empty( $style_meta ) ) {
                            $group_title = $style_meta;
                            $group_description = $part_description;
                        } elseif ( ! empty( $part_description ) ) {
                            $group_title = $part_description;
                        } else {
                            $group_title = 'Style #' . $group->display_style_no;
                        }

                        // Get other data for display
                        $image_url   = get_post_meta( $post_id, 'hp_image_url', true );
                        $part_number = get_post_meta( $post_id, 'hp_part_number', true );
                        $size_meta   = get_post_meta( $post_id, 'hp_size', true );
                        $finish_meta = get_post_meta( $post_id, 'hp_finish', true );
                        $bolt_meta   = get_post_meta( $post_id, 'hp_bolt_pattern', true );
                        $offset_val  = get_post_meta( $post_id, 'hp_offset', true );
                        ?>
                        <div class="hp-wheel-card" style="border:1px solid #ddd; border-radius:12px; padding:20px; box-sizing:border-box; position:relative; background:#fff; box-shadow:0 4px 12px rgba(0,0,0,0.1); transition:all 0.3s ease; cursor:pointer; overflow:hidden;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.15)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'">
                            <?php if ( $variation_count > 1 ) : ?>
                                <div class="hp-variation-badge" style="position:absolute; top:15px; right:15px; background:#0073aa; color:#fff; padding:4px 8px; border-radius:20px; font-size:0.7em; font-weight:600; z-index:2;">
                                    <?php echo esc_html( $variation_count ); ?> <?php esc_html_e( 'Options', 'wheelpros-importer' ); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ( $image_url ) : ?>
                                <div class="hp-wheel-image" style="text-align:center; margin-bottom:15px; position:relative; overflow:hidden; border-radius:8px; background:#f8f9fa;">
                                    <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $group_title ); ?>" style="max-width:100%; height:200px; object-fit:contain; transition:transform 0.3s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                                </div>
                            <?php endif; ?>

                            <div class="hp-wheel-info" style="text-align:center;">
                                <h3 style="margin:0 0 8px 0; font-size:1.1em; font-weight:700; color:#2c3e50; line-height:1.3;">
                                    <?php echo esc_html( $group_title ); ?>
                                </h3>
                                <p style="margin:0 0 6px 0; font-size:0.85em; color:#7f8c8d; font-weight:500;">
                                    <?php echo esc_html( $brand_meta ); ?>
                                </p>
                                <?php if ( $group_description && $group_description !== $group_title ) : ?>
                                    <p style="margin:0 0 12px 0; font-size:0.75em; color:#95a5a6; line-height:1.4;">
                                        <?php echo esc_html( $group_description ); ?>
                                    </p>
                                <?php endif; ?>
                                <div class="hp-wheel-specs" style="background:#f8f9fa; padding:10px; border-radius:6px; margin-bottom:15px; font-size:0.75em; color:#555;">
                                    <div style="margin-bottom:2px;"><strong><?php esc_html_e( 'Style #:', 'wheelpros-importer' ); ?></strong> <?php echo esc_html( $group->display_style_no ); ?></div>
                                    <?php if ( $finish_meta ) : ?>
                                        <div style="margin-bottom:2px;"><strong><?php esc_html_e( 'Example Finish:', 'wheelpros-importer' ); ?></strong> <?php echo esc_html( $finish_meta ); ?></div>
                                    <?php endif; ?>
                                    <?php if ( $size_meta ) : ?>
                                        <div style="margin-bottom:2px;"><strong><?php esc_html_e( 'Example Size:', 'wheelpros-importer' ); ?></strong> <?php echo esc_html( $size_meta ); ?></div>
                                    <?php endif; ?>
                                    <?php if ( $bolt_meta ) : ?>
                                        <div><strong><?php esc_html_e( 'Bolt Pattern:', 'wheelpros-importer' ); ?></strong> <?php echo esc_html( $bolt_meta ); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="hp-wheel-actions" style="display:flex; flex-wrap:wrap; gap:8px; justify-content:center;">
                                <a href="#" class="hp-view-variations" data-style="<?php echo esc_attr( $group->display_style_no ); ?>" data-style-name="<?php echo esc_attr( $group_title ); ?>" style="display:inline-block; padding:10px 16px; background:#2c3e50; color:#fff; border-radius:6px; text-decoration:none; font-size:0.85em; font-weight:600; transition:background 0.3s ease; flex:1; text-align:center; min-width:120px;" onmouseover="this.style.background='#34495e'" onmouseout="this.style.background='#2c3e50'">
                                    <?php esc_html_e( 'View All Options', 'wheelpros-importer' ); ?>
                                </a>
                                <?php if ( $call_phone ) : ?>
                                    <a href="tel:<?php echo esc_attr( preg_replace( '/[^\d\+]/', '', $call_phone ) ); ?>" style="display:inline-block; padding:10px 16px; background:#0073aa; color:#fff; border-radius:6px; text-decoration:none; font-size:0.85em; font-weight:600; transition:background 0.3s ease;" onmouseover="this.style.background='#005a87'" onmouseout="this.style.background='#0073aa'">
                                        📞 <?php esc_html_e( 'Call', 'wheelpros-importer' ); ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ( $quote_url ) : ?>
                                    <a href="<?php echo esc_url( $quote_url ); ?>" style="display:inline-block; padding:10px 16px; background:#27ae60; color:#fff; border-radius:6px; text-decoration:none; font-size:0.85em; font-weight:600; transition:background 0.3s ease;" onmouseover="this.style.background='#219a52'" onmouseout="this.style.background='#27ae60'">
                                        💬 <?php esc_html_e( 'Quote', 'wheelpros-importer' ); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php wp_reset_postdata(); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php
            // Pagination for display style groups.
            $max_pages = ceil( $total_groups / $per_page );
            if ( $max_pages > 1 ) {
                $pagination = paginate_links( array(
                    'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
                    'format'    => '?paged=%#%',
                    'current'   => $paged,
                    'total'     => $max_pages,
                    'type'      => 'array',
                    'prev_text' => __( '&laquo; Previous', 'wheelpros-importer' ),
                    'next_text' => __( 'Next &raquo;', 'wheelpros-importer' ),
                ) );
                if ( is_array( $pagination ) ) {
                    echo '<ul class="pagination" style="display:flex; list-style:none; gap:10px; margin-top:20px;">';
                    foreach ( $pagination as $link ) {
                        echo '<li>' . $link . '</li>';
                    }
                    echo '</ul>';
                }
            }
        ?>
        <?php else : ?>
            <p><?php esc_html_e( 'No wheels found.', 'wheelpros-importer' ); ?></p>
        <?php endif; ?>
        <!-- Modal for wheel variations -->
        <div id="hp-wheel-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:150000; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;">
            <div style="background:#fff; padding:0; max-width:90%; max-height:90%; width:100%; border-radius:12px; position:relative; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                <div style="background:#2c3e50; color:#fff; padding:20px; display:flex; justify-content:space-between; align-items:center;">
                    <h2 id="hp-modal-title" style="margin:0; font-size:1.4em; font-weight:700;"></h2>
                    <button id="hp-modal-close" style="background:transparent; border:none; color:#fff; font-size:2em; cursor:pointer; line-height:1; padding:0; width:30px; height:30px; display:flex; align-items:center; justify-content:center; border-radius:50%; transition:background 0.3s ease;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='transparent'">&times;</button>
                </div>
                <div id="hp-modal-content" style="padding:20px; max-height:calc(90vh - 120px); overflow-y:auto;">
                    <!-- Content injected via JS -->
                </div>
            </div>
        </div>
        <script>
        (function(){
            const modal = document.getElementById('hp-wheel-modal');
            const modalContent = document.getElementById('hp-modal-content');
            const modalTitle = document.getElementById('hp-modal-title');
            const closeBtn = document.getElementById('hp-modal-close');

            // Handle viewing all variations in a style group
            document.querySelectorAll('.hp-view-variations').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    const styleSlug = this.getAttribute('data-style');
                    const styleName = this.getAttribute('data-style-name');

                    modalTitle.textContent = styleName + ' - All Options';
                    modalContent.innerHTML = '<div style="text-align:center; padding:40px;"><div style="display:inline-block; width:40px; height:40px; border:4px solid #f3f3f3; border-top:4px solid #3498db; border-radius:50%; animation:spin 1s linear infinite;"></div><p style="margin-top:15px; color:#666;">Loading variations...</p></div>';
                    modal.style.display = 'flex';

                    // AJAX call to get all variations for this style
                    const formData = new FormData();
                    formData.append('action', 'hp_get_wheel_variations');
                    formData.append('style_slug', styleSlug);
                    formData.append('nonce', '<?php echo wp_create_nonce( "hp_wheel_variations" ); ?>');

                    fetch('<?php echo admin_url( "admin-ajax.php" ); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            modalContent.innerHTML = data.data.html;
                        } else {
                            modalContent.innerHTML = '<p style="text-align:center; color:#e74c3c; padding:40px;">Error loading variations. Please try again.</p>';
                        }
                    })
                    .catch(error => {
                        modalContent.innerHTML = '<p style="text-align:center; color:#e74c3c; padding:40px;">Error loading variations. Please try again.</p>';
                    });
                });
            });

            // Close modal functionality
            closeBtn.addEventListener('click', function(){
                modal.style.display = 'none';
            });

            modal.addEventListener('click', function(e){
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });

            // Close on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.style.display === 'flex') {
                    modal.style.display = 'none';
                }
            });
        })();
        </script>
        <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .hp-wheels-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)) !important;
            gap: 25px !important;
            margin: 25px 0 !important;
        }

        .hp-wheels-filter {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
        }

        .hp-wheels-filter select:focus,
        .hp-wheels-filter button:focus {
            outline: 2px solid #3498db !important;
            outline-offset: 2px !important;
        }

        @media (max-width: 768px) {
            .hp-wheels-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important;
                gap: 20px !important;
            }

            .hp-wheels-filter {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 10px !important;
            }

            .hp-wheels-filter > div {
                flex: 1 1 auto !important;
                min-width: auto !important;
            }
        }

        @media (max-width: 600px) {
            .hp-wheels-filter {
                padding: 15px !important;
            }

            .hp-wheels-filter > div {
                min-width: auto !important;
            }

            .hp-wheels-filter select,
            .hp-wheels-filter button {
                font-size: 16px !important; /* Prevents zoom on iOS */
            }
        }

        @media (max-width: 480px) {
            .hp-wheels-grid {
                grid-template-columns: 1fr !important;
            }

            .hp-wheel-actions {
                flex-direction: column !important;
            }
            .hp-wheel-actions a {
                text-align: center !important;
                min-width: auto !important;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }
}
