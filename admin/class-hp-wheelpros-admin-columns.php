<?php
/**
 * Admin columns customization for the hp_wheel post type.
 *
 * This class enhances the backend "All Wheels" listing with custom columns
 * showing wheel images, descriptions, and metadata. Images are displayed
 * externally without saving to the Media Library, and broken images are
 * filtered out to improve the admin experience.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HP_WheelPros_Admin_Columns {

    /**
     * Initialize admin column hooks.
     */
    public static function init() {
        add_filter( 'manage_hp_wheel_posts_columns', array( __CLASS__, 'add_custom_columns' ) );
        add_action( 'manage_hp_wheel_posts_custom_column', array( __CLASS__, 'populate_custom_columns' ), 10, 2 );
        add_filter( 'manage_edit-hp_wheel_sortable_columns', array( __CLASS__, 'add_sortable_columns' ) );
        add_action( 'admin_head', array( __CLASS__, 'add_admin_styles' ) );

        // Handle sorting for custom columns
        add_action( 'pre_get_posts', array( __CLASS__, 'handle_custom_column_sorting' ) );
    }

    /**
     * Add custom columns to the wheels admin listing.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public static function add_custom_columns( $columns ) {
        // Remove default columns we don't need
        unset( $columns['date'] );
        unset( $columns['taxonomy-hp_display_style'] );
        unset( $columns['taxonomy-hp_brand'] );
        unset( $columns['taxonomy-hp_finish'] );

        // Add our custom columns
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['hp_wheel_image'] = __( 'Image', 'wheelpros-importer' );
        $new_columns['title'] = $columns['title'];
        $new_columns['hp_part_number'] = __( 'Part Number', 'wheelpros-importer' );
        $new_columns['hp_description'] = __( 'Description', 'wheelpros-importer' );
        $new_columns['hp_brand'] = __( 'Brand', 'wheelpros-importer' );
        $new_columns['hp_size'] = __( 'Size', 'wheelpros-importer' );
        $new_columns['hp_finish'] = __( 'Finish', 'wheelpros-importer' );
        $new_columns['hp_stock'] = __( 'Stock', 'wheelpros-importer' );
        $new_columns['hp_display_style'] = __( 'Style #', 'wheelpros-importer' );
        $new_columns['date'] = $columns['date'];

        return $new_columns;
    }

    /**
     * Populate custom column content.
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     */
    public static function populate_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'hp_wheel_image':
                self::render_image_column( $post_id );
                break;

            case 'hp_part_number':
                $part_number = get_post_meta( $post_id, 'hp_part_number', true );
                echo esc_html( $part_number ?: '—' );
                break;

            case 'hp_description':
                $description = get_post_meta( $post_id, 'hp_part_description', true );
                if ( $description ) {
                    $truncated = wp_trim_words( $description, 15, '...' );
                    echo '<div title="' . esc_attr( $description ) . '">' . esc_html( $truncated ) . '</div>';
                } else {
                    echo '—';
                }
                break;

            case 'hp_brand':
                $brand = get_post_meta( $post_id, 'hp_brand', true );
                echo esc_html( $brand ?: '—' );
                break;

            case 'hp_size':
                $size = get_post_meta( $post_id, 'hp_size', true );
                echo esc_html( $size ?: '—' );
                break;

            case 'hp_finish':
                $finish = get_post_meta( $post_id, 'hp_finish', true );
                echo esc_html( $finish ?: '—' );
                break;

            case 'hp_stock':
                $stock = get_post_meta( $post_id, 'hp_total_qoh', true );
                if ( $stock ) {
                    $stock_int = intval( $stock );
                    $class = $stock_int > 0 ? 'hp-stock-available' : 'hp-stock-empty';
                    echo '<span class="' . esc_attr( $class ) . '">' . esc_html( $stock ) . '</span>';
                } else {
                    echo '—';
                }
                break;

            case 'hp_display_style':
                $display_style = get_post_meta( $post_id, 'hp_display_style_no', true );
                echo esc_html( $display_style ?: '—' );
                break;
        }
    }

    /**
     * Render the image column with broken image filtering.
     *
     * @param int $post_id Post ID.
     */
    private static function render_image_column( $post_id ) {
        $image_url = get_post_meta( $post_id, 'hp_image_url', true );

        if ( empty( $image_url ) ) {
            echo '<span class="hp-no-image">No Image</span>';
            return;
        }

        // Check if this image is known to be broken (cached result)
        $broken_cache_key = 'hp_broken_image_' . md5( $image_url );
        $is_broken = wp_cache_get( $broken_cache_key );

        if ( $is_broken === 'broken' ) {
            echo '<span class="hp-broken-image" title="' . esc_attr( $image_url ) . '">❌ Broken Image</span>';
            return;
        }

        // Display image with lazy loading and error handling
        echo '<div class="hp-wheel-image-container">';
        echo '<img src="' . esc_url( $image_url ) . '" ';
        echo 'alt="' . esc_attr( get_the_title( $post_id ) ) . '" ';
        echo 'class="hp-wheel-thumbnail" ';
        echo 'loading="lazy" ';
        echo 'onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'block\'; hpMarkImageBroken(\'' . esc_js( $image_url ) . '\');" ';
        echo 'onload="this.style.opacity=\'1\';" ';
        echo 'style="opacity: 0; transition: opacity 0.3s ease;">';
        echo '<span class="hp-broken-image" style="display: none;" title="' . esc_attr( $image_url ) . '">❌ Failed to Load</span>';
        echo '</div>';
    }

    /**
     * Make custom columns sortable.
     *
     * @param array $columns Sortable columns.
     * @return array Modified sortable columns.
     */
    public static function add_sortable_columns( $columns ) {
        $columns['hp_part_number'] = 'hp_part_number';
        $columns['hp_brand'] = 'hp_brand';
        $columns['hp_size'] = 'hp_size';
        $columns['hp_finish'] = 'hp_finish';
        $columns['hp_stock'] = 'hp_stock';
        $columns['hp_display_style'] = 'hp_display_style';

        return $columns;
    }

    /**
     * Handle sorting for custom meta field columns.
     *
     * @param WP_Query $query Main query.
     */
    public static function handle_custom_column_sorting( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( $query->get( 'post_type' ) !== 'hp_wheel' ) {
            return;
        }

        $orderby = $query->get( 'orderby' );

        $meta_key_map = array(
            'hp_part_number' => 'hp_part_number',
            'hp_brand' => 'hp_brand',
            'hp_size' => 'hp_size',
            'hp_finish' => 'hp_finish',
            'hp_stock' => 'hp_total_qoh',
            'hp_display_style' => 'hp_display_style_no'
        );

        if ( array_key_exists( $orderby, $meta_key_map ) ) {
            $query->set( 'meta_key', $meta_key_map[ $orderby ] );
            if ( $orderby === 'hp_stock' ) {
                $query->set( 'orderby', 'meta_value_num' );
            } else {
                $query->set( 'orderby', 'meta_value' );
            }
        }
    }

    /**
     * Add admin styles for the custom columns.
     */
    public static function add_admin_styles() {
        global $pagenow, $post_type;

        if ( $pagenow !== 'edit.php' || $post_type !== 'hp_wheel' ) {
            return;
        }

        ?>
        <style>
        .hp-wheel-image-container {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
            overflow: hidden;
        }

        .hp-wheel-thumbnail {
            max-width: 58px;
            max-height: 58px;
            object-fit: contain;
            border-radius: 3px;
        }

        .hp-no-image,
        .hp-broken-image {
            font-size: 11px;
            color: #999;
            text-align: center;
            padding: 4px;
            line-height: 1.2;
            display: block;
        }

        .hp-broken-image {
            color: #d63638;
            font-weight: 500;
        }

        .hp-stock-available {
            color: #46b450;
            font-weight: 600;
        }

        .hp-stock-empty {
            color: #d63638;
            font-weight: 600;
        }

        .column-hp_wheel_image {
            width: 80px;
        }

        .column-hp_part_number {
            width: 120px;
        }

        .column-hp_description {
            width: 200px;
        }

        .column-hp_brand,
        .column-hp_size,
        .column-hp_finish {
            width: 100px;
        }

        .column-hp_stock {
            width: 80px;
            text-align: center;
        }

        .column-hp_display_style {
            width: 100px;
        }

        /* Responsive adjustments */
        @media screen and (max-width: 1200px) {
            .column-hp_description {
                display: none;
            }
        }

        @media screen and (max-width: 900px) {
            .column-hp_finish,
            .column-hp_size {
                display: none;
            }
        }
        </style>

        <script>
        // JavaScript function to mark images as broken and cache the result
        function hpMarkImageBroken(imageUrl) {
            // Send AJAX request to mark this image as broken
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=hp_mark_image_broken&image_url=' + encodeURIComponent(imageUrl) + '&nonce=' + '<?php echo wp_create_nonce( "hp_mark_broken_image" ); ?>'
            });
        }
        </script>
        <?php
    }

    /**
     * Get count of wheels with broken images for admin dashboard.
     *
     * @return int Number of wheels with broken images.
     */
    public static function get_broken_image_count() {
        global $wpdb;

        $broken_images = wp_cache_get( 'hp_broken_images_list' );
        if ( $broken_images === false ) {
            return 0;
        }

        if ( empty( $broken_images ) ) {
            return 0;
        }

        $placeholders = implode( ',', array_fill( 0, count( $broken_images ), '%s' ) );

        $count = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(DISTINCT post_id)
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'hp_image_url'
            AND meta_value IN ($placeholders)
        ", $broken_images ) );

        return intval( $count );
    }
}
