# WheelPros API Integration Guide

This guide will help you integrate with the WheelPros API instead of using CSV/JSON file imports. The API provides real-time access to inventory, pricing, and product data.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Authentication](#authentication)
3. [Available Endpoints](#available-endpoints)
4. [Testing with Postman](#testing-with-postman)
5. [PHP Implementation Examples](#php-implementation-examples)
6. [Common Issues & Solutions](#common-issues--solutions)

---

## Prerequisites

Before you can use the WheelPros API, you need:

1. **API Credentials**: Contact your WheelPros account manager to obtain:

   - API Username/Client ID
   - API Password/Client Secret
   - API Base URL (usually something like `https://api.wheelpros.com/v1/`)
   - Dealer/Account Number

2. **IP Whitelisting**: WheelPros may require your server's IP address to be whitelisted

3. **SSL Certificate**: Ensure your server can make HTTPS requests

---

## Authentication

WheelPros typically uses one of these authentication methods:

### Method 1: Basic Authentication

```
Authorization: Basic base64(username:password)
```

### Method 2: OAuth 2.0 (Token-based)

1. Request an access token
2. Use the token in subsequent requests

### Method 3: API Key

```
X-API-Key: your-api-key-here
```

**Finding Your Auth Method:**
Check your WheelPros API documentation or ask your account manager which method they use.

---

## Available Endpoints

### Common WheelPros API Endpoints

#### 1. **Get Product Catalog**

```
GET /products
GET /wheels
```

Returns list of all available wheel products.

**Query Parameters:**

- `page` - Page number
- `limit` - Items per page
- `brand` - Filter by brand
- `updated_since` - Get only recently updated items

#### 2. **Get Product Details**

```
GET /products/{part_number}
GET /wheels/{part_number}
```

Returns detailed information for a specific part.

#### 3. **Get Inventory/Availability**

```
GET /inventory
GET /inventory/{part_number}
```

Returns current stock levels.

#### 4. **Get Pricing**

```
GET /pricing
GET /pricing/{part_number}
```

Returns current pricing (may require dealer account).

#### 5. **Get Product Images**

```
GET /images/{part_number}
```

Returns image URLs for a product.

---

## Testing with Postman

### Setting Up Postman

1. **Create a New Collection**

   - Name it "WheelPros API"

2. **Set Up Authorization**

   - Go to Collection Settings → Authorization
   - Select your auth type (Basic Auth, Bearer Token, or API Key)
   - Enter your credentials

3. **Set Up Variables**
   - Create collection variables:
     - `base_url`: `https://api.wheelpros.com/v1`
     - `username`: Your API username
     - `password`: Your API password
     - `api_key`: Your API key (if applicable)

### Example Requests

#### Test Request 1: List Products

```
GET {{base_url}}/products?limit=10
```

**Expected Response:**

```json
{
 "status": "success",
 "data": [
  {
   "part_number": "ABC123",
   "brand": "AMERICAN RACING",
   "description": "AR923 Mod 12",
   "size": "20x9.0",
   "finish": "Gloss Black",
   "bolt_pattern": "5x120",
   "offset": "+30",
   "center_bore": "72.6",
   "image_url": "https://cdn.wheelpros.com/images/ABC123.jpg",
   "inventory": 45,
   "price": 189.99
  }
 ],
 "pagination": {
  "current_page": 1,
  "total_pages": 150,
  "total_items": 1500
 }
}
```

#### Test Request 2: Get Single Product

```
GET {{base_url}}/products/ABC123
```

#### Test Request 3: Get Inventory

```
GET {{base_url}}/inventory?part_number=ABC123
```

### Troubleshooting Postman Issues

**Issue: "401 Unauthorized"**

- Check your credentials are correct
- Verify authorization method matches WheelPros requirements
- Ensure your IP is whitelisted

**Issue: "404 Not Found"**

- Verify the base URL is correct
- Check the endpoint path
- Confirm the API version number

**Issue: "429 Too Many Requests"**

- WheelPros may have rate limits
- Add delays between requests
- Contact them for higher limits

**Issue: "SSL Certificate Error"**

- Disable SSL verification in Postman (Settings → General → SSL certificate verification OFF)
- Only for testing - don't disable in production!

---

## PHP Implementation Examples

### Example 1: Basic API Request Class

```php
<?php
/**
 * WheelPros API Client
 */
class WheelPros_API_Client {

    private $base_url;
    private $username;
    private $password;
    private $api_key;

    public function __construct( $base_url, $username, $password, $api_key = '' ) {
        $this->base_url = rtrim( $base_url, '/' );
        $this->username = $username;
        $this->password = $password;
        $this->api_key = $api_key;
    }

    /**
     * Make API request
     */
    public function request( $endpoint, $method = 'GET', $data = array() ) {
        $url = $this->base_url . '/' . ltrim( $endpoint, '/' );

        $args = array(
            'method'  => $method,
            'timeout' => 30,
            'headers' => $this->get_headers(),
        );

        if ( $method === 'POST' || $method === 'PUT' ) {
            $args['body'] = json_encode( $data );
        } elseif ( ! empty( $data ) ) {
            $url = add_query_arg( $data, $url );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', $response->get_error_message() );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $status_code < 200 || $status_code >= 300 ) {
            return new WP_Error(
                'api_error',
                sprintf( 'API returned status %d: %s', $status_code, $body ),
                array( 'status' => $status_code )
            );
        }

        return $data;
    }

    /**
     * Get request headers
     */
    private function get_headers() {
        $headers = array(
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        );

        // Method 1: Basic Auth
        if ( ! empty( $this->username ) && ! empty( $this->password ) ) {
            $headers['Authorization'] = 'Basic ' . base64_encode( $this->username . ':' . $this->password );
        }

        // Method 3: API Key
        if ( ! empty( $this->api_key ) ) {
            $headers['X-API-Key'] = $this->api_key;
        }

        return $headers;
    }

    /**
     * Get all products with pagination
     */
    public function get_products( $page = 1, $limit = 100 ) {
        return $this->request( 'products', 'GET', array(
            'page'  => $page,
            'limit' => $limit,
        ) );
    }

    /**
     * Get product by part number
     */
    public function get_product( $part_number ) {
        return $this->request( 'products/' . urlencode( $part_number ) );
    }

    /**
     * Get inventory for part number
     */
    public function get_inventory( $part_number ) {
        return $this->request( 'inventory/' . urlencode( $part_number ) );
    }
}
```

### Example 2: Integrating with Your Plugin

Add this to your `class-hp-wheelpros-importer.php`:

```php
/**
 * Import from WheelPros API instead of CSV
 */
public function import_from_api() {
    $options = get_option( 'hp_wheelpros_options' );

    // Initialize API client
    $api_client = new WheelPros_API_Client(
        $options['api_base_url'],
        $options['api_username'],
        $options['api_password'],
        $options['api_key']
    );

    $imported = 0;
    $updated = 0;
    $errors = 0;
    $page = 1;
    $per_page = 100;

    do {
        // Get batch of products
        $result = $api_client->get_products( $page, $per_page );

        if ( is_wp_error( $result ) ) {
            HP_WheelPros_Logger::add( 'api', 0, 0, 0, 'error', $result->get_error_message() );
            break;
        }

        $products = isset( $result['data'] ) ? $result['data'] : array();

        foreach ( $products as $product ) {
            // Convert API data to your format
            $row = array(
                'PartNumber'      => $product['part_number'],
                'PartDescription' => $product['description'],
                'Brand'           => $product['brand'],
                'Size'            => $product['size'],
                'Finish'          => $product['finish'],
                'BoltPattern'     => $product['bolt_pattern'],
                'Offset'          => $product['offset'],
                'CenterBore'      => $product['center_bore'],
                'ImageURL'        => $product['image_url'],
                'TotalQOH'        => $product['inventory'],
                'MSRP_USD'        => $product['price'],
                // Map other fields...
            );

            // Process using existing logic
            list( $batch_imported, $batch_updated, $_processed ) = $this->process_rows( array( $row ) );
            $imported += $batch_imported;
            $updated += $batch_updated;
        }

        // Check if there are more pages
        $has_more = isset( $result['pagination']['current_page'] ) &&
                    $result['pagination']['current_page'] < $result['pagination']['total_pages'];

        $page++;

    } while ( $has_more );

    HP_WheelPros_Logger::add( 'api', $imported, $updated, 0, 'success',
        sprintf( 'API import completed. %d imported, %d updated.', $imported, $updated ) );

    return true;
}
```

### Example 3: Add API Settings to Admin

Add these fields to your settings page:

```php
// In register_settings() method
add_settings_field(
    'hp_wheelpros_api_enabled',
    __( 'Use API Instead of CSV', 'wheelpros-importer' ),
    array( $this, 'render_field_checkbox' ),
    'hp-wheelpros-settings',
    'hp_wheelpros_sftp_section',
    array( 'id' => 'api_enabled', 'label' => 'Enable API Import' )
);

add_settings_field(
    'hp_wheelpros_api_base_url',
    __( 'API Base URL', 'wheelpros-importer' ),
    array( $this, 'render_field_text' ),
    'hp-wheelpros-settings',
    'hp_wheelpros_sftp_section',
    array( 'id' => 'api_base_url', 'placeholder' => 'https://api.wheelpros.com/v1' )
);

add_settings_field(
    'hp_wheelpros_api_username',
    __( 'API Username', 'wheelpros-importer' ),
    array( $this, 'render_field_text' ),
    'hp-wheelpros-settings',
    'hp_wheelpros_sftp_section',
    array( 'id' => 'api_username' )
);

add_settings_field(
    'hp_wheelpros_api_password',
    __( 'API Password', 'wheelpros-importer' ),
    array( $this, 'render_field_password' ),
    'hp-wheelpros-settings',
    'hp_wheelpros_sftp_section',
    array( 'id' => 'api_password' )
);

add_settings_field(
    'hp_wheelpros_api_key',
    __( 'API Key (if required)', 'wheelpros-importer' ),
    array( $this, 'render_field_text' ),
    'hp-wheelpros-settings',
    'hp_wheelpros_sftp_section',
    array( 'id' => 'api_key' )
);
```

---

## Common Issues & Solutions

### Issue 1: "Connection Timeout"

**Solutions:**

- Increase PHP timeout: `set_time_limit(300);`
- Add timeout to request: `'timeout' => 60`
- Process data in smaller batches

### Issue 2: "SSL Certificate Problem"

**Solutions:**

```php
// Add to request args (TESTING ONLY)
'sslverify' => false
```

For production, fix the SSL certificate chain on your server.

### Issue 3: "Rate Limiting"

**Solutions:**

```php
// Add delay between requests
sleep(1); // Wait 1 second between API calls

// Or use transient caching
$cache_key = 'wp_api_products_' . $page;
$cached = get_transient( $cache_key );
if ( $cached !== false ) {
    return $cached;
}
// ... make API call ...
set_transient( $cache_key, $result, 3600 ); // Cache for 1 hour
```

### Issue 4: "Data Format Mismatch"

The API might return data in different field names than your CSV. Create a mapping function:

```php
private function map_api_to_csv_format( $api_product ) {
    return array(
        'PartNumber'      => $api_product['partNumber'] ?? $api_product['part_number'],
        'Brand'           => $api_product['brandName'] ?? $api_product['brand'],
        'DisplayStyleNo'  => $api_product['styleNumber'] ?? $api_product['style_no'],
        // Add more mappings as needed
    );
}
```

---

## Next Steps

1. **Get your API credentials** from WheelPros
2. **Test the connection** in Postman first
3. **Document the exact endpoints** WheelPros provides you
4. **Implement the API client** using the examples above
5. **Add error handling** and logging
6. **Test with a small batch** before full import
7. **Schedule regular syncs** to keep data updated

---

## Support Resources

- **WheelPros Support**: Contact your account manager
- **API Documentation**: Request from WheelPros if not already provided
- **This Plugin's Support**: Check GitHub issues or contact the developer

---

## Testing Checklist

- [ ] API credentials work in Postman
- [ ] Can retrieve product list
- [ ] Can retrieve single product details
- [ ] Can retrieve inventory data
- [ ] Images URLs are accessible
- [ ] Data maps correctly to WordPress custom post type
- [ ] Import doesn't timeout with large datasets
- [ ] Error handling works properly
- [ ] Logging captures all important events

---

**Last Updated:** November 2025  
**Plugin Version:** 1.9.0+
