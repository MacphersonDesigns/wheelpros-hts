<?php
/**
 * Example: Product Filtering by Vehicle
 *
 * This file demonstrates how to filter WheelPros products based on
 * selected vehicle specifications from the Vehicle Selector
 *
 * @package Harry_WheelPros_Importer
 * @since 1.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Example 1: Filter products based on vehicle selection (JavaScript event)
 *
 * Add this to your theme's footer or a custom JS file:
 */
function hp_example_vehicle_filter_js() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Listen for vehicle selection event
        $(document).on('hp_vehicle_selected', function(event, vehicle) {
            console.log('Selected vehicle:', vehicle);

            // Example: Redirect to shop page with vehicle parameters
            var searchUrl = '/shop/?year=' + vehicle.year +
                           '&make=' + encodeURIComponent(vehicle.make) +
                           '&model=' + encodeURIComponent(vehicle.model);

            if (vehicle.submodel) {
                searchUrl += '&submodel=' + encodeURIComponent(vehicle.submodel);
            }

            window.location.href = searchUrl;
        });
    });
    </script>
    <?php
}
// add_action('wp_footer', 'hp_example_vehicle_filter_js');

/**
 * Example 2: Modify WheelPros product query based on vehicle selection
 *
 * This filters products based on URL parameters from vehicle selection
 */
function hp_filter_products_by_vehicle( $query ) {
    // Only modify main query on shop/archive pages
    if ( ! is_admin() && $query->is_main_query() && ( is_post_type_archive( 'hp_wheel' ) || is_tax() ) ) {

        $year = isset( $_GET['year'] ) ? intval( $_GET['year'] ) : 0;
        $make = isset( $_GET['make'] ) ? sanitize_text_field( $_GET['make'] ) : '';
        $model = isset( $_GET['model'] ) ? sanitize_text_field( $_GET['model'] ) : '';
        $submodel = isset( $_GET['submodel'] ) ? sanitize_text_field( $_GET['submodel'] ) : '';

        if ( $year && $make && $model ) {
            // Get vehicle specs from WheelPros API
            $vehicle_specs = hp_get_vehicle_specs_cached( $year, $make, $model, $submodel );

            if ( $vehicle_specs ) {
                // Add meta query to filter by bolt pattern, diameter, etc.
                $meta_query = $query->get( 'meta_query' ) ?: array();

                // Example: Filter by bolt pattern
                if ( isset( $vehicle_specs['axles']['front']['boltPatternMm'] ) ) {
                    $bolt_pattern = $vehicle_specs['axles']['front']['boltPatternMm'];
                    $meta_query[] = array(
                        'key' => 'bolt_pattern',
                        'value' => $bolt_pattern,
                        'compare' => '=',
                    );
                }

                // Example: Filter by diameter range
                if ( isset( $vehicle_specs['axles']['front']['oeDiameterIn'] ) ) {
                    $diameter = floatval( $vehicle_specs['axles']['front']['oeDiameterIn'] );
                    $meta_query[] = array(
                        'key' => 'diameter',
                        'value' => array( $diameter - 1, $diameter + 1 ),
                        'compare' => 'BETWEEN',
                        'type' => 'DECIMAL(10,2)',
                    );
                }

                $query->set( 'meta_query', $meta_query );

                // Store vehicle info for display
                set_query_var( 'selected_vehicle', array(
                    'year' => $year,
                    'make' => $make,
                    'model' => $model,
                    'submodel' => $submodel,
                    'specs' => $vehicle_specs,
                ) );
            }
        }
    }
}
// add_action('pre_get_posts', 'hp_filter_products_by_vehicle');

/**
 * Get vehicle specs from WheelPros API with caching
 */
function hp_get_vehicle_specs_cached( $year, $make, $model, $submodel = '' ) {
    // Create cache key
    $cache_key = 'hp_vehicle_specs_' . md5( "{$year}_{$make}_{$model}_{$submodel}" );

    // Try to get from cache
    $cached = get_transient( $cache_key );
    if ( $cached !== false ) {
        return $cached;
    }

    // Make API request
    $api_url = 'https://api.wheelpros.com/vehicles/v1/years/' . $year .
               '/makes/' . urlencode( $make ) .
               '/models/' . urlencode( $model );

    if ( $submodel ) {
        $api_url .= '/submodels/' . urlencode( $submodel );
    }

    // Get auth token
    $token = get_transient( 'wheelpros_auth_token' );
    if ( ! $token ) {
        // Token expired, need to re-authenticate
        // This would be handled by your auth class
        return false;
    }

    $response = wp_remote_get( $api_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ),
        'timeout' => 15,
    ) );

    if ( is_wp_error( $response ) ) {
        error_log( 'Vehicle API Error: ' . $response->get_error_message() );
        return false;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $body ) {
        // Cache for 1 week (vehicle specs don't change often)
        set_transient( $cache_key, $body, WEEK_IN_SECONDS );
        return $body;
    }

    return false;
}

/**
 * Example 3: Display selected vehicle info above product listing
 */
function hp_display_selected_vehicle_info() {
    $vehicle = get_query_var( 'selected_vehicle' );

    if ( $vehicle ) {
        ?>
        <div class="hp-selected-vehicle-banner">
            <div class="hp-selected-vehicle-info">
                <h3>Showing products for:</h3>
                <p class="vehicle-name">
                    <?php
                    echo esc_html( $vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model'] );
                    if ( ! empty( $vehicle['submodel'] ) ) {
                        echo ' ' . esc_html( $vehicle['submodel'] );
                    }
                    ?>
                </p>

                <?php if ( ! empty( $vehicle['specs']['axles']['front'] ) ): ?>
                <ul class="vehicle-specs">
                    <li><strong>Bolt Pattern:</strong> <?php echo esc_html( $vehicle['specs']['axles']['front']['boltPatternMm'] ?? 'N/A' ); ?></li>
                    <li><strong>Center Bore:</strong> <?php echo esc_html( $vehicle['specs']['axles']['front']['centerBoreMm'] ?? 'N/A' ); ?> mm</li>
                    <li><strong>OE Tire Size:</strong> <?php echo esc_html( $vehicle['specs']['axles']['front']['oeTireTx'] ?? 'N/A' ); ?></li>
                </ul>
                <?php endif; ?>
            </div>

            <a href="<?php echo esc_url( remove_query_arg( array( 'year', 'make', 'model', 'submodel' ) ) ); ?>" class="clear-vehicle">
                Clear Vehicle Selection
            </a>
        </div>

        <style>
        .hp-selected-vehicle-banner {
            background: #f0f9ff;
            border: 2px solid #0073aa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .hp-selected-vehicle-info h3 {
            margin: 0 0 10px 0;
            color: #0073aa;
        }
        .vehicle-name {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 10px 0;
        }
        .vehicle-specs {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .vehicle-specs li {
            font-size: 14px;
        }
        .clear-vehicle {
            background: #dc3232;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            white-space: nowrap;
        }
        .clear-vehicle:hover {
            background: #a00;
        }
        </style>
        <?php
    }
}
// add_action('woocommerce_before_shop_loop', 'hp_display_selected_vehicle_info', 5);
// Or for custom theme: add_action('wheelpros_before_archive', 'hp_display_selected_vehicle_info');

/**
 * Example 4: Custom callback function for vehicle selection
 *
 * Usage in shortcode: [wheelpros_vehicle_search callback="myCustomVehicleHandler"]
 */
?>
<script>
function myCustomVehicleHandler(vehicle) {
    console.log('Custom handler called with:', vehicle);

    // Example: Load products via AJAX instead of page reload
    jQuery.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
            action: 'load_vehicle_products',
            year: vehicle.year,
            make: vehicle.make,
            model: vehicle.model,
            submodel: vehicle.submodel,
            nonce: '<?php echo wp_create_nonce( "load_vehicle_products" ); ?>'
        },
        success: function(response) {
            if (response.success) {
                jQuery('#product-container').html(response.data.html);
            }
        }
    });
}
</script>
<?php

/**
 * Example 5: AJAX handler for loading products by vehicle
 */
function hp_ajax_load_vehicle_products() {
    check_ajax_referer( 'load_vehicle_products', 'nonce' );

    $year = isset( $_POST['year'] ) ? intval( $_POST['year'] ) : 0;
    $make = isset( $_POST['make'] ) ? sanitize_text_field( $_POST['make'] ) : '';
    $model = isset( $_POST['model'] ) ? sanitize_text_field( $_POST['model'] ) : '';

    // Get vehicle specs
    $specs = hp_get_vehicle_specs_cached( $year, $make, $model );

    // Query products matching vehicle
    $args = array(
        'post_type' => 'hp_wheel',
        'posts_per_page' => 20,
        'post_status' => 'publish',
    );

    if ( $specs && isset( $specs['axles']['front']['boltPatternMm'] ) ) {
        $args['meta_query'] = array(
            array(
                'key' => 'bolt_pattern',
                'value' => $specs['axles']['front']['boltPatternMm'],
                'compare' => '=',
            ),
        );
    }

    $products = new WP_Query( $args );

    ob_start();
    if ( $products->have_posts() ) {
        while ( $products->have_posts() ) {
            $products->the_post();
            // Use your template part for product display
            get_template_part( 'template-parts/product', 'wheel' );
        }
    } else {
        echo '<p>No products found for your vehicle.</p>';
    }
    $html = ob_get_clean();
    wp_reset_postdata();

    wp_send_json_success( array( 'html' => $html, 'count' => $products->found_posts ) );
}
add_action( 'wp_ajax_load_vehicle_products', 'hp_ajax_load_vehicle_products' );
add_action( 'wp_ajax_nopriv_load_vehicle_products', 'hp_ajax_load_vehicle_products' );
