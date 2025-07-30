<?php
/**
 * Importer class for WheelPros data.
 *
 * Responsible for parsing CSV/JSON files, creating/updating/deactivating
 * Wheel posts, assigning taxonomy terms, and recording import logs. The
 * importer can be triggered manually via the admin interface or automatically
 * via a scheduled event. SFTP fetching is handled here using phpseclib if
 * available; otherwise a meaningful error is logged. Large datasets are
 * processed in batches to mitigate memory and timeout issues.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HP_WheelPros_Importer {

    /**
     * Maximum number of records processed per batch.
     */
    const BATCH_SIZE = 100;

    /**
     * Progress key used for tracking import progress in transients.
     * @var string
     */
    protected $progress_key = '';

    /**
     * Set the progress key for this importer instance.
     *
     * @param string $key Unique transient key.
     */
    public function set_progress_key( $key ) {
        $this->progress_key = $key;
    }

    /**
     * Initialise the progress transient with total rows and default counts.
     *
     * @param int $total_rows Total number of rows expected.
     * @return void
     */
    public function init_progress( $total_rows ) {
        if ( empty( $this->progress_key ) ) {
            return;
        }
        $data = array(
            'status'        => 'running',
            'total_rows'    => intval( $total_rows ),
            'processed_rows'=> 0,
            'imported'      => 0,
            'updated'       => 0,
            'deleted'       => 0,
            // Capture human‑readable messages for display in the admin UI.
            'messages'      => array( __( 'Starting import', 'wheelpros-importer' ) ),
        );
        set_transient( $this->progress_key, $data, HOUR_IN_SECONDS );
    }

    /**
     * Update the progress transient with current counts.
     *
     * @param int $processed_rows Number of rows processed so far.
     * @param int $imported       Number of new posts created.
     * @param int $updated        Number of posts updated.
     * @param int $deleted        Number of posts deactivated.
     * @return void
     */
    protected function update_progress( $processed_rows, $imported, $updated, $deleted ) {
        if ( empty( $this->progress_key ) ) {
            return;
        }
        $data = get_transient( $this->progress_key );
        if ( ! $data ) {
            return;
        }
        $data['processed_rows'] = $processed_rows;
        $data['imported']       = $imported;
        $data['updated']        = $updated;
        $data['deleted']        = $deleted;
        // Determine completion.
        if ( $data['total_rows'] > 0 && $processed_rows >= $data['total_rows'] ) {
            $data['status'] = 'complete';
        }
        set_transient( $this->progress_key, $data, HOUR_IN_SECONDS );
    }

    /**
     * Append a message to the progress data.
     *
     * @param string $message The message to append.
     * @return void
     */
    protected function add_progress_message( $message ) {
        if ( empty( $this->progress_key ) ) {
            return;
        }
        $data = get_transient( $this->progress_key );
        if ( ! $data ) {
            return;
        }
        if ( ! isset( $data['messages'] ) || ! is_array( $data['messages'] ) ) {
            $data['messages'] = array();
        }
        $data['messages'][] = $message;
        // Limit stored messages to 100 entries to avoid excessive memory usage.
        if ( count( $data['messages'] ) > 100 ) {
            $data['messages'] = array_slice( $data['messages'], -100 );
        }
        set_transient( $this->progress_key, $data, HOUR_IN_SECONDS );
    }

    /**
     * Finalize the progress transient when import is complete.
     *
     * @return void
     */
    protected function finalize_progress() {
        if ( empty( $this->progress_key ) ) {
            return;
        }
        $data = get_transient( $this->progress_key );
        if ( $data ) {
            $data['status'] = 'complete';
            set_transient( $this->progress_key, $data, HOUR_IN_SECONDS );
        }
    }

    /**
     * Test the configured SFTP connection and count rows in the remote file.
     *
     * This method is used by the admin settings page to provide a live
     * connection status. It attempts to log in to the remote server and
     * download the file configured in plugin settings. If successful, it
     * counts the number of rows (excluding the header in CSV files) or
     * objects (in JSON files) and returns an associative array. On error,
     * it returns a WP_Error object with details.
     *
     * @return array|WP_Error ['rows' => int, 'type' => string]
     */
    public function test_sftp_connection() {
        $options = get_option( 'hp_wheelpros_options' );
        // Validate settings.
        if ( empty( $options['host'] ) || empty( $options['username'] ) || empty( $options['password'] ) || empty( $options['path'] ) ) {
            return new WP_Error( 'missing_settings', __( 'SFTP settings incomplete. Please configure host, username, password and path.', 'wheelpros-importer' ) );
        }
        $file_type = isset( $options['type'] ) ? $options['type'] : 'csv';
        $password  = HP_WheelPros_Core::decrypt( $options['password'] );
        $data = false;
        $errors = array();
        // Try phpseclib first.
        if ( class_exists( '\\phpseclib3\\Net\\SFTP' ) ) {
            try {
                $sftp = new \phpseclib3\Net\SFTP( $options['host'], $options['port'] ?: 22 );
                if ( ! $sftp->login( $options['username'], $password ) ) {
                    throw new \RuntimeException( 'SFTP login failed.' );
                }
                $remote_path = $options['path'];
                $data        = $sftp->get( $remote_path );
                if ( $data === false ) {
                    throw new \RuntimeException( 'Unable to download the remote file.' );
                }
            } catch ( \Exception $e ) {
                $errors[] = $e->getMessage();
                $data     = false;
            }
        }
        // Fallback to ssh2 if phpseclib is unavailable or fails.
        if ( $data === false && function_exists( 'ssh2_connect' ) ) {
            try {
                $connection = ssh2_connect( $options['host'], $options['port'] ?: 22 );
                if ( ! $connection ) {
                    throw new \RuntimeException( 'SSH2 connection failed.' );
                }
                if ( ! ssh2_auth_password( $connection, $options['username'], $password ) ) {
                    throw new \RuntimeException( 'SSH2 authentication failed.' );
                }
                $sftp_res  = ssh2_sftp( $connection );
                $remote_path = $options['path'];
                $remote_file = 'ssh2.sftp://' . intval( $sftp_res ) . $remote_path;
                $stream     = @fopen( $remote_file, 'r' );
                if ( ! $stream ) {
                    throw new \RuntimeException( 'Failed to open remote file via SSH2.' );
                }
                $data = stream_get_contents( $stream );
                fclose( $stream );
                if ( $data === false ) {
                    throw new \RuntimeException( 'Failed to read remote file via SSH2.' );
                }
            } catch ( \Exception $e ) {
                $errors[] = $e->getMessage();
                $data     = false;
            }
        }
        if ( $data === false ) {
            // Could not download using either method.
            if ( ! empty( $errors ) ) {
                return new WP_Error( 'sftp_connection_error', implode( ' | ', $errors ) );
            }
            return new WP_Error( 'sftp_unavailable', __( 'Neither phpseclib nor ssh2 is available. Unable to establish SFTP connection.', 'wheelpros-importer' ) );
        }
        // Save to temp file to count rows efficiently.
        $temp = wp_tempnam();
        if ( ! $temp ) {
            return new WP_Error( 'temp_error', __( 'Unable to create a temporary file.', 'wheelpros-importer' ) );
        }
        file_put_contents( $temp, $data );
        $rows = 0;
        if ( 'csv' === $file_type ) {
            $handle = fopen( $temp, 'r' );
            if ( $handle ) {
                fgetcsv( $handle );
                while ( ( $line = fgetcsv( $handle ) ) !== false ) {
                    $rows++;
                }
                fclose( $handle );
            }
        } elseif ( 'json' === $file_type ) {
            $contents = file_get_contents( $temp );
            $decoded  = json_decode( $contents, true );
            if ( is_array( $decoded ) ) {
                $rows = count( $decoded );
            }
        }
        unlink( $temp );
        return array( 'rows' => $rows, 'type' => $file_type );
    }

    /**
     * Import a file from the local filesystem.
     *
     * @param string $file_path Absolute path to the file.
     * @param string $type      File type: csv or json.
     * @return true|WP_Error
     */
    public function import_from_file( $file_path, $type = 'csv' ) {
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'File does not exist.', 'wheelpros-importer' ) );
        }
        if ( 'csv' === $type ) {
            return $this->import_csv( $file_path );
        } elseif ( 'json' === $type ) {
            return $this->import_json( $file_path );
        }
        return new WP_Error( 'invalid_type', __( 'Invalid file type.', 'wheelpros-importer' ) );
    }

    /**
     * Run weekly import: fetch file from SFTP and import.
     *
     * This method is triggered by the scheduled event defined in the main
     * plugin file. It retrieves the SFTP credentials and remote path from
     * plugin settings, downloads the file into a temporary location and then
     * calls import_from_file(). On completion, a log entry is recorded.
     */
    public function run_weekly_import() {
        $options = get_option( 'hp_wheelpros_options' );
        // Validate required settings.
        if ( empty( $options['host'] ) || empty( $options['username'] ) || empty( $options['password'] ) || empty( $options['path'] ) ) {
            HP_WheelPros_Logger::add( isset( $options['type'] ) ? $options['type'] : 'csv', 0, 0, 0, 'error', __( 'SFTP settings incomplete. Aborting scheduled import.', 'wheelpros-importer' ) );
            return;
        }
        $file_type = isset( $options['type'] ) ? $options['type'] : 'csv';
        // Decrypt password.
        $password = HP_WheelPros_Core::decrypt( $options['password'] );
        $temp_file = wp_tempnam();
        if ( ! $temp_file ) {
            HP_WheelPros_Logger::add( $file_type, 0, 0, 0, 'error', __( 'Unable to create temporary file.', 'wheelpros-importer' ) );
            return;
        }
        // Attempt to download file via SFTP using phpseclib or ssh2 extension.
        $downloaded = false;
        $error_message = '';
        // First try phpseclib3 if available.
        if ( class_exists( '\\phpseclib3\\Net\\SFTP' ) ) {
            try {
                $sftp = new \phpseclib3\Net\SFTP( $options['host'], $options['port'] ?: 22 );
                if ( ! $sftp->login( $options['username'], $password ) ) {
                    throw new \RuntimeException( 'SFTP login failed.' );
                }
                $remote_path = $options['path'];
                $data        = $sftp->get( $remote_path );
                if ( $data === false ) {
                    throw new \RuntimeException( 'Failed to download file.' );
                }
                file_put_contents( $temp_file, $data );
                $downloaded = true;
            } catch ( \Exception $e ) {
                // Capture error but continue to fallback.
                $error_message = $e->getMessage();
                $downloaded = false;
            }
        }
        // Fallback to php ssh2 extension if phpseclib is unavailable or failed.
        if ( ! $downloaded && function_exists( 'ssh2_connect' ) ) {
            try {
                $connection = ssh2_connect( $options['host'], $options['port'] ?: 22 );
                if ( ! $connection ) {
                    throw new \RuntimeException( 'SSH2 connection failed.' );
                }
                if ( ! ssh2_auth_password( $connection, $options['username'], $password ) ) {
                    throw new \RuntimeException( 'SSH2 authentication failed.' );
                }
                $sftp      = ssh2_sftp( $connection );
                $remote_path = $options['path'];
                $remote_file = 'ssh2.sftp://' . intval( $sftp ) . $remote_path;
                $stream    = @fopen( $remote_file, 'r' );
                if ( ! $stream ) {
                    throw new \RuntimeException( 'Failed to open remote file via SSH2.' );
                }
                $data = stream_get_contents( $stream );
                fclose( $stream );
                if ( $data === false ) {
                    throw new \RuntimeException( 'Failed to read remote file via SSH2.' );
                }
                file_put_contents( $temp_file, $data );
                $downloaded = true;
            } catch ( \Exception $e ) {
                $error_message = $e->getMessage();
                $downloaded = false;
            }
        }
        if ( ! $downloaded ) {
            // Log the most relevant error message.
            if ( empty( $error_message ) ) {
                $error_message = __( 'Unable to download the file via SFTP. Please verify your server has phpseclib or ssh2 installed and that your credentials and path are correct.', 'wheelpros-importer' );
            }
            HP_WheelPros_Logger::add( $file_type, 0, 0, 0, 'error', $error_message );
            // Return WP_Error so callers can display error messages immediately.
            return new WP_Error( 'sftp_download_failed', $error_message );
        }
        // Proceed with import.
        $result = $this->import_from_file( $temp_file, $file_type );
        // Remove temp file.
        unlink( $temp_file );
        // If import_from_file returned WP_Error, bubble it up.
        return $result;
    }

    /**
     * Import data from CSV file.
     *
     * @param string $file_path
     * @return true|WP_Error
     */
    protected function import_csv( $file_path ) {
        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'file_open_error', __( 'Unable to open CSV file.', 'wheelpros-importer' ) );
        }
        $header = fgetcsv( $handle );
        if ( ! $header ) {
            fclose( $handle );
            return new WP_Error( 'csv_header_error', __( 'Invalid CSV header.', 'wheelpros-importer' ) );
        }
        $rows         = array();
        $row_count    = 0;
        $imported     = 0;
        $updated      = 0;
        $deleted      = 0;
        $processed_ids = array();
        // Build mapping of header to index.
        $keys = array_map( 'trim', $header );
        // Process each row in batches.
        while ( ( $data = fgetcsv( $handle ) ) !== false ) {
            $row_count++;
            $row = array();
            foreach ( $keys as $i => $key ) {
                $row[ $key ] = isset( $data[ $i ] ) ? $data[ $i ] : '';
            }
            $rows[] = $row;
            if ( count( $rows ) >= self::BATCH_SIZE ) {
                list( $batch_imported, $batch_updated, $_processed ) = $this->process_rows( $rows );
                $imported += $batch_imported;
                $updated  += $batch_updated;
                $processed_ids = array_merge( $processed_ids, $_processed );
                $rows = array();
            }
        }
        fclose( $handle );
        // Process remaining rows.
        if ( ! empty( $rows ) ) {
            list( $batch_imported, $batch_updated, $_processed ) = $this->process_rows( $rows );
            $imported += $batch_imported;
            $updated  += $batch_updated;
            $processed_ids = array_merge( $processed_ids, $_processed );
        }
        // Mark any posts not processed as inactive/draft.
        $deleted = $this->deactivate_missing_posts( $processed_ids );
        // Log result.
        HP_WheelPros_Logger::add( 'csv', $imported, $updated, $deleted, 'success', sprintf( __( 'CSV import completed. %d imported, %d updated, %d deactivated.', 'wheelpros-importer' ), $imported, $updated, $deleted ) );
        return true;
    }

    /**
     * Import data from JSON file.
     *
     * @param string $file_path
     * @return true|WP_Error
     */
    protected function import_json( $file_path ) {
        $contents = file_get_contents( $file_path );
        if ( false === $contents ) {
            return new WP_Error( 'file_read_error', __( 'Unable to read JSON file.', 'wheelpros-importer' ) );
        }
        $data = json_decode( $contents, true );
        if ( null === $data ) {
            return new WP_Error( 'json_parse_error', __( 'Invalid JSON file.', 'wheelpros-importer' ) );
        }
        // JSON may either be an array of objects or a single object containing rows.
        $rows = array();
        if ( isset( $data[0] ) && is_array( $data[0] ) && isset( $data[0]['PartNumber'] ) ) {
            // Standard array of objects.
            $rows = $data;
        } elseif ( is_array( $data ) && count( $data ) == 1 ) {
            // Single object with numeric keys; treat as one row.
            $rows = array( $data[0] );
        } else {
            return new WP_Error( 'json_format_error', __( 'Unsupported JSON structure.', 'wheelpros-importer' ) );
        }
        $imported  = 0;
        $updated   = 0;
        $deleted   = 0;
        $processed_ids = array();
        // Process in batches.
        $batch = array();
        foreach ( $rows as $row ) {
            $batch[] = $row;
            if ( count( $batch ) >= self::BATCH_SIZE ) {
                list( $batch_imported, $batch_updated, $_processed ) = $this->process_rows( $batch );
                $imported += $batch_imported;
                $updated  += $batch_updated;
                $processed_ids = array_merge( $processed_ids, $_processed );
                $batch = array();
            }
        }
        // Remaining.
        if ( ! empty( $batch ) ) {
            list( $batch_imported, $batch_updated, $_processed ) = $this->process_rows( $batch );
            $imported += $batch_imported;
            $updated  += $batch_updated;
            $processed_ids = array_merge( $processed_ids, $_processed );
        }
        $deleted = $this->deactivate_missing_posts( $processed_ids );
        HP_WheelPros_Logger::add( 'json', $imported, $updated, $deleted, 'success', sprintf( __( 'JSON import completed. %d imported, %d updated, %d deactivated.', 'wheelpros-importer' ), $imported, $updated, $deleted ) );
        return true;
    }

    /**
     * Process an array of row objects and insert/update posts.
     *
     * @param array $rows Array of associative arrays representing wheel data.
     * @return array [int imported, int updated, array processedPartNumbers]
     */
    protected function process_rows( $rows ) {
        $imported  = 0;
        $updated   = 0;
        $processed = array();
        foreach ( $rows as $row ) {
            // Skip if no part number.
            if ( empty( $row['PartNumber'] ) ) {
                continue;
            }
            $part_number = trim( $row['PartNumber'] );
            $processed[] = $part_number;
            // Check if post exists by part number.
            $existing = $this->get_post_by_part_number( $part_number );
            $post_data = array(
                'post_title'   => $part_number,
                'post_type'    => 'hp_wheel',
                'post_status'  => 'publish',
            );
            if ( $existing ) {
                $post_id = $existing->ID;
                $post_data['ID'] = $post_id;
                // Update basic post data if necessary.
                wp_update_post( $post_data );
                $updated++;
            } else {
                $post_id = wp_insert_post( $post_data );
                if ( is_wp_error( $post_id ) ) {
                    // skip on error.
                    continue;
                }
                $imported++;
            }
            // Save meta fields (sanitized).
            $meta_map = array(
                'PartDescription' => 'part_description',
                'DisplayStyleNo'  => 'display_style_no',
                'Brand'           => 'brand',
                'Finish'          => 'finish',
                'Size'            => 'size',
                'BoltPattern'     => 'bolt_pattern',
                'Offset'          => 'offset',
                'CenterBore'      => 'center_bore',
                'LoadRating'      => 'load_rating',
                'ShippingWeight'  => 'shipping_weight',
                'ImageURL'        => 'image_url',
                'InvOrderType'    => 'inventory_order_type',
                'Style'           => 'style',
                'TotalQOH'        => 'total_qoh',
                'MSRP_USD'        => 'msrp_usd',
                'MAP_USD'         => 'map_usd',
                'RunDate'         => 'run_date',
            );
            foreach ( $meta_map as $key => $meta_key ) {
                if ( isset( $row[ $key ] ) ) {
                    $value = $row[ $key ];
                    // Use appropriate sanitization based on field.
                    if ( in_array( $meta_key, array( 'size', 'bolt_pattern', 'style', 'display_style_no', 'brand', 'finish', 'part_description' ), true ) ) {
                        $value = sanitize_text_field( $value );
                    } elseif ( in_array( $meta_key, array( 'offset', 'center_bore', 'load_rating', 'shipping_weight', 'total_qoh', 'msrp_usd', 'map_usd' ), true ) ) {
                        $value = sanitize_text_field( $value );
                    } elseif ( 'image_url' === $meta_key ) {
                        $value = esc_url_raw( $value );
                    } elseif ( 'run_date' === $meta_key ) {
                        $value = sanitize_text_field( $value );
                    } else {
                        $value = sanitize_text_field( $value );
                    }
                    update_post_meta( $post_id, 'hp_' . $meta_key, $value );
                }
            }
            // Save part number as meta.
            update_post_meta( $post_id, 'hp_part_number', $part_number );
            // Assign taxonomies.
            // Display style: group by DisplayStyleNo.
            if ( ! empty( $row['DisplayStyleNo'] ) ) {
                $term = sanitize_text_field( $row['DisplayStyleNo'] );
                wp_set_object_terms( $post_id, $term, 'hp_display_style', false );
            }
            // Brand taxonomy.
            if ( ! empty( $row['Brand'] ) ) {
                $brand = sanitize_text_field( $row['Brand'] );
                wp_set_object_terms( $post_id, $brand, 'hp_brand', false );
            }
            // Finish taxonomy.
            if ( ! empty( $row['Finish'] ) ) {
                $finish = sanitize_text_field( $row['Finish'] );
                wp_set_object_terms( $post_id, $finish, 'hp_finish', false );
            }
        }
        return array( $imported, $updated, $processed );
    }

    /**
     * Deactivate wheels that were not part of the current import.
     *
     * Posts with a part number not present in the processed list will be
     * transitioned to draft status instead of being deleted. Returns the
     * number of posts deactivated.
     *
     * @param array $processed_part_numbers List of part numbers processed.
     * @return int Number of posts deactivated.
     */
    protected function deactivate_missing_posts( $processed_part_numbers ) {
        $count = 0;
        $args  = array(
            'post_type'      => 'hp_wheel',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'hp_part_number',
                    'compare' => 'EXISTS',
                ),
            ),
        );
        $query = new WP_Query( $args );
        foreach ( $query->posts as $post_id ) {
            $part_number = get_post_meta( $post_id, 'hp_part_number', true );
            if ( ! in_array( $part_number, $processed_part_numbers, true ) ) {
                // Move to draft instead of deletion.
                wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
                $count++;
            }
        }
        return $count;
    }

    /**
     * Retrieve a wheel post by part number.
     *
     * @param string $part_number
     * @return WP_Post|false
     */
    protected function get_post_by_part_number( $part_number ) {
        $args = array(
            'post_type'      => 'hp_wheel',
            'posts_per_page' => 1,
            'meta_key'       => 'hp_part_number',
            'meta_value'     => $part_number,
            'post_status'    => array( 'publish', 'draft' ),
        );
        $posts = get_posts( $args );
        return ! empty( $posts ) ? $posts[0] : false;
    }

    /**
     * Import a CSV file and update progress along the way.
     *
     * @param string $file_path Absolute path to the file.
     * @param int    $total_rows Total number of rows expected.
     * @return true|WP_Error
     */
    public function import_csv_with_progress( $file_path, $total_rows ) {
        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'file_open_error', __( 'Unable to open CSV file.', 'wheelpros-importer' ) );
        }
        $header = fgetcsv( $handle );
        if ( ! $header ) {
            fclose( $handle );
            return new WP_Error( 'csv_header_error', __( 'Invalid CSV header.', 'wheelpros-importer' ) );
        }
        $keys       = array_map( 'trim', $header );
        $imported   = 0;
        $updated    = 0;
        $deleted    = 0;
        $processed_ids = array();
        $processed_rows = 0;
        $rows_buffer = array();
        while ( ( $data = fgetcsv( $handle ) ) !== false ) {
            $processed_rows++;
            $row = array();
            foreach ( $keys as $i => $key ) {
                $row[ $key ] = isset( $data[ $i ] ) ? $data[ $i ] : '';
            }
            $rows_buffer[] = $row;
            if ( count( $rows_buffer ) >= self::BATCH_SIZE ) {
                // Log the current batch being processed.
                $batch_start = max( 1, $processed_rows - ( count( $rows_buffer ) - 1 ) );
                $batch_end   = $processed_rows;
                $this->add_progress_message( sprintf( __( 'Importing %d-%d', 'wheelpros-importer' ), $batch_start, min( $batch_end, $total_rows ) ) );
                list( $batch_imported, $batch_updated, $_processed ) = $this->process_rows( $rows_buffer );
                $imported += $batch_imported;
                $updated  += $batch_updated;
                $processed_ids = array_merge( $processed_ids, $_processed );
                $rows_buffer = array();
                // Update progress after each batch.
                $this->update_progress( $processed_rows, $imported, $updated, $deleted );
                $this->add_progress_message( sprintf( __( '%1$d-%2$d processed: %3$d imported, %4$d updated', 'wheelpros-importer' ), $batch_start, $batch_end, $batch_imported, $batch_updated ) );
            }
        }
        fclose( $handle );
        if ( ! empty( $rows_buffer ) ) {
            $batch_start = max( 1, $processed_rows - count( $rows_buffer ) + 1 );
            $batch_end   = $processed_rows;
            $this->add_progress_message( sprintf( __( 'Importing %d-%d', 'wheelpros-importer' ), $batch_start, min( $batch_end, $total_rows ) ) );
            list( $batch_imported, $batch_updated, $_processed ) = $this->process_rows( $rows_buffer );
            $imported += $batch_imported;
            $updated  += $batch_updated;
            $processed_ids = array_merge( $processed_ids, $_processed );
            $this->add_progress_message( sprintf( __( '%1$d-%2$d processed: %3$d imported, %4$d updated', 'wheelpros-importer' ), $batch_start, $batch_end, $batch_imported, $batch_updated ) );
        }
        // Deactivate missing posts.
        $deleted = $this->deactivate_missing_posts( $processed_ids );
        // Final update with deleted count and processed_rows equals total_rows.
        $this->update_progress( $total_rows, $imported, $updated, $deleted );
        $this->add_progress_message( __( 'Import complete.', 'wheelpros-importer' ) );
        // Log result.
        HP_WheelPros_Logger::add( 'csv', $imported, $updated, $deleted, 'success', sprintf( __( 'CSV import completed. %d imported, %d updated, %d deactivated.', 'wheelpros-importer' ), $imported, $updated, $deleted ) );
        return true;
    }

    /**
     * Import a JSON file with progress updates.
     *
     * @param string $file_path
     * @param int    $total_rows
     * @return true|WP_Error
     */
    public function import_json_with_progress( $file_path, $total_rows ) {
        $contents = file_get_contents( $file_path );
        if ( false === $contents ) {
            return new WP_Error( 'file_read_error', __( 'Unable to read JSON file.', 'wheelpros-importer' ) );
        }
        $data = json_decode( $contents, true );
        if ( null === $data ) {
            return new WP_Error( 'json_parse_error', __( 'Invalid JSON file.', 'wheelpros-importer' ) );
        }
        // Determine rows array structure.
        $rows = array();
        if ( isset( $data[0] ) && is_array( $data[0] ) && isset( $data[0]['PartNumber'] ) ) {
            $rows = $data;
        } elseif ( is_array( $data ) && count( $data ) == 1 ) {
            $rows = array( $data[0] );
        } else {
            return new WP_Error( 'json_format_error', __( 'Unsupported JSON structure.', 'wheelpros-importer' ) );
        }
        $imported = 0;
        $updated  = 0;
        $deleted  = 0;
        $processed_ids = array();
        $processed_rows = 0;
        $batch = array();
        foreach ( $rows as $row ) {
            $processed_rows++;
            $batch[] = $row;
            if ( count( $batch ) >= self::BATCH_SIZE ) {
                // Log batch start.
                $batch_start = max( 1, $processed_rows - ( count( $batch ) - 1 ) );
                $batch_end   = $processed_rows;
                $this->add_progress_message( sprintf( __( 'Importing %d-%d', 'wheelpros-importer' ), $batch_start, min( $batch_end, $total_rows ) ) );
                list( $batch_imported, $batch_updated, $_processed ) = $this->process_rows( $batch );
                $imported += $batch_imported;
                $updated  += $batch_updated;
                $processed_ids = array_merge( $processed_ids, $_processed );
                $batch = array();
                // Update progress and message.
                $this->update_progress( $processed_rows, $imported, $updated, $deleted );
                $this->add_progress_message( sprintf( __( '%1$d-%2$d processed: %3$d imported, %4$d updated', 'wheelpros-importer' ), $batch_start, $batch_end, $batch_imported, $batch_updated ) );
            }
        }
        if ( ! empty( $batch ) ) {
            // Final incomplete batch.
            $batch_start = max( 1, $processed_rows - count( $batch ) + 1 );
            $batch_end   = $processed_rows;
            $this->add_progress_message( sprintf( __( 'Importing %d-%d', 'wheelpros-importer' ), $batch_start, min( $batch_end, $total_rows ) ) );
            list( $batch_imported, $batch_updated, $_processed ) = $this->process_rows( $batch );
            $imported += $batch_imported;
            $updated  += $batch_updated;
            $processed_ids = array_merge( $processed_ids, $_processed );
            $this->add_progress_message( sprintf( __( '%1$d-%2$d processed: %3$d imported, %4$d updated', 'wheelpros-importer' ), $batch_start, $batch_end, $batch_imported, $batch_updated ) );
        }
        $deleted = $this->deactivate_missing_posts( $processed_ids );
        $this->update_progress( $total_rows, $imported, $updated, $deleted );
        $this->add_progress_message( __( 'Import complete.', 'wheelpros-importer' ) );
        HP_WheelPros_Logger::add( 'json', $imported, $updated, $deleted, 'success', sprintf( __( 'JSON import completed. %d imported, %d updated, %d deactivated.', 'wheelpros-importer' ), $imported, $updated, $deleted ) );
        return true;
    }

    /**
     * Run the full import process with progress tracking.
     *
     * This method downloads the file via SFTP, initialises the progress
     * transient with the total row count (provided by the caller), and
     * processes the file according to its type. It should be called
     * asynchronously via AJAX to avoid blocking the UI.
     *
     * @param int $total_rows Total number of rows expected in the file.
     * @return true|WP_Error
     */
    public function run_import_with_progress( $total_rows ) {
        $options = get_option( 'hp_wheelpros_options' );
        if ( empty( $options['host'] ) || empty( $options['username'] ) || empty( $options['password'] ) || empty( $options['path'] ) ) {
            return new WP_Error( 'missing_settings', __( 'SFTP settings incomplete.', 'wheelpros-importer' ) );
        }
        $file_type = isset( $options['type'] ) ? $options['type'] : 'csv';
        $password  = HP_WheelPros_Core::decrypt( $options['password'] );
        $temp_file = wp_tempnam();
        if ( ! $temp_file ) {
            return new WP_Error( 'temp_error', __( 'Unable to create temporary file.', 'wheelpros-importer' ) );
        }
        // Download file via SFTP using existing method. We'll reuse run_weekly_import logic but without logging.
        $downloaded = false;
        $error_msg  = '';
        // Use phpseclib if available.
        if ( class_exists( '\\phpseclib3\\Net\\SFTP' ) ) {
            try {
                $sftp = new \phpseclib3\Net\SFTP( $options['host'], $options['port'] ?: 22 );
                if ( ! $sftp->login( $options['username'], $password ) ) {
                    throw new \RuntimeException( 'SFTP login failed.' );
                }
                $data = $sftp->get( $options['path'] );
                if ( $data === false ) {
                    throw new \RuntimeException( 'Failed to download file.' );
                }
                file_put_contents( $temp_file, $data );
                $downloaded = true;
            } catch ( \Exception $e ) {
                $error_msg = $e->getMessage();
                $downloaded = false;
            }
        }
        // Fallback to ssh2.
        if ( ! $downloaded && function_exists( 'ssh2_connect' ) ) {
            try {
                $connection = ssh2_connect( $options['host'], $options['port'] ?: 22 );
                if ( ! $connection ) {
                    throw new \RuntimeException( 'SSH2 connection failed.' );
                }
                if ( ! ssh2_auth_password( $connection, $options['username'], $password ) ) {
                    throw new \RuntimeException( 'SSH2 authentication failed.' );
                }
                $sftp_res = ssh2_sftp( $connection );
                $remote_file = 'ssh2.sftp://' . intval( $sftp_res ) . $options['path'];
                $stream = @fopen( $remote_file, 'r' );
                if ( ! $stream ) {
                    throw new \RuntimeException( 'Failed to open remote file via SSH2.' );
                }
                $data = stream_get_contents( $stream );
                fclose( $stream );
                if ( $data === false ) {
                    throw new \RuntimeException( 'Failed to read remote file via SSH2.' );
                }
                file_put_contents( $temp_file, $data );
                $downloaded = true;
            } catch ( \Exception $e ) {
                $error_msg = $e->getMessage();
                $downloaded = false;
            }
        }
        if ( ! $downloaded ) {
            unlink( $temp_file );
            return new WP_Error( 'sftp_download_failed', $error_msg ?: __( 'Could not download file.', 'wheelpros-importer' ) );
        }
        // Initialise progress and add an informative message about connection.
        $this->init_progress( $total_rows );
        $this->add_progress_message( sprintf( __( 'Connection successful. %d total rows detected.', 'wheelpros-importer' ), $total_rows ) );
        // Import with progress.
        $result = true;
        if ( 'csv' === $file_type ) {
            $result = $this->import_csv_with_progress( $temp_file, $total_rows );
        } elseif ( 'json' === $file_type ) {
            $result = $this->import_json_with_progress( $temp_file, $total_rows );
        } else {
            $result = new WP_Error( 'invalid_type', __( 'Invalid file type.', 'wheelpros-importer' ) );
        }
        unlink( $temp_file );
        $this->finalize_progress();
        return $result;
    }
}
