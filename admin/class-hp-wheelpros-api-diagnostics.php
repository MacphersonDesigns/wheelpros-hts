<?php
/**
 * API Diagnostics Page
 *
 * Tests WheelPros API connection and displays configuration status
 *
 * @package Harry_WheelPros_Importer
 * @since 1.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HP_WheelPros_API_Diagnostics {

    /**
     * Initialize
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 40 );
        add_action( 'wp_ajax_hp_test_api_connection', array( __CLASS__, 'ajax_test_api_connection' ) );
    }

    /**
     * Add admin menu page
     */
    public static function add_menu_page() {
        add_submenu_page(
            'hp-wheelpros',
            'API Diagnostics',
            'API Diagnostics',
            'manage_options',
            'wheelpros-api-diagnostics',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Render diagnostic page
     */
    public static function render_page() {
        $api_username = get_option('wheelpros_api_username');
        $api_password = get_option('wheelpros_api_password');
        $auth_token = get_transient('wheelpros_auth_token');

        ?>
        <div class="wrap">
            <h1>WheelPros API Diagnostics</h1>

            <div class="hp-diagnostics-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">

                <!-- Configuration Status -->
                <div class="hp-diag-card">
                    <h2>Configuration Status</h2>
                    <table class="widefat">
                        <tr>
                            <td><strong>API Username:</strong></td>
                            <td>
                                <?php if ( empty( $api_username ) ): ?>
                                    <span style="color: #d63638;">❌ Not configured</span>
                                <?php else: ?>
                                    <span style="color: #00a32a;">✓ Configured</span>
                                    <code><?php echo esc_html( substr( $api_username, 0, 20 ) . '...' ); ?></code>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>API Password:</strong></td>
                            <td>
                                <?php if ( empty( $api_password ) ): ?>
                                    <span style="color: #d63638;">❌ Not configured</span>
                                <?php else: ?>
                                    <span style="color: #00a32a;">✓ Configured</span>
                                    <code>••••••••</code>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Auth Token:</strong></td>
                            <td>
                                <?php if ( empty( $auth_token ) ): ?>
                                    <span style="color: #f0b849;">⚠ No cached token</span>
                                    <br><small>Token will be fetched on first API call</small>
                                <?php else: ?>
                                    <span style="color: #00a32a;">✓ Cached</span>
                                    <code><?php echo esc_html( substr( $auth_token, 0, 20 ) . '...' ); ?></code>
                                    <br><small>Expires in: <?php echo self::get_token_expiry(); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <?php if ( empty( $api_username ) || empty( $api_password ) ): ?>
                        <div class="notice notice-error inline" style="margin-top: 15px;">
                            <p>
                                <strong>API credentials not configured!</strong><br>
                                Go to <a href="<?php echo admin_url( 'admin.php?page=hp-wheelpros-settings' ); ?>">WheelPros → Settings</a>
                                to configure your WheelPros API username and password.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Test Connection -->
                <div class="hp-diag-card">
                    <h2>Test API Connection</h2>
                    <p>Test your connection to the WheelPros API:</p>

                    <div style="margin: 15px 0;">
                        <button type="button" class="button button-primary" id="hp-test-auth-api">
                            Test Authentication API
                        </button>
                        <button type="button" class="button" id="hp-test-vehicle-api">
                            Test Vehicle API
                        </button>
                        <span class="spinner" style="float: none; margin: 0 10px;"></span>
                    </div>

                    <div id="hp-test-results" style="margin-top: 15px;"></div>
                </div>

            </div>

            <!-- API Endpoints -->
            <div class="hp-diag-card" style="margin-top: 20px;">
                <h2>API Endpoints</h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>API</th>
                            <th>Endpoint</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Authentication</strong></td>
                            <td><code>https://api.wheelpros.com/auth/v1/authorize</code></td>
                            <td><span id="status-auth">-</span></td>
                        </tr>
                        <tr>
                            <td><strong>Vehicle API</strong></td>
                            <td><code>https://api.wheelpros.com/vehicles/v1/years</code></td>
                            <td><span id="status-vehicle">-</span></td>
                        </tr>
                        <tr>
                            <td><strong>Product API</strong></td>
                            <td><code>https://api.wheelpros.com/products/v1/search/wheel</code></td>
                            <td><span id="status-product">-</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Troubleshooting -->
            <div class="hp-diag-card" style="margin-top: 20px;">
                <h2>Troubleshooting Vehicle Search</h2>
                <ol>
                    <li><strong>Configure API credentials:</strong> Go to WheelPros → Settings and add your WheelPros API username and password (in the "WheelPros API (Vehicle Search)" section)</li>
                    <li><strong>Test authentication:</strong> Click "Test Authentication API" above to verify credentials work</li>
                    <li><strong>Add shortcode to page:</strong> Use <code>[wheelpros_vehicle_search]</code> on a page</li>
                    <li><strong>Check browser console:</strong> Open browser DevTools (F12) and check Console for errors</li>
                    <li><strong>Verify API endpoints:</strong> The authentication and vehicle API endpoints should return HTTP 200 responses</li>
                </ol>
                <p><strong>Note:</strong> These are WheelPros API credentials for vehicle search, not your SFTP credentials for product imports.</p>
            </div>
        </div>

        <style>
        .hp-diag-card {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .hp-diag-card h2 {
            margin-top: 0;
        }
        .hp-diag-card table {
            margin-top: 15px;
        }
        .hp-diag-card table td {
            padding: 10px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('#hp-test-auth-api').on('click', function() {
                testAPI('auth');
            });

            $('#hp-test-vehicle-api').on('click', function() {
                testAPI('vehicle');
            });

            function testAPI(type) {
                var $btn = $('#hp-test-' + type + '-api');
                var $spinner = $('.spinner');
                var $results = $('#hp-test-results');

                $btn.prop('disabled', true);
                $spinner.addClass('is-active');
                $results.html('<div class="notice notice-info inline"><p>Testing connection...</p></div>');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'hp_test_api_connection',
                        nonce: '<?php echo wp_create_nonce( 'hp_test_api' ); ?>',
                        test_type: type
                    },
                    success: function(response) {
                        if (response.success) {
                            $results.html(
                                '<div class="notice notice-success inline">' +
                                '<p><strong>✓ Success!</strong></p>' +
                                '<pre style="background: #f0f0f1; padding: 10px; overflow: auto; max-height: 300px;">' +
                                JSON.stringify(response.data, null, 2) +
                                '</pre></div>'
                            );
                            $('#status-' + type).html('<span style="color: #00a32a;">✓ Working</span>');

                            // Update auth token display if this was an auth test
                            if (type === 'auth' && response.data.token_preview) {
                                $('.hp-diag-card table tr:nth-child(3) td:nth-child(2)').html(
                                    '<span style="color: #00a32a;">✓ Cached</span> ' +
                                    '<code>' + response.data.token_preview + '</code><br>' +
                                    '<small>Expires in: ' + response.data.expires_in + '</small>'
                                );
                            }
                        } else {
                            $results.html(
                                '<div class="notice notice-error inline">' +
                                '<p><strong>❌ Error:</strong> ' + (response.data || 'Unknown error') + '</p>' +
                                '</div>'
                            );
                            $('#status-' + type).html('<span style="color: #d63638;">❌ Failed</span>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $results.html(
                            '<div class="notice notice-error inline">' +
                            '<p><strong>❌ AJAX Error:</strong> ' + error + '</p>' +
                            '</div>'
                        );
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
     * Get token expiry time
     */
    private static function get_token_expiry() {
        $expiry = get_transient( 'wheelpros_auth_token_expiry' );
        if ( ! $expiry ) {
            return 'Unknown';
        }

        $remaining = $expiry - time();
        if ( $remaining <= 0 ) {
            return 'Expired';
        }

        return human_time_diff( time(), $expiry );
    }

    /**
     * AJAX: Test API connection
     */
    public static function ajax_test_api_connection() {
        check_ajax_referer( 'hp_test_api', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $test_type = isset( $_POST['test_type'] ) ? sanitize_text_field( $_POST['test_type'] ) : 'auth';

        $api_username = get_option( 'wheelpros_api_username' );
        $api_password = get_option( 'wheelpros_api_password' );

        if ( empty( $api_username ) || empty( $api_password ) ) {
            wp_send_json_error( 'API credentials not configured. Please add them in Settings.' );
        }

        if ( $test_type === 'auth' ) {
            // Test authentication
            $response = wp_remote_post( 'https://api.wheelpros.com/auth/v1/authorize', array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode( array(
                    'userName' => $api_username,
                    'password' => $api_password,
                ) ),
                'timeout' => 30,
            ) );

            if ( is_wp_error( $response ) ) {
                wp_send_json_error( $response->get_error_message() );
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( isset( $body['accessToken'] ) ) {
                // Save token for future use
                set_transient( 'wheelpros_auth_token', $body['accessToken'], 3540 );
                wp_send_json_success( array(
                    'message' => 'Authentication successful!',
                    'token_preview' => substr( $body['accessToken'], 0, 20 ) . '...',
                    'expires_in' => $body['expiresIn'] . ' seconds',
                ) );
            } else {
                wp_send_json_error( 'Authentication failed: ' . json_encode( $body ) );
            }

        } elseif ( $test_type === 'vehicle' ) {
            // Get auth token first
            $token = get_transient( 'wheelpros_auth_token' );
            if ( ! $token ) {
                // Try to get new token
                $auth_response = wp_remote_post( 'https://api.wheelpros.com/auth/v1/authorize', array(
                    'headers' => array( 'Content-Type' => 'application/json' ),
                    'body' => json_encode( array(
                        'userName' => $api_username,
                        'password' => $api_password,
                    ) ),
                    'timeout' => 30,
                ) );

                if ( is_wp_error( $auth_response ) ) {
                    wp_send_json_error( 'Auth error: ' . $auth_response->get_error_message() );
                }

                $auth_body = json_decode( wp_remote_retrieve_body( $auth_response ), true );
                $token = $auth_body['accessToken'] ?? null;

                if ( ! $token ) {
                    wp_send_json_error( 'Could not get auth token' );
                }

                set_transient( 'wheelpros_auth_token', $token, 3540 );
            }

            // Test vehicle API
            $response = wp_remote_get( 'https://api.wheelpros.com/vehicles/v1/years', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 15,
            ) );

            if ( is_wp_error( $response ) ) {
                wp_send_json_error( $response->get_error_message() );
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( is_array( $body ) ) {
                wp_send_json_success( array(
                    'message' => 'Vehicle API working!',
                    'years_count' => count( $body ),
                    'sample_years' => array_slice( $body, 0, 10 ),
                ) );
            } else {
                wp_send_json_error( 'Unexpected response: ' . json_encode( $body ) );
            }
        }

        wp_send_json_error( 'Invalid test type' );
    }
}

// Initialize
HP_WheelPros_API_Diagnostics::init();
