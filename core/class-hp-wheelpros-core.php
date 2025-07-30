<?php
/**
 * Core functionality for the WheelPros importer plugin.
 *
 * This class handles registration of custom post types and taxonomies,
 * encryption/decryption utilities for sensitive data, and helper methods
 * used throughout the plugin. Core logic is separated from admin and
 * importer code to maintain a clean, modular architecture.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HP_WheelPros_Core {

    /**
     * Register the custom post type used to store wheel products.
     *
     * The CPT is non-hierarchical, public, and supports a handful of core
     * features. We intentionally disable the editor since all content is
     * stored in meta fields. The CPT is registered on the `init` hook as
     * recommended by WordPress【595164492155795†L80-L87】.
     */
    public static function register_cpt() {
        $labels = array(
            'name'               => _x( 'Wheels', 'post type general name', 'wheelpros-importer' ),
            'singular_name'      => _x( 'Wheel', 'post type singular name', 'wheelpros-importer' ),
            'menu_name'          => _x( 'Wheels', 'admin menu', 'wheelpros-importer' ),
            'name_admin_bar'     => _x( 'Wheel', 'add new on admin bar', 'wheelpros-importer' ),
            'add_new'            => _x( 'Add New', 'wheel', 'wheelpros-importer' ),
            'add_new_item'       => __( 'Add New Wheel', 'wheelpros-importer' ),
            'new_item'           => __( 'New Wheel', 'wheelpros-importer' ),
            'edit_item'          => __( 'Edit Wheel', 'wheelpros-importer' ),
            'view_item'          => __( 'View Wheel', 'wheelpros-importer' ),
            'all_items'          => __( 'All Wheels', 'wheelpros-importer' ),
            'search_items'       => __( 'Search Wheels', 'wheelpros-importer' ),
            'parent_item_colon'  => __( 'Parent Wheels:', 'wheelpros-importer' ),
            'not_found'          => __( 'No wheels found.', 'wheelpros-importer' ),
            'not_found_in_trash' => __( 'No wheels found in Trash.', 'wheelpros-importer' )
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            // Place the CPT under our plugin's top‑level menu. This ensures
            // the Wheels list appears in the admin sidebar. Without this,
            // users cannot see or manage wheel posts from the backend.
            'show_in_menu'       => 'hp-wheelpros',
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'wheel' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title', 'thumbnail', 'custom-fields' ),
            'show_in_rest'       => true,
        );
        
        register_post_type( 'hp_wheel', $args );
    }

    /**
     * Register taxonomies for grouping and filtering wheels.
     *
     * A custom taxonomy for `display_style` groups wheels that share the same
     * DisplayStyleNo. Brands and finish are also registered as taxonomies to
     * facilitate filtering on the front‑end. Taxonomies are registered via
     * the `$taxonomies` argument of `register_post_type` or separately on
     * `init`【595164492155795†L80-L87】.
     */
    public static function register_taxonomies() {
        // Display Style taxonomy.
        $labels = array(
            'name'              => _x( 'Display Styles', 'taxonomy general name', 'wheelpros-importer' ),
            'singular_name'     => _x( 'Display Style', 'taxonomy singular name', 'wheelpros-importer' ),
            'search_items'      => __( 'Search Display Styles', 'wheelpros-importer' ),
            'all_items'         => __( 'All Display Styles', 'wheelpros-importer' ),
            'edit_item'         => __( 'Edit Display Style', 'wheelpros-importer' ),
            'update_item'       => __( 'Update Display Style', 'wheelpros-importer' ),
            'add_new_item'      => __( 'Add New Display Style', 'wheelpros-importer' ),
            'new_item_name'     => __( 'New Display Style Name', 'wheelpros-importer' ),
            'menu_name'         => __( 'Display Style', 'wheelpros-importer' ),
        );
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_in_menu'      => false,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'display-style' ),
            'show_in_rest'      => true,
        );
        register_taxonomy( 'hp_display_style', array( 'hp_wheel' ), $args );

        // Brand taxonomy.
        $brand_labels = array(
            'name'              => _x( 'Brands', 'taxonomy general name', 'wheelpros-importer' ),
            'singular_name'     => _x( 'Brand', 'taxonomy singular name', 'wheelpros-importer' ),
            'search_items'      => __( 'Search Brands', 'wheelpros-importer' ),
            'all_items'         => __( 'All Brands', 'wheelpros-importer' ),
            'edit_item'         => __( 'Edit Brand', 'wheelpros-importer' ),
            'update_item'       => __( 'Update Brand', 'wheelpros-importer' ),
            'add_new_item'      => __( 'Add New Brand', 'wheelpros-importer' ),
            'new_item_name'     => __( 'New Brand Name', 'wheelpros-importer' ),
            'menu_name'         => __( 'Brand', 'wheelpros-importer' ),
        );
        $brand_args = array(
            'hierarchical'      => true,
            'labels'            => $brand_labels,
            'show_ui'           => true,
            'show_in_menu'      => false,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'wheel-brand' ),
            'show_in_rest'      => true,
        );
        register_taxonomy( 'hp_brand', array( 'hp_wheel' ), $brand_args );

        // Finish taxonomy.
        $finish_labels = array(
            'name'              => _x( 'Finishes', 'taxonomy general name', 'wheelpros-importer' ),
            'singular_name'     => _x( 'Finish', 'taxonomy singular name', 'wheelpros-importer' ),
            'search_items'      => __( 'Search Finishes', 'wheelpros-importer' ),
            'all_items'         => __( 'All Finishes', 'wheelpros-importer' ),
            'edit_item'         => __( 'Edit Finish', 'wheelpros-importer' ),
            'update_item'       => __( 'Update Finish', 'wheelpros-importer' ),
            'add_new_item'      => __( 'Add New Finish', 'wheelpros-importer' ),
            'new_item_name'     => __( 'New Finish Name', 'wheelpros-importer' ),
            'menu_name'         => __( 'Finish', 'wheelpros-importer' ),
        );
        $finish_args = array(
            'hierarchical'      => true,
            'labels'            => $finish_labels,
            'show_ui'           => true,
            'show_in_menu'      => false,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'wheel-finish' ),
            'show_in_rest'      => true,
        );
        register_taxonomy( 'hp_finish', array( 'hp_wheel' ), $finish_args );
    }

    /**
     * Encrypt a string using OpenSSL and a site-specific key.
     *
     * The encrypted string is base64-encoded so it can be stored safely in
     * the database. If OpenSSL is not available, the original string is
     * returned. DO NOT store encryption keys in source code; ideally you
     * should set HP_WHEELPROS_SECRET_KEY in wp-config.php.
     *
     * @param string $plain_text The data to encrypt.
     * @return string
     */
    public static function encrypt( $plain_text ) {
        $key = defined( 'HP_WHEELPROS_SECRET_KEY' ) ? HP_WHEELPROS_SECRET_KEY : AUTH_KEY;
        $method = 'aes-256-cbc';
        if ( function_exists( 'openssl_encrypt' ) ) {
            $ivlen    = openssl_cipher_iv_length( $method );
            $iv       = random_bytes( $ivlen );
            $encrypted = openssl_encrypt( $plain_text, $method, $key, 0, $iv );
            // Store as two base64‑encoded parts separated by a colon. This avoids
            // the possibility of the delimiter appearing in binary data. It is
            // backward‑compatible: older strings using '::' will still decrypt.
            return base64_encode( $iv ) . ':' . base64_encode( $encrypted );
        }
        // Fallback: return plain text if OpenSSL is unavailable (not ideal but better than no value).
        return $plain_text;
    }

    /**
     * Decrypt a string previously encrypted via encrypt().
     *
     * @param string $encrypted The encrypted data.
     * @return string
     */
    public static function decrypt( $encrypted ) {
        $key = defined( 'HP_WHEELPROS_SECRET_KEY' ) ? HP_WHEELPROS_SECRET_KEY : AUTH_KEY;
        $method = 'aes-256-cbc';
        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return $encrypted;
        }
        // Backward compatibility for old format: base64(iv::cipher).
        if ( strpos( $encrypted, '::' ) !== false ) {
            $data = base64_decode( $encrypted );
            if ( $data !== false ) {
                $parts = explode( '::', $data, 2 );
                if ( 2 === count( $parts ) ) {
                    list( $iv, $ciphertext ) = $parts;
                    return openssl_decrypt( $ciphertext, $method, $key, 0, $iv );
                }
            }
        }
        // New format: base64(iv):base64(cipher)
        $parts = explode( ':', $encrypted, 2 );
        if ( 2 === count( $parts ) ) {
            list( $b64_iv, $b64_cipher ) = $parts;
            $iv        = base64_decode( $b64_iv );
            $ciphertext = base64_decode( $b64_cipher );
            if ( $iv !== false && $ciphertext !== false ) {
                $decrypted = openssl_decrypt( $ciphertext, $method, $key, 0, $iv );
                if ( false !== $decrypted ) {
                    return $decrypted;
                }
            }
        }
        // If nothing matches, return input.
        return $encrypted;
    }
}
