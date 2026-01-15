<?php
/**
 * Vehicle Selector for WheelPros Product Fitment
 *
 * Provides AJAX-powered cascading dropdowns for year/make/model/trim selection
 * and integrates with WheelPros Vehicle API for fitment data
 *
 * @package Harry_WheelPros_Importer
 * @since 1.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HP_WheelPros_Vehicle_Selector {

    /**
     * WheelPros API credentials
     */
    private $api_username;
    private $api_password;
    private $api_base_url = 'https://api.wheelpros.com';
    private $auth_token;
    private $token_expiry;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_username = get_option('wheelpros_api_username');
        $this->api_password = get_option('wheelpros_api_password');

        // Register AJAX handlers
        add_action('wp_ajax_hp_get_vehicle_years', array($this, 'ajax_get_years'));
        add_action('wp_ajax_nopriv_hp_get_vehicle_years', array($this, 'ajax_get_years'));

        add_action('wp_ajax_hp_get_vehicle_makes', array($this, 'ajax_get_makes'));
        add_action('wp_ajax_nopriv_hp_get_vehicle_makes', array($this, 'ajax_get_makes'));

        add_action('wp_ajax_hp_get_vehicle_models', array($this, 'ajax_get_models'));
        add_action('wp_ajax_nopriv_hp_get_vehicle_models', array($this, 'ajax_get_models'));

        add_action('wp_ajax_hp_get_vehicle_submodels', array($this, 'ajax_get_submodels'));
        add_action('wp_ajax_nopriv_hp_get_vehicle_submodels', array($this, 'ajax_get_submodels'));

        add_action('wp_ajax_hp_get_vehicle_specs', array($this, 'ajax_get_vehicle_specs'));
        add_action('wp_ajax_nopriv_hp_get_vehicle_specs', array($this, 'ajax_get_vehicle_specs'));

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Get or refresh authentication token
     */
    private function get_auth_token() {
        // Check if we have a valid cached token
        $cached_token = get_transient('wheelpros_auth_token');
        if ($cached_token) {
            return $cached_token;
        }

        // Request new token
        $response = wp_remote_post($this->api_base_url . '/auth/v1/authorize', array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'userName' => $this->api_username,
                'password' => $this->api_password,
            )),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            error_log('WheelPros Auth Error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['accessToken'])) {
            $token = $body['accessToken'];
            $expires_in = isset($body['expiresIn']) ? intval($body['expiresIn']) : 3600;

            // Cache token (subtract 60 seconds for safety margin)
            set_transient('wheelpros_auth_token', $token, $expires_in - 60);

            return $token;
        }

        return false;
    }

    /**
     * Make authenticated API request
     */
    private function api_request($endpoint, $method = 'GET', $body = null) {
        $token = $this->get_auth_token();

        if (!$token) {
            return array('error' => 'Authentication failed');
        }

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        );

        if ($body && $method !== 'GET') {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request($this->api_base_url . $endpoint, $args);

        if (is_wp_error($response)) {
            error_log('WheelPros API Error: ' . $response->get_error_message());
            return array('error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * AJAX: Get vehicle years
     */
    public function ajax_get_years() {
        check_ajax_referer('hp_vehicle_selector', 'nonce');

        // Check if API credentials are configured
        if (empty($this->api_username) || empty($this->api_password)) {
            wp_send_json_error('WheelPros API credentials not configured. Please configure them in WheelPros Importer > Settings.');
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $endpoint = '/vehicles/v1/years';
        if ($type) {
            $endpoint .= '?type=' . urlencode($type);
        }

        $data = $this->api_request($endpoint);

        if (isset($data['error'])) {
            wp_send_json_error($data['error']);
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX: Get vehicle makes for a year
     */
    public function ajax_get_makes() {
        check_ajax_referer('hp_vehicle_selector', 'nonce');

        $year = isset($_POST['year']) ? intval($_POST['year']) : 0;
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

        if (!$year) {
            wp_send_json_error('Year is required');
        }

        $endpoint = "/vehicles/v1/years/{$year}/makes";
        if ($type) {
            $endpoint .= '?type=' . urlencode($type);
        }

        $data = $this->api_request($endpoint);

        if (isset($data['error'])) {
            wp_send_json_error($data['error']);
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX: Get vehicle models for year/make
     */
    public function ajax_get_models() {
        check_ajax_referer('hp_vehicle_selector', 'nonce');

        $year = isset($_POST['year']) ? intval($_POST['year']) : 0;
        $make = isset($_POST['make']) ? sanitize_text_field($_POST['make']) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

        if (!$year || !$make) {
            wp_send_json_error('Year and Make are required');
        }

        $endpoint = "/vehicles/v1/years/{$year}/makes/" . urlencode($make) . "/models";
        if ($type) {
            $endpoint .= '?type=' . urlencode($type);
        }

        $data = $this->api_request($endpoint);

        if (isset($data['error'])) {
            wp_send_json_error($data['error']);
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX: Get vehicle submodels (trims) for year/make/model
     */
    public function ajax_get_submodels() {
        check_ajax_referer('hp_vehicle_selector', 'nonce');

        $year = isset($_POST['year']) ? intval($_POST['year']) : 0;
        $make = isset($_POST['make']) ? sanitize_text_field($_POST['make']) : '';
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

        if (!$year || !$make || !$model) {
            wp_send_json_error('Year, Make, and Model are required');
        }

        $endpoint = "/vehicles/v1/years/{$year}/makes/" . urlencode($make) . "/models/" . urlencode($model) . "/submodels";
        if ($type) {
            $endpoint .= '?type=' . urlencode($type);
        }

        $data = $this->api_request($endpoint);

        if (isset($data['error'])) {
            wp_send_json_error($data['error']);
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX: Get complete vehicle specs
     */
    public function ajax_get_vehicle_specs() {
        check_ajax_referer('hp_vehicle_selector', 'nonce');

        $year = isset($_POST['year']) ? intval($_POST['year']) : 0;
        $make = isset($_POST['make']) ? sanitize_text_field($_POST['make']) : '';
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
        $submodel = isset($_POST['submodel']) ? sanitize_text_field($_POST['submodel']) : '';

        if (!$year || !$make || !$model) {
            wp_send_json_error('Year, Make, and Model are required');
        }

        $endpoint = "/vehicles/v1/years/{$year}/makes/" . urlencode($make) . "/models/" . urlencode($model);

        if ($submodel) {
            $endpoint .= "/submodels/" . urlencode($submodel);
        }

        $data = $this->api_request($endpoint);

        if (isset($data['error'])) {
            wp_send_json_error($data['error']);
        }

        wp_send_json_success($data);
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only load if the shortcode is present on the page
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'wheelpros_vehicle_search')) {
            return;
        }

        wp_enqueue_script(
            'hp-vehicle-selector',
            plugins_url('js/vehicle-selector.js', dirname(__FILE__)),
            array('jquery'),
            '1.10.0',
            true
        );

        wp_localize_script('hp-vehicle-selector', 'hpVehicleSelector', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hp_vehicle_selector'),
            'strings' => array(
                'selectYear' => __('Select Year', 'harrys-wheelpros'),
                'selectMake' => __('Select Make', 'harrys-wheelpros'),
                'selectModel' => __('Select Model', 'harrys-wheelpros'),
                'selectTrim' => __('Select Trim', 'harrys-wheelpros'),
                'loading' => __('Loading...', 'harrys-wheelpros'),
                'error' => __('Error loading data', 'harrys-wheelpros'),
            ),
        ));

        wp_enqueue_style(
            'hp-vehicle-selector',
            plugins_url('css/vehicle-selector.css', dirname(__FILE__)),
            array(),
            '1.10.0'
        );
    }

    /**
     * Render vehicle selector form
     */
    public static function render_form($args = array()) {
        $defaults = array(
            'type' => '', // 'wheel' or 'tire' to filter products
            'show_specs' => false, // Show vehicle specs after selection
            'callback' => '', // JavaScript callback function name
        );

        $args = wp_parse_args($args, $defaults);

        ob_start();
        ?>
        <div class="hp-vehicle-selector" data-type="<?php echo esc_attr($args['type']); ?>" data-callback="<?php echo esc_attr($args['callback']); ?>">
            <div class="hp-vehicle-selector__row">
                <div class="hp-vehicle-selector__field">
                    <label for="hp-vehicle-year"><?php _e('Year', 'harrys-wheelpros'); ?></label>
                    <select id="hp-vehicle-year" name="vehicle_year" required>
                        <option value=""><?php _e('Select Year', 'harrys-wheelpros'); ?></option>
                    </select>
                </div>

                <div class="hp-vehicle-selector__field">
                    <label for="hp-vehicle-make"><?php _e('Make', 'harrys-wheelpros'); ?></label>
                    <select id="hp-vehicle-make" name="vehicle_make" disabled required>
                        <option value=""><?php _e('Select Make', 'harrys-wheelpros'); ?></option>
                    </select>
                </div>

                <div class="hp-vehicle-selector__field">
                    <label for="hp-vehicle-model"><?php _e('Model', 'harrys-wheelpros'); ?></label>
                    <select id="hp-vehicle-model" name="vehicle_model" disabled required>
                        <option value=""><?php _e('Select Model', 'harrys-wheelpros'); ?></option>
                    </select>
                </div>

                <div class="hp-vehicle-selector__field">
                    <label for="hp-vehicle-submodel"><?php _e('Trim', 'harrys-wheelpros'); ?></label>
                    <select id="hp-vehicle-submodel" name="vehicle_submodel" disabled>
                        <option value=""><?php _e('Select Trim (Optional)', 'harrys-wheelpros'); ?></option>
                    </select>
                </div>
            </div>

            <div class="hp-vehicle-selector__actions">
                <button type="button" class="hp-vehicle-selector__submit" disabled>
                    <?php _e('Search Products', 'harrys-wheelpros'); ?>
                </button>
                <button type="button" class="hp-vehicle-selector__reset" style="display:none;">
                    <?php _e('Reset', 'harrys-wheelpros'); ?>
                </button>
            </div>

            <?php if ($args['show_specs']): ?>
            <div class="hp-vehicle-specs" style="display:none;">
                <h3><?php _e('Vehicle Specifications', 'harrys-wheelpros'); ?></h3>
                <div class="hp-vehicle-specs__content"></div>
            </div>
            <?php endif; ?>

            <div class="hp-vehicle-selector__loading" style="display:none;">
                <span class="spinner"></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize
new HP_WheelPros_Vehicle_Selector();
