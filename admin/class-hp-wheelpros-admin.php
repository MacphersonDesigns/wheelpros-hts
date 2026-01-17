<?php
/**
 * Admin class for the WheelPros importer plugin.
 *
 * This class handles creating the top‑level plugin menu, rendering the
 * settings, import and log pages, and saving form data. All forms use
 * WordPress nonces and capability checks to ensure only authorized users
 * (default capability: manage_options) can alter settings or run imports.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HP_WheelPros_Admin {

    /** @var HP_WheelPros_Admin */
    protected static $instance = null;

    /**
     * Returns the singleton instance of this class.
     *
     * @return HP_WheelPros_Admin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init_hooks();
        }
        return self::$instance;
    }

    /**
     * Initialize admin hooks.
     */
    protected function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        // AJAX hooks for progress import.
        // Migration action to convert taxonomy data to meta fields
        add_action( 'wp_ajax_hp_migrate_taxonomy_to_meta', array( $this, 'ajax_migrate_taxonomy_to_meta' ) );

        // Two-phase import system
        add_action( 'wp_ajax_hp_download_csv', array( $this, 'ajax_download_csv' ) );
        add_action( 'wp_ajax_hp_process_cached_csv', array( $this, 'ajax_process_cached_csv' ) );

        // Import state management (resume/clear)
        add_action( 'wp_ajax_hp_save_import_state', array( $this, 'ajax_save_import_state' ) );
        add_action( 'wp_ajax_hp_clear_import_state', array( $this, 'ajax_clear_import_state' ) );

        // Broken image tracking
        add_action( 'wp_ajax_hp_mark_image_broken', array( $this, 'ajax_mark_image_broken' ) );

        // Image validation utility
        add_action( 'wp_ajax_hp_validate_wheel_images', array( $this, 'ajax_validate_wheel_images' ) );
    }

    /**
     * Adds top‑level menu and submenus for the plugin.
     */
    public function add_menu() {
        // Top level menu item.
        add_menu_page(
            __( 'WheelPros Importer', 'wheelpros-importer' ),
            __( 'WheelPros', 'wheelpros-importer' ),
            'manage_options',
            'hp-wheelpros',
            array( $this, 'render_import_page' ),
            'dashicons-admin-generic',
            56
        );

        // Submenu: Import.
        add_submenu_page(
            'hp-wheelpros',
            __( 'Import Wheels', 'wheelpros-importer' ),
            __( 'Import', 'wheelpros-importer' ),
            'manage_options',
            'hp-wheelpros',
            array( $this, 'render_import_page' )
        );

        // Submenu: Settings.
        add_submenu_page(
            'hp-wheelpros',
            __( 'WheelPros Settings', 'wheelpros-importer' ),
            __( 'Settings', 'wheelpros-importer' ),
            'manage_options',
            'hp-wheelpros-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register plugin settings using Settings API.
     */
    public function register_settings() {
        // Register a settings section for SFTP credentials and import options.
        register_setting( 'hp_wheelpros_settings', 'hp_wheelpros_options', array( $this, 'sanitize_options' ) );

        // Register API credentials separately (they're not part of hp_wheelpros_options)
        register_setting( 'hp_wheelpros_settings', 'wheelpros_api_username', 'sanitize_text_field' );
        register_setting( 'hp_wheelpros_settings', 'wheelpros_api_password', array( $this, 'sanitize_api_password' ) );

        add_settings_section(
            'hp_wheelpros_sftp_section',
            __( 'SFTP & File Settings', 'wheelpros-importer' ),
            '__return_false',
            'hp-wheelpros-settings'
        );
        // Host.
        add_settings_field(
            'hp_sftp_host',
            __( 'SFTP Host', 'wheelpros-importer' ),
            array( $this, 'render_sftp_host_field' ),
            'hp-wheelpros-settings',
            'hp_wheelpros_sftp_section'
        );
        // Port.
        add_settings_field(
            'hp_sftp_port',
            __( 'SFTP Port', 'wheelpros-importer' ),
            array( $this, 'render_sftp_port_field' ),
            'hp-wheelpros-settings',
            'hp_wheelpros_sftp_section'
        );
        // Username.
        add_settings_field(
            'hp_sftp_username',
            __( 'SFTP Username', 'wheelpros-importer' ),
            array( $this, 'render_sftp_username_field' ),
            'hp-wheelpros-settings',
            'hp_wheelpros_sftp_section'
        );
        // Password.
        add_settings_field(
            'hp_sftp_password',
            __( 'SFTP Password', 'wheelpros-importer' ),
            array( $this, 'render_sftp_password_field' ),
            'hp-wheelpros-settings',
            'hp_wheelpros_sftp_section'
        );
        // Remote path.
        // Remote file path – single path for wheel import. Users should specify the
        // relative path to the CSV or JSON file on the SFTP server.
        add_settings_field(
            'hp_path',
            __( 'Remote File Path', 'wheelpros-importer' ),
            array( $this, 'render_path_field' ),
            'hp-wheelpros-settings',
            'hp_wheelpros_sftp_section'
        );
        // File type (CSV/JSON).
        add_settings_field(
            'hp_file_type',
            __( 'File Type', 'wheelpros-importer' ),
            array( $this, 'render_file_type_field' ),
            'hp-wheelpros-settings',
            'hp_wheelpros_sftp_section'
        );

        // Contact options section – allow store owner to define a phone number and quote form URL
        add_settings_section(
            'hp_wheelpros_contact_section',
            __( 'Contact Options', 'wheelpros-importer' ),
            '__return_false',
            'hp-wheelpros-settings'
        );
        // Phone number field
        add_settings_field(
            'hp_call_phone',
            __( 'Contact Phone Number', 'wheelpros-importer' ),
            array( $this, 'render_call_phone_field' ),
            'hp-wheelpros-settings',
            'hp_wheelpros_contact_section'
        );
        // Quote form URL field
        add_settings_field(
            'hp_quote_url',
            __( 'Quote Form URL', 'wheelpros-importer' ),
            array( $this, 'render_quote_url_field' ),
            'hp-wheelpros-settings',
            'hp_wheelpros_contact_section'
        );

        // WheelPros API section for vehicle search
        add_settings_section(
            'hp_wheelpros_api_section',
            __( 'WheelPros API (Vehicle Search)', 'wheelpros-importer' ),
            array( $this, 'render_api_section_description' ),
            'hp-wheelpros-settings'
        );

        // API Username
        add_settings_field(
            'hp_api_username',
            __( 'API Username', 'wheelpros-importer' ),
            array( $this, 'render_api_username_field' ),
            'hp-wheelpros-settings',
            'hp_wheelpros_api_section'
        );

        // API Password
        add_settings_field(
            'hp_api_password',
            __( 'API Password', 'wheelpros-importer' ),
            array( $this, 'render_api_password_field' ),
            'hp-wheelpros-settings',
            'hp_wheelpros_api_section'
        );
    }

    /**
     * Sanitize settings on save.
     *
     * This callback ensures each option is validated and sanitized. Sensitive
     * values (password) are encrypted using helper functions in the core
     * class. Following WordPress guidance, we validate input early and
     * sanitize output late【726752494393687†L48-L73】.
     *
     * @param array $input Raw option values from the form.
     * @return array Sanitized values.
     */
    public function sanitize_options( $input ) {
        $opts = array();
        // Host: basic text.
        if ( isset( $input['host'] ) ) {
            $opts['host'] = sanitize_text_field( $input['host'] );
        }
        // Port: numeric.
        if ( isset( $input['port'] ) ) {
            $opts['port'] = absint( $input['port'] );
        }
        // Username: text.
        if ( isset( $input['username'] ) ) {
            $opts['username'] = sanitize_text_field( $input['username'] );
        }
        // Password: encrypt before saving. We intentionally do not sanitize the password
        // with sanitize_text_field() because that can strip special characters and
        // break authentication. Instead we use the raw value as entered. Only
        // encrypt when a new value is provided; otherwise preserve the existing
        // encrypted password. Empty inputs mean the user wants to keep the
        // current password.
        if ( isset( $input['password'] ) && $input['password'] !== '' ) {
            // Remove any slashes added by WP.
            $raw_password      = wp_unslash( $input['password'] );
            $opts['password'] = HP_WheelPros_Core::encrypt( $raw_password );
        } else {
            // Preserve existing password if not provided.
            $options = get_option( 'hp_wheelpros_options' );
            if ( isset( $options['password'] ) ) {
                $opts['password'] = $options['password'];
            }
        }
        // Remote path: text.
        if ( isset( $input['path'] ) ) {
            $opts['path'] = sanitize_text_field( $input['path'] );
        }
        // No longer support separate wheel/tire/accessory paths – use single 'path'.
        // File type: allow only csv or json.
        if ( isset( $input['type'] ) && in_array( $input['type'], array( 'csv', 'json' ), true ) ) {
            $opts['type'] = $input['type'];
        } else {
            $opts['type'] = 'csv';
        }

        // Phone number: sanitize and strip non-numeric (keep plus and hyphen)
        if ( isset( $input['call_phone'] ) ) {
            $phone = sanitize_text_field( $input['call_phone'] );
            // Remove any invalid characters but keep numbers, plus, hyphen and spaces
            $phone = preg_replace( '/[^\d\+\-\s]/', '', $phone );
            $opts['call_phone'] = $phone;
        }
        // Quote form URL: ensure it's a valid URL
        if ( isset( $input['quote_url'] ) ) {
            $url = esc_url_raw( $input['quote_url'] );
            $opts['quote_url'] = $url;
        }
        return $opts;
    }

    /**
     * Sanitize API password
     * Keep existing password if blank
     */
    public function sanitize_api_password( $input ) {
        if ( empty( $input ) ) {
            // Keep existing password
            return get_option( 'wheelpros_api_password', '' );
        }
        return sanitize_text_field( wp_unslash( $input ) );
    }

    /* -----------------------------------------------------------------------
     * Settings field render callbacks
     * Each method outputs the appropriate input field for its setting.
     * The current values are retrieved from the options array.
     */
    public function render_sftp_host_field() {
        $options = get_option( 'hp_wheelpros_options' );
        $value   = isset( $options['host'] ) ? esc_attr( $options['host'] ) : '';
        printf( '<input type="text" name="hp_wheelpros_options[host]" value="%s" class="regular-text" />', $value );
    }

    public function render_sftp_port_field() {
        $options = get_option( 'hp_wheelpros_options' );
        $value   = isset( $options['port'] ) ? absint( $options['port'] ) : 22;
        printf( '<input type="number" name="hp_wheelpros_options[port]" value="%d" class="small-text" min="1" max="65535" />', $value );
    }

    public function render_sftp_username_field() {
        $options = get_option( 'hp_wheelpros_options' );
        $value   = isset( $options['username'] ) ? esc_attr( $options['username'] ) : '';
        printf( '<input type="text" name="hp_wheelpros_options[username]" value="%s" class="regular-text" />', $value );
    }

    public function render_sftp_password_field() {
        // Always blank – we don't display decrypted password.
        printf( '<input type="password" name="hp_wheelpros_options[password]" value="" class="regular-text" autocomplete="new-password" />' );
        echo '<p class="description">';
        esc_html_e( 'Leave blank to keep the current password.', 'wheelpros-importer' );
        echo '</p>';
    }

    public function render_sftp_path_field() {
        $options = get_option( 'hp_wheelpros_options' );
        $value   = isset( $options['path'] ) ? esc_attr( $options['path'] ) : '/path/to/wheels.csv';
        printf( '<input type="text" name="hp_wheelpros_options[path]" value="%s" class="regular-text" />', $value );
    }

    public function render_file_type_field() {
        $options = get_option( 'hp_wheelpros_options' );
        $type    = isset( $options['type'] ) ? $options['type'] : 'csv';
        ?>
        <select name="hp_wheelpros_options[type]">
            <option value="csv" <?php selected( $type, 'csv' ); ?>><?php esc_html_e( 'CSV', 'wheelpros-importer' ); ?></option>
            <option value="json" <?php selected( $type, 'json' ); ?>><?php esc_html_e( 'JSON', 'wheelpros-importer' ); ?></option>
        </select>
        <?php
    }

    /**
     * Render wheel CSV path field.
     */
    /**
     * Render the remote file path input field.
     *
     * This field allows administrators to specify the path to the wheel CSV
     * or JSON file on the SFTP server. It replaces the separate wheel,
     * tire and accessory path fields used in earlier versions.
     */
    public function render_path_field() {
        $options = get_option( 'hp_wheelpros_options' );
        $value   = isset( $options['path'] ) ? esc_attr( $options['path'] ) : '';
        printf( '<input type="text" name="hp_wheelpros_options[path]" value="%s" class="regular-text" />', $value );
    }

    /**
     * Render the call phone input field.
     */
    public function render_call_phone_field() {
        $options = get_option( 'hp_wheelpros_options' );
        $value   = isset( $options['call_phone'] ) ? esc_attr( $options['call_phone'] ) : '';
        printf( '<input type="text" name="hp_wheelpros_options[call_phone]" value="%s" class="regular-text" />', $value );
        echo '<p class="description">';
        esc_html_e( 'Enter the phone number customers should call for pricing inquiries (e.g. +1 555-555-5555).', 'wheelpros-importer' );
        echo '</p>';
    }

    /**
     * Render the quote form URL field.
     */
    public function render_quote_url_field() {
        $options = get_option( 'hp_wheelpros_options' );
        $value   = isset( $options['quote_url'] ) ? esc_url( $options['quote_url'] ) : '';
        printf( '<input type="url" name="hp_wheelpros_options[quote_url]" value="%s" class="regular-text" />', $value );
        echo '<p class="description">';
        esc_html_e( 'Enter the URL of your quote request form. Leave blank to disable the quote button.', 'wheelpros-importer' );
        echo '</p>';
    }

    /**
     * Render API section description
     */
    public function render_api_section_description() {
        echo '<p>';
        esc_html_e( 'Configure your WheelPros API credentials for vehicle search functionality. These are different from your SFTP credentials above and are used for the vehicle fitment search feature.', 'wheelpros-importer' );
        echo '</p>';
    }

    /**
     * Render API username field
     */
    public function render_api_username_field() {
        $value = get_option( 'wheelpros_api_username', '' );
        printf( '<input type="text" name="wheelpros_api_username" value="%s" class="regular-text" />', esc_attr( $value ) );
        echo '<p class="description">';
        esc_html_e( 'Your WheelPros API username (not SFTP username).', 'wheelpros-importer' );
        echo '</p>';
    }

    /**
     * Render API password field
     */
    public function render_api_password_field() {
        $value = get_option( 'wheelpros_api_password', '' );
        // Show masked value if exists
        $display_value = ! empty( $value ) ? '••••••••' : '';
        printf( '<input type="password" name="wheelpros_api_password" value="" class="regular-text" placeholder="%s" autocomplete="new-password" />', esc_attr( $display_value ) );
        echo '<p class="description">';
        esc_html_e( 'Your WheelPros API password. Leave blank to keep current password.', 'wheelpros-importer' );
        echo '</p>';
    }

    /**
     * Render the settings page.
     */
    /**
     * Render settings page content.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // Handle test connection submission.
        if ( isset( $_POST['hp_test_connection'] ) && check_admin_referer( 'hp_test_connection_action', 'hp_test_connection_nonce' ) ) {
            $importer = new HP_WheelPros_Importer();
            $result   = $importer->test_sftp_connection();
            if ( is_wp_error( $result ) ) {
                add_settings_error( 'hp_wheelpros_connection', 'hp_wheelpros_connection_error', $result->get_error_message(), 'error' );
            } else {
                $rows = isset( $result['rows'] ) ? intval( $result['rows'] ) : 0;
                $type = isset( $result['type'] ) ? strtoupper( $result['type'] ) : '';
                add_settings_error( 'hp_wheelpros_connection', 'hp_wheelpros_connection_success', sprintf( __( 'SFTP connection successful. File type: %1$s. %2$d rows detected.', 'wheelpros-importer' ), $type, $rows ), 'updated' );
            }
        }
        // Output any settings errors from the Settings API (including connection test messages).
        settings_errors();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'WheelPros Importer Settings', 'wheelpros-importer' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'hp_wheelpros_settings' );
                do_settings_sections( 'hp-wheelpros-settings' );
                submit_button();
                ?>
            </form>
            <hr />
            <h2><?php esc_html_e( 'Live Connection', 'wheelpros-importer' ); ?></h2>
            <p><?php esc_html_e( 'Use this tool to verify that your SFTP credentials and file path are configured correctly. The plugin will attempt to connect to the server and download the file, reporting the number of rows detected. This does not perform an import.', 'wheelpros-importer' ); ?></p>
            <form method="post">
                <?php wp_nonce_field( 'hp_test_connection_action', 'hp_test_connection_nonce' ); ?>
                <?php submit_button( __( 'Test Connection', 'wheelpros-importer' ), 'secondary', 'hp_test_connection' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the manual import page.
     */
    public function render_import_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // Handle manual import submission (fallback).
        if ( isset( $_POST['hp_manual_import'] ) && check_admin_referer( 'hp_manual_import_action', 'hp_manual_import_nonce' ) ) {
            $this->process_manual_import();
        }

        // Handle clear data submission.
        if ( isset( $_POST['hp_clear_data'] ) && check_admin_referer( 'hp_clear_data_action', 'hp_clear_data_nonce' ) ) {
            $deleted = $this->clear_wheel_data();
            add_settings_error( 'hp_wheelpros_import', 'hp_wheelpros_clear_data', sprintf( __( 'Cleared %d wheel records and associated terms.', 'wheelpros-importer' ), $deleted ), 'updated' );
        }
        // Output any import related messages.
        settings_errors( 'hp_wheelpros_import' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Import Wheels', 'wheelpros-importer' ); ?></h1>
            <p><?php esc_html_e( 'Import wheel data from your configured SFTP server or upload a CSV file manually. The two-phase import method is the recommended approach for large datasets.', 'wheelpros-importer' ); ?></p>

            <?php
            // Check for incomplete/resumable imports
            $resume_data = get_transient( 'hp_import_resume_state' );
            $settings_valid = $this->validate_import_settings();

            // Show settings validation warnings
            if ( ! empty( $settings_valid['errors'] ) ) {
                echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Settings Issues:', 'wheelpros-importer' ) . '</strong></p><ul>';
                foreach ( $settings_valid['errors'] as $error ) {
                    echo '<li>' . esc_html( $error ) . '</li>';
                }
                echo '</ul><p><a href="' . esc_url( admin_url( 'admin.php?page=hp-wheelpros-settings' ) ) . '">' . esc_html__( 'Go to Settings', 'wheelpros-importer' ) . '</a></p></div>';
            }

            // Show resume option if there's an incomplete import
            if ( $resume_data && isset( $resume_data['cache_key'] ) && isset( $resume_data['offset'] ) && $resume_data['offset'] > 0 ) {
                $cache_exists = get_transient( $resume_data['cache_key'] ) || get_transient( $resume_data['cache_key'] . '_meta' );
                if ( $cache_exists ) {
                    ?>
                    <div class="notice notice-warning" id="hp-resume-notice">
                        <p><strong><?php esc_html_e( 'Incomplete Import Detected', 'wheelpros-importer' ); ?></strong></p>
                        <p><?php printf(
                            esc_html__( 'Previous import was interrupted at row %d of %d (%d%% complete). You can resume or start fresh.', 'wheelpros-importer' ),
                            $resume_data['offset'],
                            $resume_data['total_rows'],
                            $resume_data['total_rows'] > 0 ? round( ( $resume_data['offset'] / $resume_data['total_rows'] ) * 100 ) : 0
                        ); ?></p>
                        <p>
                            <button type="button" class="button button-primary" id="hp-resume-import"><?php esc_html_e( 'Resume Import', 'wheelpros-importer' ); ?></button>
                            <button type="button" class="button" id="hp-discard-resume"><?php esc_html_e( 'Start Fresh', 'wheelpros-importer' ); ?></button>
                        </p>
                    </div>
                    <?php
                }
            }
            ?>

            <h2><?php esc_html_e( 'SFTP Import (Recommended)', 'wheelpros-importer' ); ?></h2>
            <p><?php esc_html_e( 'Download and cache the CSV file first, then process it in small batches. This method is reliable and avoids timeout issues with large files.', 'wheelpros-importer' ); ?></p>

            <div style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
                <label style="display: inline-flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="hp-skip-image-validation" checked />
                    <strong><?php esc_html_e( 'Skip image validation during import (faster)', 'wheelpros-importer' ); ?></strong>
                </label>
                <p class="description" style="margin: 5px 0 0 26px;"><?php esc_html_e( 'Uncheck to validate images during import. This is slower but filters out items without working images. You can also validate images separately after import.', 'wheelpros-importer' ); ?></p>
            </div>

            <button id="hp-download-csv" class="button button-primary"><?php esc_html_e( 'Step 1: Download CSV File', 'wheelpros-importer' ); ?></button>
            <button id="hp-process-csv" class="button button-secondary" disabled><?php esc_html_e( 'Step 2: Process Data', 'wheelpros-importer' ); ?></button>

            <div id="hp-import-progress" style="margin-top:15px; display:none; max-width:600px;">
                <div style="background:#e5e5e5; height:20px; width:100%; border-radius:4px; overflow:hidden;">
                    <div id="hp-progress-bar" style="background:#28a745; width:0%; height:100%; transition:width 0.5s;"></div>
                </div>
                <div id="hp-progress-text" style="margin-top:5px; font-size:14px;"></div>
                <div style="display:flex; margin-top:10px; gap:20px;">
                    <div style="flex:1;">
                        <h4><?php echo esc_html( __( 'Import Log', 'wheelpros-importer' ) ); ?></h4>
                        <pre id="hp-import-log" style="max-height:300px; overflow:auto; background:#f8f8f8; padding:10px; border:1px solid #ccc; white-space:pre-wrap;"></pre>
                    </div>
                </div>
            </div>

            <script>
            (function($){
                var cacheKey = '';
                var totalRows = 0;
                var currentOffset = 0;
                var totalImported = 0;
                var totalUpdated = 0;
                var totalSkipped = 0;
                var importRunning = false;
                var retryCount = 0;
                var maxRetries = 3;

                <?php
                // Pass resume data to JavaScript if available
                if ( $resume_data && isset( $resume_data['cache_key'] ) ) {
                    echo 'var resumeData = ' . json_encode( $resume_data ) . ';';
                } else {
                    echo 'var resumeData = null;';
                }
                ?>

                // Resume import handler
                $('#hp-resume-import').on('click', function(e){
                    e.preventDefault();
                    if (!resumeData) return;

                    cacheKey = resumeData.cache_key;
                    totalRows = resumeData.total_rows;
                    currentOffset = resumeData.offset;
                    totalImported = resumeData.imported || 0;
                    totalUpdated = resumeData.updated || 0;
                    totalSkipped = resumeData.skipped || 0;

                    $('#hp-resume-notice').hide();
                    $('#hp-import-progress').show();
                    $('#hp-progress-bar').css('width', ((currentOffset / totalRows) * 100) + '%');
                    $('#hp-progress-text').text('<?php echo esc_js( __( 'Resuming import...', 'wheelpros-importer' ) ); ?>');
                    $('#hp-import-log').text('🔄 Resuming from row ' + currentOffset + '...\n');

                    $('#hp-download-csv').prop('disabled', true);
                    $('#hp-process-csv').prop('disabled', true);

                    startProcessing();
                });

                // Discard resume handler
                $('#hp-discard-resume').on('click', function(e){
                    e.preventDefault();
                    if (!confirm('<?php echo esc_js( __( 'Are you sure? This will clear the cached data and you will need to download again.', 'wheelpros-importer' ) ); ?>')) {
                        return;
                    }

                    $.post(ajaxurl, {
                        action: 'hp_clear_import_state'
                    }, function(){
                        $('#hp-resume-notice').hide();
                        resumeData = null;
                    });
                });

                // Download CSV file
                $('#hp-download-csv').on('click', function(e){
                    e.preventDefault();
                    var $btn = $(this);
                    $btn.prop('disabled', true);
                    $('#hp-process-csv').prop('disabled', true);
                    $('#hp-import-progress').show();
                    $('#hp-progress-bar').css('width', '0%');
                    $('#hp-progress-text').text('<?php echo esc_js( __( 'Downloading CSV file from SFTP server...', 'wheelpros-importer' ) ); ?>');
                    $('#hp-import-log').text('Starting download...\n');

                    // Reset state
                    currentOffset = 0;
                    totalImported = 0;
                    totalUpdated = 0;
                    totalSkipped = 0;

                    $.post(ajaxurl, {
                        action: 'hp_download_csv'
                    }, function(resp){
                        if (resp.success) {
                            cacheKey = resp.data.cache_key;
                            totalRows = resp.data.total_rows;

                            $('#hp-progress-bar').css('width', '100%');
                            $('#hp-progress-text').text('<?php echo esc_js( __( 'Download complete! Ready to process data.', 'wheelpros-importer' ) ); ?>');
                            $('#hp-import-log').append('✅ Download successful!\n');
                            $('#hp-import-log').append('📊 File size: ' + resp.data.file_size + ' bytes\n');
                            $('#hp-import-log').append('📋 Total rows: ' + resp.data.total_rows + '\n');
                            $('#hp-import-log').append('🔧 Header: ' + resp.data.header.join(', ') + '\n\n');

                            $btn.prop('disabled', false);
                            $('#hp-process-csv').prop('disabled', false);
                        } else {
                            $('#hp-progress-text').text('❌ Download failed: ' + resp.data);
                            $('#hp-import-log').append('❌ Download error: ' + resp.data + '\n');
                            $btn.prop('disabled', false);
                        }
                    }, 'json').fail(function(xhr, status, error){
                        $('#hp-progress-text').text('❌ Network error occurred');
                        $('#hp-import-log').append('❌ Network error: ' + error + '\n');
                        $btn.prop('disabled', false);
                    });
                });

                // Process cached CSV
                $('#hp-process-csv').on('click', function(e){
                    e.preventDefault();
                    if (!cacheKey) {
                        alert('Please download the CSV file first');
                        return;
                    }

                    $('#hp-process-csv').prop('disabled', true);
                    $('#hp-download-csv').prop('disabled', true);
                    $('#hp-progress-bar').css('width', '0%');
                    $('#hp-progress-text').text('<?php echo esc_js( __( 'Processing data...', 'wheelpros-importer' ) ); ?>');
                    $('#hp-import-log').append('🔄 Starting data processing...\n');

                    startProcessing();
                });

                function startProcessing() {
                    importRunning = true;
                    retryCount = 0;
                    processBatch();
                }

                function processBatch(){
                    if (!importRunning) return;

                    var skipImageValidation = $('#hp-skip-image-validation').is(':checked') ? 1 : 0;

                    $.post(ajaxurl, {
                        action: 'hp_process_cached_csv',
                        cache_key: cacheKey,
                        offset: currentOffset,
                        skip_image_validation: skipImageValidation
                    }, function(resp){
                        retryCount = 0; // Reset retry count on success

                        if (resp.success) {
                            currentOffset = resp.data.offset;
                            totalImported += resp.data.imported;
                            totalUpdated += resp.data.updated;
                            totalSkipped += resp.data.skipped || 0;

                            var progress = resp.data.progress;
                            $('#hp-progress-bar').css('width', progress + '%');
                            $('#hp-progress-text').text(progress + '% complete (' + currentOffset + '/' + totalRows + ' rows)');

                            // Add log messages
                            if (resp.data.log && Array.isArray(resp.data.log)) {
                                resp.data.log.forEach(function(msg){
                                    $('#hp-import-log').append(msg + '\n');
                                });
                                var logEl = document.getElementById('hp-import-log');
                                logEl.scrollTop = logEl.scrollHeight;
                            }

                            if (resp.data.done) {
                                importRunning = false;
                                var completeMsg = '✅ Import completed! Imported: ' + totalImported + ', Updated: ' + totalUpdated;
                                if (totalSkipped > 0) {
                                    completeMsg += ', Skipped: ' + totalSkipped;
                                }
                                $('#hp-progress-text').text(completeMsg);
                                $('#hp-import-log').append('\n🎉 Import finished successfully!\n');
                                $('#hp-import-log').append('📈 Total imported: ' + totalImported + '\n');
                                $('#hp-import-log').append('🔄 Total updated: ' + totalUpdated + '\n');
                                if (totalSkipped > 0) {
                                    $('#hp-import-log').append('⏭️ Total skipped: ' + totalSkipped + '\n');
                                }
                                $('#hp-process-csv').prop('disabled', false);
                                $('#hp-download-csv').prop('disabled', false);
                            } else {
                                // Continue with next batch
                                setTimeout(processBatch, 100);
                            }
                        } else {
                            handleProcessError(resp.data);
                        }
                    }, 'json').fail(function(xhr, status, error){
                        handleNetworkError(error);
                    });
                }

                function handleProcessError(errorMsg) {
                    $('#hp-progress-text').text('❌ Processing failed: ' + errorMsg);
                    $('#hp-import-log').append('❌ Processing error: ' + errorMsg + '\n');
                    importRunning = false;
                    enableButtons();
                }

                function handleNetworkError(error) {
                    retryCount++;
                    $('#hp-import-log').append('⚠️ Network error (attempt ' + retryCount + '/' + maxRetries + '): ' + error + '\n');

                    if (retryCount < maxRetries) {
                        // Save state and retry after delay
                        $('#hp-progress-text').text('⚠️ Network error, retrying in 5 seconds... (attempt ' + retryCount + '/' + maxRetries + ')');
                        saveImportState();
                        setTimeout(processBatch, 5000);
                    } else {
                        $('#hp-progress-text').text('❌ Network error after ' + maxRetries + ' attempts. Progress saved - refresh to resume.');
                        $('#hp-import-log').append('❌ Max retries reached. Your progress has been saved.\n');
                        $('#hp-import-log').append('💾 Refresh the page to see the resume option.\n');
                        importRunning = false;
                        saveImportState();
                        enableButtons();
                    }
                }

                function saveImportState() {
                    $.post(ajaxurl, {
                        action: 'hp_save_import_state',
                        cache_key: cacheKey,
                        offset: currentOffset,
                        total_rows: totalRows,
                        imported: totalImported,
                        updated: totalUpdated,
                        skipped: totalSkipped
                    });
                }

                function enableButtons() {
                    $('#hp-process-csv').prop('disabled', false);
                    $('#hp-download-csv').prop('disabled', false);
                }
            })(jQuery);
            </script>

            <hr />
            <h2><?php esc_html_e( 'Data Migration', 'wheelpros-importer' ); ?></h2>
            <p><?php esc_html_e( 'If you previously imported wheels but are now seeing "No Wheels Found", use this tool to convert taxonomy data to meta fields.', 'wheelpros-importer' ); ?></p>
            <?php $migrate_nonce = wp_create_nonce( 'hp_migrate_taxonomy_meta' ); ?>
            <button id="hp-run-migration" class="button button-secondary"><?php esc_html_e( 'Migrate Taxonomy Data to Meta Fields', 'wheelpros-importer' ); ?></button>
            <div id="hp-migration-progress-wrapper" style="margin-top:15px; display:none; max-width:600px;">
                <div style="background:#e5e5e5; height:20px; width:100%; border-radius:4px; overflow:hidden;">
                    <div id="hp-migration-progress-bar" style="background:#27ae60; width:0%; height:100%; transition:width 0.5s;"></div>
                </div>
                <div id="hp-migration-progress-text" style="margin-top:5px; font-size:14px;"></div>
            </div>
            <script>
            (function($){
                $('#hp-run-migration').on('click', function(e){
                    e.preventDefault();
                    var $btn = $(this);
                    $btn.prop('disabled', true);
                    $('#hp-migration-progress-wrapper').show();
                    $('#hp-migration-progress-bar').css('width', '0%');
                    $('#hp-migration-progress-text').text('<?php echo esc_js( __( 'Starting migration...', 'wheelpros-importer' ) ); ?>');

                    var offset = 0;
                    var batchSize = 25;

                    function migrateBatch(){
                        $.post(ajaxurl, {
                            action: 'hp_migrate_taxonomy_to_meta',
                            nonce: '<?php echo $migrate_nonce; ?>',
                            offset: offset,
                            batch_size: batchSize
                        }, function(resp){
                            if (resp.success) {
                                var progress = ((resp.data.offset / resp.data.total) * 100).toFixed(1);
                                $('#hp-migration-progress-bar').css('width', progress + '%');
                                $('#hp-migration-progress-text').text(progress + '% complete (' + resp.data.processed + ' processed in this batch)');

                                if (resp.data.complete) {
                                    $('#hp-migration-progress-text').text('100% complete - Migration finished!');
                                    $btn.prop('disabled', false);
                                    alert('Migration completed successfully! You can now refresh your wheel display page.');
                                } else {
                                    offset = resp.data.offset;
                                    setTimeout(migrateBatch, 500); // Small delay between batches
                                }
                            } else {
                                $('#hp-migration-progress-text').text('Error: ' + resp.data);
                                $btn.prop('disabled', false);
                                alert('Migration failed: ' + resp.data);
                            }
                        }).fail(function(){
                            $('#hp-migration-progress-text').text('Network error occurred');
                            $btn.prop('disabled', false);
                            alert('Network error occurred during migration');
                        });
                    }

                    migrateBatch();
                });
            })(jQuery);
            </script>

            <hr />
            <h2><?php esc_html_e( 'Manual File Upload', 'wheelpros-importer' ); ?></h2>
            <p><?php esc_html_e( 'If you need to import a different CSV file or the SFTP import is not available, you can upload a file here. Only CSV format is supported.', 'wheelpros-importer' ); ?></p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'hp_manual_import_action', 'hp_manual_import_nonce' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="hp_wheelpros_file"><?php esc_html_e( 'Upload CSV File', 'wheelpros-importer' ); ?></label>
                        </th>
                        <td>
                            <input type="file" id="hp_wheelpros_file" name="hp_wheelpros_file" accept=".csv" required />
                            <p class="description"><?php esc_html_e( 'Select a CSV file with the same format as your SFTP feed.', 'wheelpros-importer' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Import from File', 'wheelpros-importer' ), 'secondary', 'hp_manual_import' ); ?>
            </form>

            <hr />
            <h2><?php esc_html_e( 'Image Validation', 'wheelpros-importer' ); ?></h2>
            <p><?php esc_html_e( 'Scan all published wheels and automatically hide those with missing or invalid images by converting them to draft status. This helps clean up your catalog and ensures only wheels with valid images are displayed to customers.', 'wheelpros-importer' ); ?></p>
            <p class="description"><?php esc_html_e( 'Note: Images are automatically validated during import, so you only need to run this if you have older data or suspect issues.', 'wheelpros-importer' ); ?></p>
            <?php $validate_nonce = wp_create_nonce( 'hp_validate_images' ); ?>
            <button id="hp-validate-images" class="button button-secondary"><?php esc_html_e( 'Scan & Hide Wheels with Invalid Images', 'wheelpros-importer' ); ?></button>
            <button id="hp-cancel-validation" class="button" style="display:none;"><?php esc_html_e( 'Cancel', 'wheelpros-importer' ); ?></button>
            <div id="hp-validation-progress-wrapper" style="margin-top:15px; display:none; max-width:600px;">
                <div style="background:#e5e5e5; height:20px; width:100%; border-radius:4px; overflow:hidden;">
                    <div id="hp-validation-progress-bar" style="background:#f39c12; width:0%; height:100%; transition:width 0.5s;"></div>
                </div>
                <div id="hp-validation-progress-text" style="margin-top:5px; font-size:14px;"></div>
                <div style="margin-top:10px;">
                    <h4><?php echo esc_html( __( 'Validation Log', 'wheelpros-importer' ) ); ?></h4>
                    <pre id="hp-validation-log" style="max-height:300px; overflow:auto; background:#f8f8f8; padding:10px; border:1px solid #ccc; white-space:pre-wrap;"></pre>
                </div>
            </div>
            <script>
            (function($){
                var validationRunning = false;
                var validationCancelled = false;

                $('#hp-validate-images').on('click', function(e){
                    e.preventDefault();
                    var $btn = $(this);
                    var $cancelBtn = $('#hp-cancel-validation');

                    if (!confirm('This will scan all published wheels and hide those with missing or invalid images. Continue?')) {
                        return;
                    }

                    validationRunning = true;
                    validationCancelled = false;
                    $btn.prop('disabled', true);
                    $cancelBtn.show();
                    $('#hp-validation-progress-wrapper').show();
                    $('#hp-validation-progress-bar').css('width', '0%');
                    $('#hp-validation-progress-text').text('<?php echo esc_js( __( 'Starting image validation...', 'wheelpros-importer' ) ); ?>');
                    $('#hp-validation-log').text('🔍 Scanning for wheels with invalid images...\n');

                    var offset = 0;
                    var batchSize = 25;
                    var totalHidden = 0;
                    var totalScanned = 0;

                    function validateBatch(){
                        if (validationCancelled) {
                            $('#hp-validation-progress-text').text('⚠️ Validation cancelled by user');
                            $('#hp-validation-log').append('\n⚠️ Validation cancelled. Scanned: ' + totalScanned + ', Hidden: ' + totalHidden + '\n');
                            $btn.prop('disabled', false);
                            $cancelBtn.hide();
                            validationRunning = false;
                            return;
                        }

                        $.post(ajaxurl, {
                            action: 'hp_validate_wheel_images',
                            nonce: '<?php echo $validate_nonce; ?>',
                            offset: offset,
                            batch_size: batchSize
                        }, function(resp){
                            if (resp.success) {
                                totalScanned += resp.data.processed;
                                totalHidden += resp.data.hidden;

                                var progress = ((resp.data.offset / resp.data.total) * 100).toFixed(1);
                                $('#hp-validation-progress-bar').css('width', progress + '%');
                                $('#hp-validation-progress-text').text(progress + '% complete (' + totalScanned + '/' + resp.data.total + ' scanned, ' + totalHidden + ' hidden)');

                                // Add log messages
                                if (resp.data.log && Array.isArray(resp.data.log)) {
                                    resp.data.log.forEach(function(msg){
                                        $('#hp-validation-log').append(msg + '\n');
                                    });
                                    var logEl = document.getElementById('hp-validation-log');
                                    logEl.scrollTop = logEl.scrollHeight;
                                }

                                if (resp.data.complete) {
                                    $('#hp-validation-progress-text').text('✅ Validation complete! Scanned: ' + totalScanned + ', Hidden: ' + totalHidden);
                                    $('#hp-validation-log').append('\n🎉 Validation finished!\n');
                                    $('#hp-validation-log').append('📊 Total wheels scanned: ' + totalScanned + '\n');
                                    $('#hp-validation-log').append('❌ Wheels hidden (invalid images): ' + totalHidden + '\n');
                                    $btn.prop('disabled', false);
                                    $cancelBtn.hide();
                                    validationRunning = false;
                                    alert('Image validation completed! ' + totalHidden + ' wheels with invalid images have been hidden.');
                                } else {
                                    offset = resp.data.offset;
                                    setTimeout(validateBatch, 500); // Small delay to avoid overwhelming server
                                }
                            } else {
                                $('#hp-validation-progress-text').text('❌ Validation failed: ' + resp.data);
                                $('#hp-validation-log').append('❌ Error: ' + resp.data + '\n');
                                $btn.prop('disabled', false);
                                $cancelBtn.hide();
                                validationRunning = false;
                                alert('Validation failed: ' + resp.data);
                            }
                        }).fail(function(xhr, status, error){
                            $('#hp-validation-progress-text').text('❌ Network error occurred');
                            $('#hp-validation-log').append('❌ Network error: ' + error + '\n');
                            $btn.prop('disabled', false);
                            $cancelBtn.hide();
                            validationRunning = false;
                            alert('Network error occurred during validation');
                        });
                    }

                    validateBatch();
                });

                $('#hp-cancel-validation').on('click', function() {
                    if (validationRunning && confirm('Are you sure you want to cancel the validation process?')) {
                        validationCancelled = true;
                    }
                });
            })(jQuery);
            </script>

            <hr />
            <h2><?php esc_html_e( 'Maintenance Actions', 'wheelpros-importer' ); ?></h2>
            <p><?php esc_html_e( 'If you need to reset the wheel catalog, you can clear all imported wheels and associated taxonomy terms. This will permanently remove all Wheel entries and cannot be undone. Use with caution.', 'wheelpros-importer' ); ?></p>
            <form method="post">
                <?php wp_nonce_field( 'hp_clear_data_action', 'hp_clear_data_nonce' ); ?>
                <?php submit_button( __( 'Clear All Wheel Data', 'wheelpros-importer' ), 'delete', 'hp_clear_data' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Validate import settings and return any issues.
     *
     * @return array Array with 'valid' boolean and 'errors' array
     */
    protected function validate_import_settings() {
        $errors = array();
        $opts = get_option( 'hp_wheelpros_options' );

        if ( empty( $opts['host'] ) ) {
            $errors[] = __( 'SFTP Host is not configured', 'wheelpros-importer' );
        }
        if ( empty( $opts['username'] ) ) {
            $errors[] = __( 'SFTP Username is not configured', 'wheelpros-importer' );
        }
        if ( empty( $opts['password'] ) ) {
            $errors[] = __( 'SFTP Password is not configured', 'wheelpros-importer' );
        }
        if ( empty( $opts['path'] ) ) {
            $errors[] = __( 'Remote file path is not configured', 'wheelpros-importer' );
        }

        return array(
            'valid' => empty( $errors ),
            'errors' => $errors,
        );
    }

    /**
     * Process a manual file import.
     */
    protected function process_manual_import() {
        if ( empty( $_FILES['hp_wheelpros_file']['tmp_name'] ) ) {
            add_settings_error( 'hp_wheelpros_import', 'hp_wheelpros_import_error', __( 'No file uploaded.', 'wheelpros-importer' ), 'error' );
            return;
        }
        $file = $_FILES['hp_wheelpros_file'];
        $type = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        $options = get_option( 'hp_wheelpros_options' );
        $expected_type = isset( $options['type'] ) ? $options['type'] : 'csv';
        if ( $type !== $expected_type ) {
            add_settings_error( 'hp_wheelpros_import', 'hp_wheelpros_type_mismatch', __( 'Uploaded file type does not match the selected type in settings.', 'wheelpros-importer' ), 'error' );
            return;
        }
        $importer = new HP_WheelPros_Importer();
        $result   = $importer->import_from_file( $file['tmp_name'], $type );
        if ( is_wp_error( $result ) ) {
            add_settings_error( 'hp_wheelpros_import', 'hp_wheelpros_import_error', $result->get_error_message(), 'error' );
        } else {
            add_settings_error( 'hp_wheelpros_import', 'hp_wheelpros_import_success', __( 'Import completed successfully.', 'wheelpros-importer' ), 'updated' );
        }
    }

    /**
     * Clear all imported wheel data.
     *
     * This helper method deletes all posts of type hp_wheel as well as their
     * associated terms. It is intended to provide a manual reset mechanism
     * for administrators. Returns the number of posts deleted.
     *
     * @return int
     */
    protected function clear_wheel_data() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return 0;
        }
        // Fetch all wheel posts (including drafts) via WP_Query to avoid
        // memory exhaustion.
        $args = array(
            'post_type'      => 'hp_wheel',
            'posts_per_page' => -1,
            'post_status'    => array( 'publish', 'draft' ),
            'fields'         => 'ids',
        );
        $query = new WP_Query( $args );
        $deleted = 0;
        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post_id ) {
                // Delete permanently.
                wp_delete_post( $post_id, true );
                $deleted++;
            }
        }
        // Optionally, clean up orphaned terms in our taxonomies.
        // Remove orphaned terms for our custom taxonomies. Fetch IDs only to avoid
        // triggering warnings in wp_list_pluck() when objects are not provided.
        $taxonomies = array( 'hp_display_style', 'hp_brand', 'hp_finish' );
        foreach ( $taxonomies as $taxonomy ) {
            $term_ids = get_terms( array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'fields'     => 'ids',
            ) );
            if ( is_array( $term_ids ) ) {
                foreach ( $term_ids as $term_id ) {
                    if ( ! empty( $term_id ) ) {
                        wp_delete_term( $term_id, $taxonomy );
                    }
                }
            }
        }
        return $deleted;
    }

    /**
     * AJAX handler to download and cache the CSV file from SFTP server.
     *
     * This is Phase 1 of the two-phase import process. It downloads the CSV file
     * from the SFTP server and stores it locally as a transient, then returns
     * information about the cached file.
     */
    public function ajax_download_csv() {
        try {
            // Only administrators can perform imports
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( 'Permission denied' );
            }

            error_log( 'HP CSV Download: Starting CSV download process' );

            // Load required classes
            if ( ! class_exists( 'HP_WheelPros_Core' ) ) {
                require_once( plugin_dir_path( __FILE__ ) . '../core/class-hp-wheelpros-core.php' );
            }

            // Get SFTP settings
            $opts = get_option( 'hp_wheelpros_options' );
            $host     = isset( $opts['host'] ) ? $opts['host'] : '';
            $username = isset( $opts['username'] ) ? $opts['username'] : '';
            $enc_pass = isset( $opts['password'] ) ? $opts['password'] : '';
            $password = $enc_pass ? HP_WheelPros_Core::decrypt( $enc_pass ) : '';
            $path     = isset( $opts['path'] ) ? $opts['path'] : '';

            // Validate settings
            if ( empty( $host ) || empty( $username ) || empty( $password ) || empty( $path ) ) {
                $missing = array();
                if ( empty( $host ) ) $missing[] = 'host';
                if ( empty( $username ) ) $missing[] = 'username';
                if ( empty( $password ) ) $missing[] = 'password';
                if ( empty( $path ) ) $missing[] = 'path';
                wp_send_json_error( 'Missing SFTP settings: ' . implode( ', ', $missing ) );
            }

            // Download CSV file
            $csv_data = false;
            error_log( 'HP CSV Download: Attempting SFTP connection to ' . $host );

            // Try phpseclib3 first
            if ( class_exists( '\\phpseclib3\\Net\\SFTP' ) ) {
                try {
                    $sftp = new \phpseclib3\Net\SFTP( $host, isset( $opts['port'] ) ? (int) $opts['port'] : 22 );
                    if ( ! $sftp->login( $username, $password ) ) {
                        throw new \RuntimeException( 'SFTP login failed' );
                    }

                    error_log( 'HP CSV Download: SFTP login successful, downloading file: ' . $path );
                    $csv_data = $sftp->get( $path );

                    if ( $csv_data === false ) {
                        throw new \RuntimeException( 'Failed to download file from ' . $path );
                    }

                    error_log( 'HP CSV Download: File downloaded successfully, size: ' . strlen( $csv_data ) . ' bytes' );

                } catch ( \Exception $e ) {
                    error_log( 'HP CSV Download: phpseclib3 error: ' . $e->getMessage() );
                    $csv_data = false;
                }
            }

            // Try ssh2 as fallback
            if ( $csv_data === false && function_exists( 'ssh2_connect' ) ) {
                try {
                    $connection = ssh2_connect( $host, isset( $opts['port'] ) ? (int) $opts['port'] : 22 );
                    if ( ! $connection ) {
                        throw new \RuntimeException( 'SSH2 connection failed' );
                    }

                    if ( ! ssh2_auth_password( $connection, $username, $password ) ) {
                        throw new \RuntimeException( 'SSH2 authentication failed' );
                    }

                    $sftp_res = ssh2_sftp( $connection );
                    $remote_file = 'ssh2.sftp://' . intval( $sftp_res ) . $path;
                    $stream = @fopen( $remote_file, 'r' );

                    if ( ! $stream ) {
                        throw new \RuntimeException( 'Failed to open remote file via SSH2' );
                    }

                    $csv_data = stream_get_contents( $stream );
                    fclose( $stream );

                    if ( $csv_data === false ) {
                        throw new \RuntimeException( 'Failed to read remote file via SSH2' );
                    }

                    error_log( 'HP CSV Download: SSH2 download successful, size: ' . strlen( $csv_data ) . ' bytes' );

                } catch ( \Exception $e ) {
                    error_log( 'HP CSV Download: SSH2 error: ' . $e->getMessage() );
                    $csv_data = false;
                }
            }

            if ( $csv_data === false ) {
                wp_send_json_error( 'Failed to download CSV file from SFTP server' );
            }

            // Parse and validate CSV
            $lines = explode( "\n", trim( $csv_data ) );
            if ( empty( $lines ) || count( $lines ) < 2 ) {
                wp_send_json_error( 'Downloaded file appears to be empty or invalid' );
            }

            $header = str_getcsv( array_shift( $lines ) );
            $data_lines = array_filter( $lines, function( $line ) {
                return trim( $line ) !== '';
            });

            $total_rows = count( $data_lines );
            error_log( 'HP CSV Download: Parsed CSV - Header: ' . implode( ', ', $header ) . ' | Rows: ' . $total_rows );

            // Generate cache key and store data
            $cache_key = 'hp_csv_cache_' . md5( uniqid( '', true ) );
            $cache_data = array(
                'header' => $header,
                'lines' => $data_lines,
                'total_rows' => $total_rows,
                'downloaded_at' => current_time( 'mysql' ),
            );

            error_log( 'HP CSV Download: Generated cache key: ' . $cache_key );
            error_log( 'HP CSV Download: About to store cache data with ' . count( $cache_data['lines'] ) . ' lines' );

            // Check the size of the data before caching
            $serialized_size = strlen( serialize( $cache_data ) );
            error_log( 'HP CSV Download: Serialized cache data size: ' . $serialized_size . ' bytes (' . round( $serialized_size / 1024 / 1024, 2 ) . ' MB)' );

            // If data is too large, split it into chunks
            if ( $serialized_size > 1048576 ) { // 1MB limit
                error_log( 'HP CSV Download: Data too large, splitting into chunks' );

                // Split lines into chunks of 1000 rows each
                $chunk_size = 1000;
                $chunks = array_chunk( $cache_data['lines'], $chunk_size );
                $total_chunks = count( $chunks );

                // Store metadata
                $metadata = array(
                    'header' => $cache_data['header'],
                    'total_rows' => $cache_data['total_rows'],
                    'downloaded_at' => $cache_data['downloaded_at'],
                    'total_chunks' => $total_chunks,
                    'chunk_size' => $chunk_size,
                );

                set_transient( $cache_key . '_meta', $metadata, 6 * HOUR_IN_SECONDS );

                // Store each chunk separately
                for ( $i = 0; $i < $total_chunks; $i++ ) {
                    $chunk_key = $cache_key . '_chunk_' . $i;
                    set_transient( $chunk_key, $chunks[ $i ], 6 * HOUR_IN_SECONDS );
                    error_log( 'HP CSV Download: Stored chunk ' . $i . ' with ' . count( $chunks[ $i ] ) . ' lines' );
                }

                error_log( 'HP CSV Download: Data split into ' . $total_chunks . ' chunks' );
            } else {
                // Store normally if small enough
                set_transient( $cache_key, $cache_data, 6 * HOUR_IN_SECONDS );
                error_log( 'HP CSV Download: Data small enough, stored as single transient' );
            }

            // Verify the cache was set by trying to retrieve it immediately
            $verification = get_transient( $cache_key );
            if ( ! $verification ) {
                // Check if chunked format was used
                $metadata_verification = get_transient( $cache_key . '_meta' );
                if ( $metadata_verification ) {
                    error_log( 'HP CSV Download: Cache verification successful (chunked format)' );
                } else {
                    error_log( 'HP CSV Download: WARNING - Cache verification failed completely' );
                }
            } else {
                error_log( 'HP CSV Download: Cache verification successful (single transient)' );
            }

            wp_send_json_success( array(
                'cache_key' => $cache_key,
                'total_rows' => $total_rows,
                'file_size' => strlen( $csv_data ),
                'header' => $header,
                'message' => 'CSV file downloaded and cached successfully'
            ) );

        } catch ( Exception $e ) {
            error_log( 'HP CSV Download: Exception: ' . $e->getMessage() );
            wp_send_json_error( 'Download failed: ' . $e->getMessage() );
        } catch ( Error $e ) {
            error_log( 'HP CSV Download: Fatal error: ' . $e->getMessage() );
            wp_send_json_error( 'Fatal error: ' . $e->getMessage() );
        }
    }

    /**
     * AJAX handler to process cached CSV data in small batches.
     *
     * This is Phase 2 of the two-phase import process. It processes the cached
     * CSV data in small batches to avoid memory and timeout issues.
     */
    public function ajax_process_cached_csv() {
        try {
            // Only administrators can perform imports
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( 'Permission denied' );
            }

            // Get parameters
            $cache_key = isset( $_REQUEST['cache_key'] ) ? sanitize_key( wp_unslash( $_REQUEST['cache_key'] ) ) : '';
            $offset = isset( $_REQUEST['offset'] ) ? absint( $_REQUEST['offset'] ) : 0;
            $skip_image_validation = isset( $_REQUEST['skip_image_validation'] ) && $_REQUEST['skip_image_validation'] == '1';
            $batch_size = 50; // Larger batch size for better performance (was 25)

            // Set memory and time limits for this request
            ini_set( 'memory_limit', '512M' );
            set_time_limit( 120 );

            error_log( 'HP CSV Process: Raw request data: ' . print_r( $_REQUEST, true ) );

            if ( empty( $cache_key ) ) {
                error_log( 'HP CSV Process: Missing cache key in request' );
                wp_send_json_error( 'Missing cache key' );
            }

            error_log( 'HP CSV Process: Looking for cache key: ' . $cache_key );
            error_log( 'HP CSV Process: Offset: ' . $offset );

            // Debug: List all transients starting with hp_csv_cache
            global $wpdb;
            $transients = $wpdb->get_results(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '%hp_csv_cache%'",
                ARRAY_A
            );
            error_log( 'HP CSV Process: Found ' . count( $transients ) . ' CSV cache transients in database' );
            foreach ( $transients as $transient ) {
                error_log( 'HP CSV Process: Transient found: ' . $transient['option_name'] );
            }

            // Get cached data - handle both chunked and single transients
            $cache_data = get_transient( $cache_key );
            $is_chunked = false;
            $metadata = null;

            if ( ! $cache_data ) {
                // Try chunked format
                $metadata = get_transient( $cache_key . '_meta' );

                if ( $metadata && isset( $metadata['total_chunks'] ) ) {
                    error_log( 'HP CSV Process: Found chunked cache with ' . $metadata['total_chunks'] . ' chunks' );
                    $is_chunked = true;

                    // For chunked data, we'll load only the needed chunk for this batch
                    // instead of reconstructing the entire dataset
                    $cache_data = array(
                        'header' => $metadata['header'],
                        'total_rows' => $metadata['total_rows'],
                        'downloaded_at' => $metadata['downloaded_at'],
                        'is_chunked' => true,
                        'chunk_size' => $metadata['chunk_size'],
                        'total_chunks' => $metadata['total_chunks'],
                    );

                    error_log( 'HP CSV Process: Using chunked processing mode' );
                } else {
                    error_log( 'HP CSV Process: No cache data or metadata found for key: ' . $cache_key );
                    wp_send_json_error( 'Cache expired or not found. Please re-download the CSV file.' );
                }
            } else {
                error_log( 'HP CSV Process: Found single transient cache data' );
            }

            if ( ! is_array( $cache_data ) ) {
                error_log( 'HP CSV Process: Cache data is not array: ' . gettype( $cache_data ) );
                wp_send_json_error( 'Cache data is invalid. Please re-download the CSV file.' );
            }

            error_log( 'HP CSV Process: Cache data retrieved successfully, rows: ' . ( isset( $cache_data['total_rows'] ) ? $cache_data['total_rows'] : 'unknown' ) );

            $header = $cache_data['header'];
            $total_rows = $cache_data['total_rows'];
            $is_chunked = isset( $cache_data['is_chunked'] ) ? $cache_data['is_chunked'] : false;

            // Get the actual data lines for this batch
            if ( $is_chunked ) {
                // Load only the chunks we need for this batch
                $chunk_size = $cache_data['chunk_size'];
                $start_chunk = floor( $offset / $chunk_size );
                $end_chunk = floor( ( $offset + $batch_size - 1 ) / $chunk_size );

                error_log( 'HP CSV Process: Loading chunks ' . $start_chunk . ' to ' . $end_chunk . ' for offset ' . $offset );

                $lines = array();
                for ( $chunk_idx = $start_chunk; $chunk_idx <= $end_chunk; $chunk_idx++ ) {
                    $chunk_key = $cache_key . '_chunk_' . $chunk_idx;
                    $chunk_data = get_transient( $chunk_key );

                    if ( ! $chunk_data ) {
                        error_log( 'HP CSV Process: Missing chunk ' . $chunk_idx . ' for key: ' . $chunk_key );
                        wp_send_json_error( 'Cache chunk ' . $chunk_idx . ' expired. Please re-download the CSV file.' );
                    }

                    $lines = array_merge( $lines, $chunk_data );
                }

                error_log( 'HP CSV Process: Loaded ' . count( $lines ) . ' lines from chunks ' . $start_chunk . '-' . $end_chunk );

                // Adjust offset to be relative to the loaded chunks
                $batch_offset = $offset - ( $start_chunk * $chunk_size );

            } else {
                // Single transient format
                $lines = $cache_data['lines'];
                $batch_offset = $offset;
            }

            // Process batch
            $end = min( $batch_offset + $batch_size, count( $lines ) );
            if ( $is_chunked ) {
                // For chunked data, ensure we don't exceed total rows
                $actual_end = min( $offset + $batch_size, $total_rows );
            } else {
                $actual_end = min( $offset + $batch_size, $total_rows );
            }

            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $log_messages = array();

            // Log if image validation is being skipped
            if ( $offset === 0 && $skip_image_validation ) {
                $log_messages[] = '⚡ Image validation skipped for faster import';
            }

            // Build existing part number map for this batch only (more memory efficient)
            $existing_map = array();
            if ( $offset === 0 ) {
                // Only build the map on the first batch, then cache it
                $existing_cache_key = $cache_key . '_existing';
                $existing_map = get_transient( $existing_cache_key );

                if ( ! $existing_map ) {
                    // Use direct database query instead of WP_Query to save memory
                    global $wpdb;

                    $sql = "SELECT p.ID, pm.meta_value as part_number
                            FROM {$wpdb->posts} p
                            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                            WHERE p.post_type = 'hp_wheel'
                            AND p.post_status IN ('publish', 'draft')
                            AND pm.meta_key = 'hp_part_number'
                            AND pm.meta_value != ''";

                    $results = $wpdb->get_results( $sql, ARRAY_A );

                    $existing_map = array();
                    if ( $results ) {
                        foreach ( $results as $row ) {
                            $existing_map[ $row['part_number'] ] = (int) $row['ID'];
                        }
                    }

                    error_log( 'HP CSV Process: Built existing map using direct DB query with ' . count( $existing_map ) . ' entries' );

                    // Cache for the duration of this import
                    set_transient( $existing_cache_key, $existing_map, 2 * HOUR_IN_SECONDS );
                    $log_messages[] = '🔍 Built existing part number map (' . count( $existing_map ) . ' existing parts)';
                }
            } else {
                // Get cached existing map
                $existing_cache_key = $cache_key . '_existing';
                $existing_map = get_transient( $existing_cache_key );
                if ( ! $existing_map ) {
                    $existing_map = array();
                }
            }

            // Process each row in this batch
            for ( $i = $batch_offset; $i < $end && ( $is_chunked ? ( $offset + ( $i - $batch_offset ) ) < $total_rows : true ); $i++ ) {
                if ( ! isset( $lines[ $i ] ) ) {
                    continue;
                }

                $line = trim( $lines[ $i ] );
                if ( empty( $line ) ) {
                    continue;
                }

                $row_data = str_getcsv( $line );
                if ( count( $row_data ) !== count( $header ) ) {
                    $actual_row_num = $is_chunked ? ( $offset + ( $i - $batch_offset ) + 1 ) : ( $offset + $i + 1 );
                    $log_messages[] = '⚠️ Row ' . $actual_row_num . ' skipped (column count mismatch)';
                    continue;
                }

                // Map row data to header
                $item = array();
                foreach ( $header as $idx => $col ) {
                    $item[ $col ] = isset( $row_data[ $idx ] ) ? $row_data[ $idx ] : '';
                }

                $part_number = isset( $item['PartNumber'] ) ? trim( $item['PartNumber'] ) : '';
                if ( empty( $part_number ) ) {
                    $actual_row_num = $is_chunked ? ( $offset + ( $i - $batch_offset ) + 1 ) : ( $offset + $i + 1 );
                    $log_messages[] = '⚠️ Row ' . $actual_row_num . ' skipped (missing PartNumber)';
                    continue;
                }

                // Check if brand is hidden - skip hidden brands during import
                $brand = isset( $item['Brand'] ) ? trim( $item['Brand'] ) : '';
                if ( ! empty( $brand ) && class_exists( 'HP_WheelPros_Brand_Manager' ) ) {
                    if ( ! HP_WheelPros_Brand_Manager::should_import_brand( $brand ) ) {
                        $skipped++;
                        continue; // Skip hidden brands
                    }
                }

                // Validate image URL if not skipping validation
                if ( ! $skip_image_validation ) {
                    $image_url = isset( $item['ImageURL'] ) ? trim( $item['ImageURL'] ) : '';
                    if ( empty( $image_url ) || ! $this->validate_image_url_quick( $image_url ) ) {
                        $skipped++;
                        continue; // Skip items with invalid/missing images
                    }
                }

                // Check if exists
                $post_id = isset( $existing_map[ $part_number ] ) ? $existing_map[ $part_number ] : 0;

                if ( $post_id ) {
                    // Update existing
                    wp_update_post( array(
                        'ID' => $post_id,
                        'post_title' => $part_number
                    ) );
                    $updated++;
                } else {
                    // Create new
                    $post_id = wp_insert_post( array(
                        'post_title' => $part_number,
                        'post_type' => 'hp_wheel',
                        'post_status' => 'publish',
                    ) );

                    if ( is_wp_error( $post_id ) ) {
                        $log_messages[] = '❌ Failed to create post for ' . $part_number;
                        continue;
                    }

                    $imported++;
                    $existing_map[ $part_number ] = $post_id;
                }

                // Update meta fields
                update_post_meta( $post_id, 'hp_part_number', $part_number );

                $meta_fields = array(
                    'PartDescription' => 'part_description',
                    'DisplayStyleNo' => 'display_style_no',
                    'Brand' => 'brand',
                    'Finish' => 'finish',
                    'Size' => 'size',
                    'BoltPattern' => 'bolt_pattern',
                    'Offset' => 'offset',
                    'CenterBore' => 'center_bore',
                    'LoadRating' => 'load_rating',
                    'ShippingWeight' => 'shipping_weight',
                    'ImageURL' => 'image_url',
                    'InvOrderType' => 'inventory_order_type',
                    'Style' => 'style',
                    'TotalQOH' => 'total_qoh',
                    'MSRP_USD' => 'msrp_usd',
                    'MAP_USD' => 'map_usd',
                    'RunDate' => 'run_date',
                );

                foreach ( $meta_fields as $csv_key => $meta_key ) {
                    if ( isset( $item[ $csv_key ] ) ) {
                        $value = $item[ $csv_key ];
                        if ( $meta_key === 'image_url' ) {
                            $value = esc_url_raw( $value );
                        } else {
                            $value = sanitize_text_field( $value );
                        }
                        update_post_meta( $post_id, 'hp_' . $meta_key, $value );
                    }
                }

                // Update taxonomies
                if ( ! empty( $item['DisplayStyleNo'] ) ) {
                    wp_set_object_terms( $post_id, sanitize_text_field( $item['DisplayStyleNo'] ), 'hp_display_style', false );
                }
                if ( ! empty( $item['Brand'] ) ) {
                    wp_set_object_terms( $post_id, sanitize_text_field( $item['Brand'] ), 'hp_brand', false );
                }
                if ( ! empty( $item['Finish'] ) ) {
                    wp_set_object_terms( $post_id, sanitize_text_field( $item['Finish'] ), 'hp_finish', false );
                }

                // Log progress every 10 items
                $actual_row_num = $is_chunked ? ( $offset + ( $i - $batch_offset ) + 1 ) : ( $offset + $i + 1 );
                if ( $actual_row_num % 10 === 0 ) {
                    $log_messages[] = '🔹 Processed ' . $part_number . ' (' . $actual_row_num . '/' . $total_rows . ')';
                }
            }

            // Update cached existing map if we added new items
            if ( $imported > 0 ) {
                set_transient( $cache_key . '_existing', $existing_map, 2 * HOUR_IN_SECONDS );
            }

            // Clean up memory after processing
            if ( isset( $lines ) ) {
                unset( $lines );
            }
            wp_cache_flush();

            error_log( 'HP CSV Process: Memory usage after batch: ' . memory_get_usage( true ) . ' bytes (' . round( memory_get_usage( true ) / 1024 / 1024, 2 ) . ' MB)' );

            // Calculate progress
            $actual_end = $is_chunked ? min( $offset + $batch_size, $total_rows ) : min( $offset + $batch_size, $total_rows );
            $progress = $total_rows > 0 ? floor( ( $actual_end / $total_rows ) * 100 ) : 0;
            $done = ( $actual_end >= $total_rows );

            if ( $done ) {
                // Clean up cache - handle both single and chunked formats
                delete_transient( $cache_key );
                delete_transient( $cache_key . '_existing' );
                delete_transient( 'hp_import_resume_state' ); // Clear resume state on success

                // Clean up chunked cache if it exists
                $metadata = get_transient( $cache_key . '_meta' );
                if ( $metadata && isset( $metadata['total_chunks'] ) ) {
                    for ( $i = 0; $i < $metadata['total_chunks']; $i++ ) {
                        delete_transient( $cache_key . '_chunk_' . $i );
                    }
                    delete_transient( $cache_key . '_meta' );
                    error_log( 'HP CSV Process: Cleaned up chunked cache with ' . $metadata['total_chunks'] . ' chunks' );
                } else {
                    error_log( 'HP CSV Process: Cleaned up single cache transient' );
                }

                $completion_msg = '✅ Import completed! Imported: ' . $imported . ', Updated: ' . $updated;
                if ( $skipped > 0 ) {
                    $completion_msg .= ', Skipped: ' . $skipped;
                }
                $log_messages[] = $completion_msg;
                error_log( 'HP CSV Process: Import completed - Imported: ' . $imported . ', Updated: ' . $updated . ', Skipped: ' . $skipped );
            } else {
                // Save progress state for resume capability (every batch)
                $state = array(
                    'cache_key'  => $cache_key,
                    'offset'     => $actual_end,
                    'total_rows' => $total_rows,
                    'saved_at'   => current_time( 'mysql' ),
                );
                set_transient( 'hp_import_resume_state', $state, 6 * HOUR_IN_SECONDS );
            }

            wp_send_json_success( array(
                'progress' => $progress,
                'offset' => $actual_end,
                'done' => $done,
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'log' => $log_messages,
                'cache_key' => $cache_key,
            ) );

        } catch ( Exception $e ) {
            error_log( 'HP CSV Process: Exception: ' . $e->getMessage() );
            wp_send_json_error( 'Processing failed: ' . $e->getMessage() );
        } catch ( Error $e ) {
            error_log( 'HP CSV Process: Fatal error: ' . $e->getMessage() );
            wp_send_json_error( 'Fatal error: ' . $e->getMessage() );
        }
    }

    /**
     * AJAX handler to migrate taxonomy data to meta fields.
     * This fixes the issue where existing imports only have taxonomy data
     * but the shortcode expects meta fields.
     */
    public function ajax_migrate_taxonomy_to_meta() {
        // Verify nonce and capabilities
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'hp_migrate_taxonomy_meta' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        // Increase memory limit and execution time for this operation
        ini_set( 'memory_limit', '512M' );
        set_time_limit( 300 );

        // Get batch parameters - using smaller batch size to prevent memory issues
        $batch_size = absint( $_POST['batch_size'] ?? 25 );
        $offset = absint( $_POST['offset'] ?? 0 );

        // Use a more memory-efficient query - only get IDs that need migration
        global $wpdb;

        // Get wheels that don't have display style meta (most memory efficient way)
        $sql = "SELECT p.ID
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'hp_display_style_no'
                WHERE p.post_type = 'hp_wheel'
                AND p.post_status = 'publish'
                AND pm.meta_value IS NULL
                LIMIT %d OFFSET %d";

        $wheel_ids = $wpdb->get_col( $wpdb->prepare( $sql, $batch_size, $offset ) );
        $processed = 0;

        foreach ( $wheel_ids as $wheel_id ) {
            // Get taxonomy terms and convert to meta - one by one to save memory
            $display_style_terms = wp_get_object_terms( $wheel_id, 'hp_display_style', array( 'fields' => 'names' ) );
            if ( ! empty( $display_style_terms ) && ! is_wp_error( $display_style_terms ) ) {
                update_post_meta( $wheel_id, 'hp_display_style_no', sanitize_text_field( $display_style_terms[0] ) );
            }

            $brand_terms = wp_get_object_terms( $wheel_id, 'hp_brand', array( 'fields' => 'names' ) );
            if ( ! empty( $brand_terms ) && ! is_wp_error( $brand_terms ) ) {
                update_post_meta( $wheel_id, 'hp_brand', sanitize_text_field( $brand_terms[0] ) );
            }

            $finish_terms = wp_get_object_terms( $wheel_id, 'hp_finish', array( 'fields' => 'names' ) );
            if ( ! empty( $finish_terms ) && ! is_wp_error( $finish_terms ) ) {
                update_post_meta( $wheel_id, 'hp_finish', sanitize_text_field( $finish_terms[0] ) );
            }

            $processed++;

            // Clear any object cache to prevent memory buildup
            wp_cache_flush();
        }

        // Get total count of wheels that still need migration (memory efficient)
        $total_remaining_sql = "SELECT COUNT(p.ID)
                               FROM {$wpdb->posts} p
                               LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'hp_display_style_no'
                               WHERE p.post_type = 'hp_wheel'
                               AND p.post_status = 'publish'
                               AND pm.meta_value IS NULL";

        $total_remaining = $wpdb->get_var( $total_remaining_sql );

        // Get total wheel count for progress calculation
        $total_wheels = wp_count_posts( 'hp_wheel' )->publish;

        wp_send_json_success( array(
            'processed' => $processed,
            'total'     => $total_wheels,
            'offset'    => $offset + $batch_size,
            'remaining' => $total_remaining,
            'complete'  => $total_remaining <= 0,
        ) );
    }

    /**
     * Robust image URL validation that returns validity and reason.
     * Used by the image validation scanner for detailed logging.
     *
     * @param string $url Image URL to validate.
     * @return array ['valid' => bool, 'reason' => string]
     */
    protected function validate_image_url_robust( $url ) {
        // Quick validation
        if ( empty( $url ) ) {
            return array( 'valid' => false, 'reason' => 'No image URL' );
        }

        // Basic URL format check
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return array( 'valid' => false, 'reason' => 'Invalid URL format' );
        }

        // Skip known placeholder patterns
        $skip_patterns = array( 'placeholder', 'no-image', 'noimage', 'default.', 'blank.', 'example.com', 'localhost' );
        foreach ( $skip_patterns as $pattern ) {
            if ( stripos( $url, $pattern ) !== false ) {
                return array( 'valid' => false, 'reason' => 'Placeholder URL pattern detected' );
            }
        }

        // Check cache first
        $cache_key = 'hp_img_robust_' . md5( $url );
        $cached = get_transient( $cache_key );
        if ( $cached !== false && is_array( $cached ) ) {
            return $cached;
        }

        $result = array( 'valid' => false, 'reason' => 'Unknown error' );

        // Try HEAD request first (faster)
        $response = wp_remote_head( $url, array(
            'timeout'     => 10,
            'redirection' => 5,
            'user-agent'  => 'Mozilla/5.0 (compatible; WheelPros-Importer/1.0)',
        ) );

        if ( ! is_wp_error( $response ) ) {
            $status_code = wp_remote_retrieve_response_code( $response );
            $content_type = wp_remote_retrieve_header( $response, 'content-type' );

            // Accept 200, 301, 302
            if ( in_array( $status_code, array( 200, 301, 302, 304 ) ) ) {
                // Be lenient with content-type
                if ( empty( $content_type ) || strpos( $content_type, 'image/' ) === 0 || strpos( $content_type, 'application/octet-stream' ) !== false ) {
                    $result = array( 'valid' => true, 'reason' => 'HEAD request OK' );
                } else {
                    // Wrong content-type, but try GET anyway
                    $result = array( 'valid' => false, 'reason' => 'Content-Type: ' . $content_type );
                }
            } else {
                $result = array( 'valid' => false, 'reason' => 'HTTP ' . $status_code );
            }
        } else {
            $result = array( 'valid' => false, 'reason' => 'HEAD failed: ' . $response->get_error_message() );
        }

        // If HEAD failed or returned questionable result, try GET with range
        if ( ! $result['valid'] ) {
            $response = wp_remote_get( $url, array(
                'timeout'     => 10,
                'redirection' => 5,
                'user-agent'  => 'Mozilla/5.0 (compatible; WheelPros-Importer/1.0)',
                'headers'     => array( 'Range' => 'bytes=0-1023' ),
            ) );

            if ( ! is_wp_error( $response ) ) {
                $status_code = wp_remote_retrieve_response_code( $response );
                $body = wp_remote_retrieve_body( $response );

                if ( in_array( $status_code, array( 200, 206 ) ) && ! empty( $body ) ) {
                    // Check for image magic bytes
                    $magic_bytes = array(
                        "\xFF\xD8\xFF"         => 'JPEG',
                        "\x89PNG"              => 'PNG',
                        "GIF87a"               => 'GIF',
                        "GIF89a"               => 'GIF',
                        "RIFF"                 => 'WebP',
                        "<svg"                 => 'SVG',
                    );

                    foreach ( $magic_bytes as $magic => $type ) {
                        if ( substr( $body, 0, strlen( $magic ) ) === $magic ) {
                            $result = array( 'valid' => true, 'reason' => 'Valid ' . $type . ' image' );
                            break;
                        }
                    }

                    // If no magic bytes matched but we got content, it might still be valid
                    if ( ! $result['valid'] && strlen( $body ) > 100 ) {
                        // Check if it looks like binary data (not HTML/text)
                        $text_chars = count_chars( substr( $body, 0, 100 ), 1 );
                        $printable = 0;
                        foreach ( $text_chars as $char => $count ) {
                            if ( ( $char >= 32 && $char <= 126 ) || in_array( $char, array( 9, 10, 13 ) ) ) {
                                $printable += $count;
                            }
                        }
                        // If less than 70% printable, likely binary/image
                        if ( $printable < 70 ) {
                            $result = array( 'valid' => true, 'reason' => 'Binary content detected' );
                        } else {
                            $result = array( 'valid' => false, 'reason' => 'Not an image (text content)' );
                        }
                    }
                } elseif ( $status_code === 404 ) {
                    $result = array( 'valid' => false, 'reason' => 'Image not found (404)' );
                } elseif ( $status_code === 403 ) {
                    // Some servers block range requests, try full GET
                    $result = array( 'valid' => false, 'reason' => 'Access denied (403)' );
                }
            }
        }

        // Cache result for 4 hours
        set_transient( $cache_key, $result, 4 * HOUR_IN_SECONDS );

        return $result;
    }

    /**
     * Quick image URL validation with caching.
     * Uses HEAD request first, falls back to GET if HEAD fails.
     * More lenient and robust than the importer's validate_image_url().
     *
     * @param string $url Image URL to validate.
     * @return bool True if image appears valid.
     */
    protected function validate_image_url_quick( $url ) {
        // Quick validation
        if ( empty( $url ) ) {
            return false;
        }

        // Basic URL format check
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        // Skip known placeholder patterns
        $skip_patterns = array( 'placeholder', 'no-image', 'noimage', 'default.', 'blank.' );
        foreach ( $skip_patterns as $pattern ) {
            if ( stripos( $url, $pattern ) !== false ) {
                return false;
            }
        }

        // Check cache first
        $cache_key = 'hp_img_' . md5( $url );
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached === 'valid';
        }

        // Try HEAD request first (faster)
        $response = wp_remote_head( $url, array(
            'timeout'     => 8,
            'redirection' => 5,
            'user-agent'  => 'Mozilla/5.0 (compatible; WheelPros-Importer/1.0)',
        ) );

        $is_valid = false;

        if ( ! is_wp_error( $response ) ) {
            $status_code = wp_remote_retrieve_response_code( $response );
            $content_type = wp_remote_retrieve_header( $response, 'content-type' );

            // Accept 200, 301, 302 (redirects often work for images)
            if ( in_array( $status_code, array( 200, 301, 302 ) ) ) {
                // Be lenient with content-type - some servers return wrong types
                if ( empty( $content_type ) || strpos( $content_type, 'image/' ) === 0 || strpos( $content_type, 'application/octet-stream' ) !== false ) {
                    $is_valid = true;
                }
            }
        }

        // If HEAD failed or returned error, try GET with range (downloads only first bytes)
        if ( ! $is_valid ) {
            $response = wp_remote_get( $url, array(
                'timeout'     => 8,
                'redirection' => 5,
                'user-agent'  => 'Mozilla/5.0 (compatible; WheelPros-Importer/1.0)',
                'headers'     => array( 'Range' => 'bytes=0-1023' ), // Only get first 1KB
            ) );

            if ( ! is_wp_error( $response ) ) {
                $status_code = wp_remote_retrieve_response_code( $response );
                $body = wp_remote_retrieve_body( $response );

                // Accept 200, 206 (partial content)
                if ( in_array( $status_code, array( 200, 206 ) ) && ! empty( $body ) ) {
                    // Check for image magic bytes
                    $magic_bytes = array(
                        "\xFF\xD8\xFF"         => 'jpeg',
                        "\x89PNG"              => 'png',
                        "GIF87a"               => 'gif',
                        "GIF89a"               => 'gif',
                        "RIFF"                 => 'webp', // WebP starts with RIFF
                    );

                    foreach ( $magic_bytes as $magic => $type ) {
                        if ( substr( $body, 0, strlen( $magic ) ) === $magic ) {
                            $is_valid = true;
                            break;
                        }
                    }
                }
            }
        }

        // Cache result for 2 hours
        set_transient( $cache_key, $is_valid ? 'valid' : 'invalid', 2 * HOUR_IN_SECONDS );

        return $is_valid;
    }

    /**
     * AJAX handler to save import state for resume functionality.
     */
    public function ajax_save_import_state() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $state = array(
            'cache_key'  => isset( $_POST['cache_key'] ) ? sanitize_key( $_POST['cache_key'] ) : '',
            'offset'     => isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0,
            'total_rows' => isset( $_POST['total_rows'] ) ? absint( $_POST['total_rows'] ) : 0,
            'imported'   => isset( $_POST['imported'] ) ? absint( $_POST['imported'] ) : 0,
            'updated'    => isset( $_POST['updated'] ) ? absint( $_POST['updated'] ) : 0,
            'skipped'    => isset( $_POST['skipped'] ) ? absint( $_POST['skipped'] ) : 0,
            'saved_at'   => current_time( 'mysql' ),
        );

        // Save state for 6 hours (same as cache expiry)
        set_transient( 'hp_import_resume_state', $state, 6 * HOUR_IN_SECONDS );

        wp_send_json_success( array( 'message' => 'Import state saved' ) );
    }

    /**
     * AJAX handler to clear import state (discard resume).
     */
    public function ajax_clear_import_state() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        // Get current state to clean up cache too
        $state = get_transient( 'hp_import_resume_state' );
        if ( $state && isset( $state['cache_key'] ) ) {
            $cache_key = $state['cache_key'];

            // Delete all related transients
            delete_transient( $cache_key );
            delete_transient( $cache_key . '_existing' );
            delete_transient( $cache_key . '_meta' );

            // Clean up chunks if they exist
            $metadata = get_transient( $cache_key . '_meta' );
            if ( $metadata && isset( $metadata['total_chunks'] ) ) {
                for ( $i = 0; $i < $metadata['total_chunks']; $i++ ) {
                    delete_transient( $cache_key . '_chunk_' . $i );
                }
            }
        }

        delete_transient( 'hp_import_resume_state' );

        wp_send_json_success( array( 'message' => 'Import state cleared' ) );
    }

    /**
     * AJAX handler to mark an image URL as broken.
     */
    public function ajax_mark_image_broken() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'hp_mark_broken_image' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }

        $image_url = sanitize_text_field( $_POST['image_url'] ?? '' );

        if ( empty( $image_url ) ) {
            wp_send_json_error( 'No image URL provided' );
        }

        // Cache this image as broken for 1 hour
        $cache_key = 'hp_broken_image_' . md5( $image_url );
        wp_cache_set( $cache_key, 'broken', '', 3600 );

        // Also add to a list of broken images for reporting
        $broken_images = wp_cache_get( 'hp_broken_images_list' );
        if ( $broken_images === false ) {
            $broken_images = array();
        }

        if ( ! in_array( $image_url, $broken_images ) ) {
            $broken_images[] = $image_url;
            wp_cache_set( 'hp_broken_images_list', $broken_images, '', 3600 );
        }

        wp_send_json_success( array(
            'message' => 'Image marked as broken',
            'image_url' => $image_url
        ) );
    }

    /**
     * AJAX handler to validate wheel images in batches.
     * Checks if images are valid and marks wheels with invalid images as draft.
     * Uses robust validation with HEAD + GET fallback and magic byte detection.
     */
    public function ajax_validate_wheel_images() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'hp_validate_images' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
        $batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 25;

        // Get total count using efficient query
        global $wpdb;
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'hp_wheel' AND post_status = 'publish'" );

        // Get batch of posts
        $batch_args = array(
            'post_type'      => 'hp_wheel',
            'post_status'    => 'publish',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        );
        $posts = get_posts( $batch_args );

        $hidden = 0;
        $log = array();

        foreach ( $posts as $post ) {
            $image_url = get_post_meta( $post->ID, 'hp_image_url', true );
            $part_number = get_post_meta( $post->ID, 'hp_part_number', true );

            // Use the robust validation method
            $is_valid = $this->validate_image_url_robust( $image_url );
            $is_invalid = ! $is_valid['valid'];
            $reason = $is_valid['reason'];

            if ( $is_invalid ) {
                // Convert to draft
                wp_update_post( array(
                    'ID'          => $post->ID,
                    'post_status' => 'draft',
                ) );
                $hidden++;

                // Add to broken images cache
                $broken_images = wp_cache_get( 'hp_broken_images_list' );
                if ( $broken_images === false ) {
                    $broken_images = array();
                }
                if ( ! in_array( $image_url, $broken_images ) ) {
                    $broken_images[] = $image_url;
                    wp_cache_set( 'hp_broken_images_list', $broken_images, '', 3600 );
                }

                $log[] = sprintf( '❌ Hidden: %s - %s', $part_number, $reason );
            }
        }

        $new_offset = $offset + $batch_size;
        $complete = $new_offset >= $total;

        wp_send_json_success( array(
            'offset'    => $new_offset,
            'total'     => $total,
            'processed' => count( $posts ),
            'hidden'    => $hidden,
            'complete'  => $complete,
            'log'       => $log,
        ) );
    }
}
