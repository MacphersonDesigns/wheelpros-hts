<?php
/**
 * Simple logging utility for WheelPros importer.
 *
 * This class writes structured log entries into a custom database table
 * (`{$wpdb->prefix}hp_wheelpros_logs`). Each log entry includes counts of
 * imported, updated and deleted items, status and a human readable message.
 * The table is created on activation via the `install` method. Logs can be
 * queried via the Admin class for display. Avoid storing sensitive data in
 * messages to keep logs safe for lower privilege users.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HP_WheelPros_Logger {

    /**
     * Create the logs table if it does not exist.
     */
    public static function install() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hp_wheelpros_logs';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            file_type varchar(10) NOT NULL,
            imported int(11) DEFAULT 0 NOT NULL,
            updated int(11) DEFAULT 0 NOT NULL,
            deleted int(11) DEFAULT 0 NOT NULL,
            status varchar(20) NOT NULL,
            message text NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Insert a new log entry.
     *
     * @param string $file_type  File type imported (csv/json).
     * @param int    $imported   Number of new records created.
     * @param int    $updated    Number of records updated.
     * @param int    $deleted    Number of records deleted.
     * @param string $status     Status: success|error|warning.
     * @param string $message    Human readable message.
     */
    public static function add( $file_type, $imported, $updated, $deleted, $status, $message ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'hp_wheelpros_logs',
            array(
                'file_type' => $file_type,
                'imported'  => (int) $imported,
                'updated'   => (int) $updated,
                'deleted'   => (int) $deleted,
                'status'    => sanitize_text_field( $status ),
                'message'   => sanitize_textarea_field( $message ),
            ),
            array( '%s', '%d', '%d', '%d', '%s', '%s' )
        );
    }
}
