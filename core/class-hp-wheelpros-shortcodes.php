<?php
/**
 * Handles shortcodes for the front-end display of wheels.
 *
 * Provides the [hp_wheels] shortcode which renders a filterable list of wheels.
 * Features advanced filtering, pagination, and responsive design.
 */
class HP_WheelPros_Shortcodes {

    /**
     * Register shortcode hooks and AJAX handlers.
     */
    public static function register() {
        // Register shortcodes.
        add_shortcode( 'hp_wheels', array( __CLASS__, 'render_wheels_shortcode' ) );

        // Register AJAX handlers for wheel variations modal.
        add_action( 'wp_ajax_hp_get_wheel_variations', array( __CLASS__, 'ajax_get_wheel_variations' ) );
        add_action( 'wp_ajax_nopriv_hp_get_wheel_variations', array( __CLASS__, 'ajax_get_wheel_variations' ) );
    }

    /**
     * AJAX handler for getting wheel variations.
     */
    public static function ajax_get_wheel_variations() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'hp_wheel_variations' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }

        $display_style_no = sanitize_text_field( $_POST['style_slug'] );
        $brand_name = sanitize_text_field( $_POST['brand_name'] ?? '' );

        if ( empty( $display_style_no ) ) {
            wp_send_json_error( 'Display style number required' );
        }

        global $wpdb;

        // Get broken images list to exclude them
        $broken_images = wp_cache_get( 'hp_broken_images_list' );
        $broken_where_clause = '';
        $brand_where_clause = '';
        $params = array( $display_style_no );

        // Add brand filter if provided
        if ( ! empty( $brand_name ) ) {
            $brand_where_clause = " AND pm_brand.meta_value = %s";
            $params[] = $brand_name;
        }

        if ( ! empty( $broken_images ) && is_array( $broken_images ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $broken_images ), '%s' ) );
            $broken_where_clause = " AND pm_image.meta_value NOT IN ($placeholders)";
            $params = array_merge( $params, $broken_images );
        }

        // Get all wheels for this DisplayStyleNo and Brand with images only (excluding broken ones)
        $sql = "SELECT p.ID,
                   pm_image.meta_value as image_url,
                   pm_part.meta_value as part_number,
                   pm_desc.meta_value as part_description,
                   pm_size.meta_value as size,
                   pm_brand.meta_value as brand,
                   pm_finish.meta_value as finish,
                   pm_offset.meta_value as offset_val,
                   pm_bolt.meta_value as bolt,
                   pm_bore.meta_value as center_bore,
                   pm_load.meta_value as load_rating,
                   pm_qoh.meta_value as qoh
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_display ON p.ID = pm_display.post_id AND pm_display.meta_key = 'hp_display_style_no' AND pm_display.meta_value = %s
            INNER JOIN {$wpdb->postmeta} pm_image ON p.ID = pm_image.post_id AND pm_image.meta_key = 'hp_image_url' AND pm_image.meta_value != '' AND pm_image.meta_value IS NOT NULL{$broken_where_clause}
            LEFT JOIN {$wpdb->postmeta} pm_part ON p.ID = pm_part.post_id AND pm_part.meta_key = 'hp_part_number'
            LEFT JOIN {$wpdb->postmeta} pm_desc ON p.ID = pm_desc.post_id AND pm_desc.meta_key = 'hp_part_description'
            LEFT JOIN {$wpdb->postmeta} pm_size ON p.ID = pm_size.post_id AND pm_size.meta_key = 'hp_size'
            LEFT JOIN {$wpdb->postmeta} pm_brand ON p.ID = pm_brand.post_id AND pm_brand.meta_key = 'hp_brand'
            LEFT JOIN {$wpdb->postmeta} pm_finish ON p.ID = pm_finish.post_id AND pm_finish.meta_key = 'hp_finish'
            LEFT JOIN {$wpdb->postmeta} pm_offset ON p.ID = pm_offset.post_id AND pm_offset.meta_key = 'hp_offset'
            LEFT JOIN {$wpdb->postmeta} pm_bolt ON p.ID = pm_bolt.post_id AND pm_bolt.meta_key = 'hp_bolt_pattern'
            LEFT JOIN {$wpdb->postmeta} pm_bore ON p.ID = pm_bore.post_id AND pm_bore.meta_key = 'hp_center_bore'
            LEFT JOIN {$wpdb->postmeta} pm_load ON p.ID = pm_load.post_id AND pm_load.meta_key = 'hp_load_rating'
            LEFT JOIN {$wpdb->postmeta} pm_qoh ON p.ID = pm_qoh.post_id AND pm_qoh.meta_key = 'hp_total_qoh'
            WHERE p.post_type = 'hp_wheel' AND p.post_status = 'publish'{$brand_where_clause}
            ORDER BY pm_part.meta_value ASC";

        $wheels = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

        if ( empty( $wheels ) ) {
            wp_send_json_error( 'No wheels found for this style' );
        }

        $options = get_option( 'hp_wheelpros_options' );
        $call_phone = isset( $options['call_phone'] ) ? $options['call_phone'] : '';
        $quote_url = isset( $options['quote_url'] ) ? $options['quote_url'] : '';

        // Group wheels by finish + size + offset for proper grouping, not just image URL
        $grouped_wheels = array();
        foreach ( $wheels as $wheel ) {
            // Create a unique key based on finish, size, and offset to group similar wheels
            $group_key = $wheel->finish . '|' . $wheel->size . '|' . $wheel->offset_val;
            if ( ! isset( $grouped_wheels[ $group_key ] ) ) {
                $grouped_wheels[ $group_key ] = array(
                    'main' => $wheel,
                    'variations' => array(),
                    'group_title' => $wheel->finish . ($wheel->size ? ' - ' . $wheel->size : '') . ($wheel->offset_val ? ' - ' . $wheel->offset_val . ' offset' : '')
                );
            }
            $grouped_wheels[ $group_key ]['variations'][] = $wheel;
        }

        ob_start();
        ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <?php foreach ( $grouped_wheels as $group ) :
                $main_wheel = $group['main'];
                $variations = $group['variations'];
                $has_multiple = count( $variations ) > 1;
                ?>
                <div style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: #fff; transition: transform 0.2s ease, box-shadow 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">

                    <?php if ( ! empty( $group['group_title'] ) ) : ?>
                        <div style="background: #f8f9fa; padding: 8px 12px; margin: -15px -15px 15px -15px; border-radius: 8px 8px 0 0; font-size: 0.85em; font-weight: 600; color: #2c3e50; border-bottom: 1px solid #dee2e6;">
                            <?php echo esc_html( $group['group_title'] ); ?>
                        </div>
                    <?php endif; ?>

                    <div style="text-align: center; margin-bottom: 15px;">
                        <img src="<?php echo esc_url( $main_wheel->image_url ); ?>" alt="<?php echo esc_attr( $main_wheel->part_number ); ?>" style="max-width: 100%; height: 180px; object-fit: contain; border-radius: 4px;" loading="lazy">
                    </div>

                    <?php if ( $has_multiple ) : ?>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px; color: #2c3e50; font-size: 0.9em;">Select Option:</label>
                            <select class="wheel-variation-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                                <?php foreach ( $variations as $index => $variation ) : ?>
                                    <option value="<?php echo esc_attr( $index ); ?>" data-wheel='<?php echo esc_attr( json_encode( $variation ) ); ?>'>
                                        <?php echo esc_html( $variation->part_number ); ?>
                                        <?php if ( $variation->size ) : ?> - <?php echo esc_html( $variation->size ); ?><?php endif; ?>
                                        <?php if ( $variation->finish ) : ?> - <?php echo esc_html( $variation->finish ); ?><?php endif; ?>
                                        <?php if ( $variation->offset_val ) : ?> - <?php echo esc_html( $variation->offset_val ); ?> offset<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="wheel-details">
                        <h4 style="margin: 0 0 8px 0; font-size: 1.1em; font-weight: 600; color: #2c3e50;">
                            <?php echo esc_html( $main_wheel->part_number ); ?>
                        </h4>

                        <?php if ( $main_wheel->part_description ) : ?>
                            <p style="margin: 0 0 10px 0; font-size: 0.9em; color: #555; line-height: 1.4;">
                                <?php echo esc_html( $main_wheel->part_description ); ?>
                            </p>
                        <?php endif; ?>

                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 0.85em;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                <div><strong>Brand:</strong><br><span class="detail-brand"><?php echo esc_html( $main_wheel->brand ); ?></span></div>
                                <div><strong>Finish:</strong><br><span class="detail-finish"><?php echo esc_html( $main_wheel->finish ); ?></span></div>
                                <div><strong>Size:</strong><br><span class="detail-size"><?php echo esc_html( $main_wheel->size ); ?></span></div>
                                <div><strong>Bolt:</strong><br><span class="detail-bolt"><?php echo esc_html( $main_wheel->bolt ); ?></span></div>
                                <?php if ( $main_wheel->offset_val ) : ?>
                                    <div><strong>Offset:</strong><br><span class="detail-offset"><?php echo esc_html( $main_wheel->offset_val ); ?></span></div>
                                <?php endif; ?>
                                <?php if ( $main_wheel->center_bore ) : ?>
                                    <div><strong>Center Bore:</strong><br><span class="detail-bore"><?php echo esc_html( $main_wheel->center_bore ); ?></span></div>
                                <?php endif; ?>
                                <?php if ( $main_wheel->load_rating ) : ?>
                                    <div><strong>Load Rating:</strong><br><span class="detail-load"><?php echo esc_html( $main_wheel->load_rating ); ?></span></div>
                                <?php endif; ?>
                                <?php if ( $main_wheel->qoh ) : ?>
                                    <div><strong>In Stock:</strong><br><span class="detail-qoh"><?php echo esc_html( $main_wheel->qoh ); ?> units</span></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <?php if ( $call_phone ) : ?>
                            <a href="tel:<?php echo esc_attr( preg_replace( '/[^\d\+]/', '', $call_phone ) ); ?>" style="flex: 1; min-width: 120px; padding: 10px 12px; background: #0073aa; color: #fff; border-radius: 6px; text-decoration: none; font-size: 0.85em; text-align: center; font-weight: 600; transition: background 0.3s ease;" onmouseover="this.style.background='#005a87'" onmouseout="this.style.background='#0073aa'">
                                📞 Call for Pricing
                            </a>
                        <?php endif; ?>
                        <?php if ( $quote_url ) : ?>
                            <a href="<?php echo esc_url( $quote_url ); ?>" target="_blank" style="flex: 1; min-width: 120px; padding: 10px 12px; background: #27ae60; color: #fff; border-radius: 6px; text-decoration: none; font-size: 0.85em; text-align: center; font-weight: 600; transition: background 0.3s ease;" onmouseover="this.style.background='#219a52'" onmouseout="this.style.background='#27ae60'">
                                💬 Request Quote
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <script>
        document.querySelectorAll('.wheel-variation-select').forEach(function(select) {
            select.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const wheelData = JSON.parse(selectedOption.dataset.wheel);
                const detailsContainer = this.closest('.wheel-variation-card, [style*="border"]').querySelector('.wheel-details');

                // Update details
                detailsContainer.querySelector('.detail-brand').textContent = wheelData.brand || '';
                detailsContainer.querySelector('.detail-finish').textContent = wheelData.finish || '';
                detailsContainer.querySelector('.detail-size').textContent = wheelData.size || '';
                detailsContainer.querySelector('.detail-bolt').textContent = wheelData.bolt || '';

                const offsetElement = detailsContainer.querySelector('.detail-offset');
                if (offsetElement) offsetElement.textContent = wheelData.offset_val || '';

                const boreElement = detailsContainer.querySelector('.detail-bore');
                if (boreElement) boreElement.textContent = wheelData.center_bore || '';

                const loadElement = detailsContainer.querySelector('.detail-load');
                if (loadElement) loadElement.textContent = wheelData.load_rating || '';

                const qohElement = detailsContainer.querySelector('.detail-qoh');
                if (qohElement) qohElement.textContent = wheelData.qoh ? wheelData.qoh + ' units' : '';

                // Update part number
                detailsContainer.querySelector('h4').textContent = wheelData.part_number || '';
            });
        });
        </script>
        <?php

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
        $per_page  = 24;

        global $wpdb;

        // Optimized query: Get display styles with valid images, grouped by display style AND brand to prevent mixing different wheel types
        $cache_key = 'hp_wheel_display_styles_v2_' . md5( serialize( compact( 'brand', 'finish', 'size', 'bolt_pattern', 'style' ) ) );
        $display_style_groups = wp_cache_get( $cache_key );

        if ( false === $display_style_groups ) {
            // Build optimized SQL query - group by display style AND brand to prevent mixing different wheel types
            $sql = "SELECT
                        pm_display.meta_value as display_style_no,
                        MIN(pm_image.meta_value) as image_url,
                        GROUP_CONCAT(DISTINCT p.ID) as wheel_ids,
                        COUNT(p.ID) as variation_count,
                        MAX(pm_style.meta_value) as style_name,
                        MAX(pm_brand.meta_value) as brand_name,
                        MAX(pm_desc.meta_value) as part_description
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm_display ON p.ID = pm_display.post_id AND pm_display.meta_key = 'hp_display_style_no'
                    INNER JOIN {$wpdb->postmeta} pm_image ON p.ID = pm_image.post_id AND pm_image.meta_key = 'hp_image_url' AND pm_image.meta_value != '' AND pm_image.meta_value IS NOT NULL
                    LEFT JOIN {$wpdb->postmeta} pm_style ON p.ID = pm_style.post_id AND pm_style.meta_key = 'hp_style'
                    LEFT JOIN {$wpdb->postmeta} pm_brand ON p.ID = pm_brand.post_id AND pm_brand.meta_key = 'hp_brand'
                    LEFT JOIN {$wpdb->postmeta} pm_desc ON p.ID = pm_desc.post_id AND pm_desc.meta_key = 'hp_part_description'";

            $where_conditions = array( "p.post_type = 'hp_wheel'", "p.post_status = 'publish'" );
            $join_conditions = array();
            $values = array();

            // Exclude known broken images
            $broken_images = wp_cache_get( 'hp_broken_images_list' );
            if ( ! empty( $broken_images ) && is_array( $broken_images ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $broken_images ), '%s' ) );
                $where_conditions[] = "pm_image.meta_value NOT IN ($placeholders)";
                $values = array_merge( $values, $broken_images );
            }

            // Add filter conditions
            if ( $brand ) {
                $join_conditions[] = "INNER JOIN {$wpdb->postmeta} pm_filter_brand ON p.ID = pm_filter_brand.post_id AND pm_filter_brand.meta_key = 'hp_brand' AND pm_filter_brand.meta_value = %s";
                $values[] = $brand;
            }
            if ( $finish ) {
                $join_conditions[] = "INNER JOIN {$wpdb->postmeta} pm_filter_finish ON p.ID = pm_filter_finish.post_id AND pm_filter_finish.meta_key = 'hp_finish' AND pm_filter_finish.meta_value = %s";
                $values[] = $finish;
            }
            if ( $size ) {
                $join_conditions[] = "INNER JOIN {$wpdb->postmeta} pm_filter_size ON p.ID = pm_filter_size.post_id AND pm_filter_size.meta_key = 'hp_size' AND pm_filter_size.meta_value = %s";
                $values[] = $size;
            }
            if ( $bolt_pattern ) {
                $join_conditions[] = "INNER JOIN {$wpdb->postmeta} pm_filter_bolt ON p.ID = pm_filter_bolt.post_id AND pm_filter_bolt.meta_key = 'hp_bolt_pattern' AND pm_filter_bolt.meta_value = %s";
                $values[] = $bolt_pattern;
            }
            if ( $style ) {
                $join_conditions[] = "INNER JOIN {$wpdb->postmeta} pm_filter_style ON p.ID = pm_filter_style.post_id AND pm_filter_style.meta_key = 'hp_style' AND pm_filter_style.meta_value = %s";
                $values[] = $style;
            }

            if ( ! empty( $join_conditions ) ) {
                $sql .= ' ' . implode( ' ', $join_conditions );
            }

            $sql .= ' WHERE ' . implode( ' AND ', $where_conditions );
            $sql .= ' GROUP BY pm_display.meta_value, pm_brand.meta_value';
            $sql .= ' ORDER BY pm_brand.meta_value ASC, display_style_no ASC';

            if ( ! empty( $values ) ) {
                $sql = $wpdb->prepare( $sql, $values );
            }

            $display_style_groups = $wpdb->get_results( $sql );
            wp_cache_set( $cache_key, $display_style_groups, '', 300 ); // Cache for 5 minutes
        }

        if ( empty( $display_style_groups ) ) {
            return '<div style="padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;"><h3>No Wheels Found</h3><p>No wheels match your current filter criteria. Please try adjusting your filters.</p></div>';
        }

        $total_groups = count( $display_style_groups );

        // Paginate results
        $offset = ( $paged - 1 ) * $per_page;
        $paginated_groups = array_slice( $display_style_groups, $offset, $per_page );

        // Contact options.
        $options    = get_option( 'hp_wheelpros_options' );
        $call_phone = isset( $options['call_phone'] ) ? $options['call_phone'] : '';
        $quote_url  = isset( $options['quote_url'] ) ? $options['quote_url'] : '';

        // For filter dropdowns, gather available values (cached)
        $filter_cache_key = 'hp_wheel_filter_options';
        $filter_options = wp_cache_get( $filter_cache_key );

        if ( false === $filter_options ) {
            $filter_options = array(
                'brands' => $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'hp_brand' AND meta_value != '' ORDER BY meta_value" ),
                'finishes' => $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'hp_finish' AND meta_value != '' ORDER BY meta_value" ),
                'sizes' => $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'hp_size' AND meta_value != '' ORDER BY meta_value" ),
                'bolt_patterns' => $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'hp_bolt_pattern' AND meta_value != '' ORDER BY meta_value" ),
                'styles' => $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'hp_style' AND meta_value != '' ORDER BY meta_value" )
            );
            wp_cache_set( $filter_cache_key, $filter_options, '', 900 ); // Cache for 15 minutes
        }

        ob_start();
        ?>
        <form method="get" class="hp-wheels-filter" action="" style="margin-bottom:2em; padding:20px; background:#f8f9fa; border-radius:8px; display:flex; flex-wrap:wrap; gap:15px; align-items:end;">
            <div style="flex:1; min-width:180px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#2c3e50; font-size:0.9em;"><?php esc_html_e( 'Brand', 'wheelpros-importer' ); ?></label>
                <select name="brand" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:0.9em; background:#fff;">
                    <option value=""><?php esc_html_e( 'All Brands', 'wheelpros-importer' ); ?></option>
                    <?php foreach ( $filter_options['brands'] as $brand_option ) : ?>
                        <option value="<?php echo esc_attr( $brand_option ); ?>" <?php selected( $brand, $brand_option ); ?>><?php echo esc_html( $brand_option ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1; min-width:180px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#2c3e50; font-size:0.9em;"><?php esc_html_e( 'Finish', 'wheelpros-importer' ); ?></label>
                <select name="finish" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:0.9em; background:#fff;">
                    <option value=""><?php esc_html_e( 'All Finishes', 'wheelpros-importer' ); ?></option>
                    <?php foreach ( $filter_options['finishes'] as $finish_option ) : ?>
                        <option value="<?php echo esc_attr( $finish_option ); ?>" <?php selected( $finish, $finish_option ); ?>><?php echo esc_html( $finish_option ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1; min-width:180px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#2c3e50; font-size:0.9em;"><?php esc_html_e( 'Size', 'wheelpros-importer' ); ?></label>
                <select name="size" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:0.9em; background:#fff;">
                    <option value=""><?php esc_html_e( 'All Sizes', 'wheelpros-importer' ); ?></option>
                    <?php foreach ( $filter_options['sizes'] as $size_option ) : ?>
                        <option value="<?php echo esc_attr( $size_option ); ?>" <?php selected( $size, $size_option ); ?>><?php echo esc_html( $size_option ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1; min-width:180px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#2c3e50; font-size:0.9em;"><?php esc_html_e( 'Bolt Pattern', 'wheelpros-importer' ); ?></label>
                <select name="bolt_pattern" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:0.9em; background:#fff;">
                    <option value=""><?php esc_html_e( 'All Bolt Patterns', 'wheelpros-importer' ); ?></option>
                    <?php foreach ( $filter_options['bolt_patterns'] as $bolt_option ) : ?>
                        <option value="<?php echo esc_attr( $bolt_option ); ?>" <?php selected( $bolt_pattern, $bolt_option ); ?>><?php echo esc_html( $bolt_option ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1; min-width:180px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color:#2c3e50; font-size:0.9em;"><?php esc_html_e( 'Style', 'wheelpros-importer' ); ?></label>
                <select name="style" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:0.9em; background:#fff;">
                    <option value=""><?php esc_html_e( 'All Styles', 'wheelpros-importer' ); ?></option>
                    <?php foreach ( $filter_options['styles'] as $style_option ) : ?>
                        <option value="<?php echo esc_attr( $style_option ); ?>" <?php selected( $style, $style_option ); ?>><?php echo esc_html( $style_option ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:0 0 auto; display:flex; gap:10px;">
                <button type="submit" style="padding:10px 20px; background:#2c3e50; color:#fff; border:none; border-radius:6px; font-size:0.9em; font-weight:600; cursor:pointer; transition:background 0.3s ease;" onmouseover="this.style.background='#34495e'" onmouseout="this.style.background='#2c3e50'"><?php esc_html_e( 'Filter Results', 'wheelpros-importer' ); ?></button>
                <button type="button" onclick="window.location.href='<?php echo esc_url( strtok( $_SERVER['REQUEST_URI'], '?' ) ); ?>'" style="padding:10px 20px; background:#6c757d; color:#fff; border:none; border-radius:6px; font-size:0.9em; font-weight:600; cursor:pointer; transition:background 0.3s ease;" onmouseover="this.style.background='#5a6268'" onmouseout="this.style.background='#6c757d'"><?php esc_html_e( 'Reset Filters', 'wheelpros-importer' ); ?></button>
            </div>
        </form>

        <?php if ( ! empty( $paginated_groups ) ) : ?>
            <div class="hp-wheels-grid">
                <?php foreach ( $paginated_groups as $group ) :
                    // Get representative wheel data
                    $wheel_ids = explode( ',', $group->wheel_ids );
                    $post_id = intval( $wheel_ids[0] );

                    // Create group title from database data
                    $group_title = ! empty( $group->style_name ) ? $group->style_name : ( ! empty( $group->part_description ) ? $group->part_description : 'Style #' . $group->display_style_no );
                    $group_description = ! empty( $group->part_description ) && $group->part_description !== $group_title ? $group->part_description : '';

                    // Get additional metadata for first wheel
                    $meta_data = $wpdb->get_results( $wpdb->prepare( "
                        SELECT meta_key, meta_value
                        FROM {$wpdb->postmeta}
                        WHERE post_id = %d
                        AND meta_key IN ('hp_size', 'hp_finish', 'hp_bolt_pattern', 'hp_offset')
                    ", $post_id ), OBJECT_K );

                    $size_meta = isset( $meta_data['hp_size'] ) ? $meta_data['hp_size']->meta_value : '';
                    $finish_meta = isset( $meta_data['hp_finish'] ) ? $meta_data['hp_finish']->meta_value : '';
                    $bolt_meta = isset( $meta_data['hp_bolt_pattern'] ) ? $meta_data['hp_bolt_pattern']->meta_value : '';
                    ?>
                    <div class="hp-wheel-card" style="border:1px solid #ddd; border-radius:12px; padding:20px; box-sizing:border-box; position:relative; background:#fff; box-shadow:0 4px 12px rgba(0,0,0,0.1); transition:all 0.3s ease; cursor:pointer; overflow:hidden;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.15)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'">
                        <?php if ( $group->variation_count > 1 ) : ?>
                            <div class="hp-variation-badge" style="position:absolute; top:15px; right:15px; background:#0073aa; color:#fff; padding:4px 8px; border-radius:20px; font-size:0.7em; font-weight:600; z-index:2;">
                                <?php echo esc_html( $group->variation_count ); ?> <?php esc_html_e( 'Options', 'wheelpros-importer' ); ?>
                            </div>
                        <?php endif; ?>

                        <div class="hp-wheel-image" style="text-align:center; margin-bottom:15px; position:relative; overflow:hidden; border-radius:8px; background:#f8f9fa;">
                            <img src="<?php echo esc_url( $group->image_url ); ?>" alt="<?php echo esc_attr( $group_title ); ?>" style="max-width:100%; height:200px; object-fit:contain; transition:transform 0.3s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'" loading="lazy">
                        </div>

                        <div class="hp-wheel-info" style="text-align:center;">
                            <h3 style="margin:0 0 8px 0; font-size:1.1em; font-weight:700; color:#2c3e50; line-height:1.3;">
                                <?php echo esc_html( $group_title ); ?>
                            </h3>
                            <p style="margin:0 0 6px 0; font-size:0.85em; color:#7f8c8d; font-weight:500;">
                                <?php echo esc_html( $group->brand_name ); ?>
                            </p>
                            <?php if ( $group_description ) : ?>
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

                        <div class="hp-wheel-actions" style="display:flex; flex-direction:column; gap:8px;">
                            <a href="#" class="hp-view-variations" data-style="<?php echo esc_attr( $group->display_style_no ); ?>" data-brand="<?php echo esc_attr( $group->brand_name ); ?>" data-style-name="<?php echo esc_attr( $group_title ); ?>" style="display:block; padding:12px 16px; background:#2c3e50; color:#fff; border-radius:6px; text-decoration:none; font-size:0.85em; font-weight:600; transition:background 0.3s ease; text-align:center;" onmouseover="this.style.background='#34495e'" onmouseout="this.style.background='#2c3e50'">
                                <?php esc_html_e( 'View All Options', 'wheelpros-importer' ); ?>
                            </a>
                            <?php if ( $call_phone ) : ?>
                                <a href="tel:<?php echo esc_attr( preg_replace( '/[^\d\+]/', '', $call_phone ) ); ?>" style="display:block; padding:12px 16px; background:#0073aa; color:#fff; border-radius:6px; text-decoration:none; font-size:0.85em; font-weight:600; transition:background 0.3s ease; text-align:center;" onmouseover="this.style.background='#005a87'" onmouseout="this.style.background='#0073aa'">
                                    📞 <?php esc_html_e( 'Call for Pricing', 'wheelpros-importer' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php
            // Pagination
            if ( $total_groups > $per_page ) {
                $total_pages = ceil( $total_groups / $per_page );

                echo '<div class="hp-pagination" style="margin: 30px 0; text-align: center;">';

                if ( $paged > 1 ) {
                    $prev_url = add_query_arg( 'paged', $paged - 1 );
                    echo '<a href="' . esc_url( $prev_url ) . '" style="display: inline-block; padding: 8px 12px; margin: 0 2px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #2c3e50;">« Previous</a>';
                }

                for ( $i = max( 1, $paged - 2 ); $i <= min( $total_pages, $paged + 2 ); $i++ ) {
                    if ( $i == $paged ) {
                        echo '<span style="display: inline-block; padding: 8px 12px; margin: 0 2px; background: #2c3e50; color: #fff; border-radius: 4px;">' . $i . '</span>';
                    } else {
                        $page_url = add_query_arg( 'paged', $i );
                        echo '<a href="' . esc_url( $page_url ) . '" style="display: inline-block; padding: 8px 12px; margin: 0 2px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #2c3e50;">' . $i . '</a>';
                    }
                }

                if ( $paged < $total_pages ) {
                    $next_url = add_query_arg( 'paged', $paged + 1 );
                    echo '<a href="' . esc_url( $next_url ) . '" style="display: inline-block; padding: 8px 12px; margin: 0 2px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #2c3e50;">Next »</a>';
                }

                echo '</div>';
            }
            ?>
        <?php else : ?>
            <div style="padding: 30px; text-align: center; background: #f8f9fa; border-radius: 8px;">
                <h3 style="color: #666; margin-bottom: 15px;"><?php esc_html_e( 'No Wheels Found', 'wheelpros-importer' ); ?></h3>
                <p style="color: #888; margin-bottom: 20px;"><?php esc_html_e( 'No wheels match your current filter criteria.', 'wheelpros-importer' ); ?></p>
                <a href="<?php echo esc_url( strtok( $_SERVER['REQUEST_URI'], '?' ) ); ?>" style="display: inline-block; padding: 10px 20px; background: #2c3e50; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 600;"><?php esc_html_e( 'Clear All Filters', 'wheelpros-importer' ); ?></a>
            </div>
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
                    const brandName = this.getAttribute('data-brand');
                    const styleName = this.getAttribute('data-style-name');

                    modalTitle.textContent = styleName + ' - All Options';
                    modalContent.innerHTML = '<div style="text-align:center; padding:40px;"><div style="display:inline-block; width:40px; height:40px; border:4px solid #f3f3f3; border-top:4px solid #3498db; border-radius:50%; animation:spin 1s linear infinite;"></div><p style="margin-top:15px; color:#666;">Loading variations...</p></div>';
                    modal.style.display = 'flex';

                    // AJAX call to get all variations for this style and brand combination
                    const formData = new FormData();
                    formData.append('action', 'hp_get_wheel_variations');
                    formData.append('style_slug', styleSlug);
                    formData.append('brand_name', brandName);
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
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)) !important;
            gap: 25px !important;
            margin: 25px 0 !important;
        }

        .hp-wheel-card {
            display: flex !important;
            flex-direction: column !important;
            min-height: 580px !important;
        }

        .hp-wheel-info {
            flex: 1 !important;
        }

        .hp-wheel-actions {
            margin-top: auto !important;
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
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important;
                gap: 20px !important;
            }

            .hp-wheel-card {
                min-height: 520px !important;
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

            .hp-wheel-card {
                min-height: 480px !important;
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
