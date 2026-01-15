# Vehicle Search & Fitment Filtering

## Overview

The Vehicle Search feature allows customers to filter WheelPros products based on their specific vehicle's year, make, model, and trim. This ensures they only see products that fit their vehicle.

## How It Works

### 1. Cascading Dropdowns

The vehicle selector uses a **cascading dropdown pattern**:

```
Year → Make → Model → Submodel (Trim)
```

Each selection filters the next dropdown:

- Select **Year** → Shows only makes available for that year
- Select **Make** → Shows only models for that make/year combination
- Select **Model** → Shows only submodels/trims for that model (optional)
- Click **Search Products** → Filters products by vehicle fitment specs

### 2. WheelPros Vehicle API

The system uses these WheelPros Vehicle API endpoints:

| Endpoint                                                                         | Returns                                     |
| -------------------------------------------------------------------------------- | ------------------------------------------- |
| `GET /vehicles/v1/years`                                                         | Array of years `[2024, 2023, 2022...]`      |
| `GET /vehicles/v1/years/{year}/makes`                                            | Array of makes `["Ford", "Audi", "BMW"...]` |
| `GET /vehicles/v1/years/{year}/makes/{make}/models`                              | Array of models `["F-150", "A3", "X5"...]`  |
| `GET /vehicles/v1/years/{year}/makes/{make}/models/{model}/submodels`            | Array of submodels/trims                    |
| `GET /vehicles/v1/years/{year}/makes/{make}/models/{model}/submodels/{submodel}` | Complete vehicle specs                      |

### 3. Vehicle Specifications Response

When a vehicle is selected, the API returns detailed fitment data:

```json
{
	"id": 33252,
	"make": "Ford",
	"model": "F-150",
	"subModel": "Raptor",
	"year": 2020,
	"properties": {
		"staggered": false
	},
	"axles": {
		"front": {
			"boltPatternMm": "135.00",
			"boltPatternTx": "6x135",
			"oeWidthIn": "8.50",
			"oeDiameterIn": "17.00",
			"oeTireTx": "315/70R17",
			"centerBoreMm": "87.10",
			"lugPatternTx": "M14 x 1.5",
			"offset": {
				"oeOffset": "17x8.5 +34mm",
				"offsetMaxMm": 35,
				"offsetMinMm": 12
			}
		}
	}
}
```

## Installation & Usage

### Basic Setup

1. **Add the Shortcode to Any Page:**

```
[wheelpros_vehicle_search]
```

That's it! The form will automatically load with all cascading functionality.

### Advanced Options

```
[wheelpros_vehicle_search type="wheel" show_specs="true" callback="myCustomHandler"]
```

**Parameters:**

- `type` - Filter by product type: `wheel` or `tire`
- `show_specs` - Display vehicle specs after selection: `true` or `false`
- `callback` - JavaScript function name to call when vehicle is selected

### Example: Vehicle Search Page

Create a new page called "Find Products for My Vehicle" and add:

```
[wheelpros_vehicle_search type="wheel" show_specs="true"]
```

## Product Filtering

### Method 1: URL Parameters (Redirect to Shop)

When a customer selects their vehicle, the form can redirect to your shop page with URL parameters:

```
/shop/?year=2020&make=Ford&model=F-150&submodel=Raptor
```

Then filter products using the example code in `examples/vehicle-filter-examples.php`:

```php
// In your theme's functions.php
add_action('pre_get_posts', 'hp_filter_products_by_vehicle');
```

### Method 2: AJAX Loading (No Page Reload)

For a smoother experience, load products via AJAX:

```javascript
// Custom callback function
[wheelpros_vehicle_search callback="loadProductsAjax"]

<script>
function loadProductsAjax(vehicle) {
    jQuery.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
            action: 'load_vehicle_products',
            year: vehicle.year,
            make: vehicle.make,
            model: vehicle.model,
            nonce: '...'
        },
        success: function(response) {
            jQuery('#products').html(response.data.html);
        }
    });
}
</script>
```

See `examples/vehicle-filter-examples.php` for complete implementation.

### Method 3: JavaScript Event Listener

Listen for vehicle selection anywhere in your theme:

```javascript
jQuery(document).on("hp_vehicle_selected", function (event, vehicle) {
	console.log("Vehicle selected:", vehicle);
	// vehicle = {year: 2020, make: "Ford", model: "F-150", submodel: "Raptor"}

	// Your custom logic here
});
```

## Filtering Logic

### How to Filter Products by Vehicle Specs

Once you have the vehicle specs, filter products by:

1. **Bolt Pattern** - Match exact bolt pattern (e.g., "6x135")
2. **Diameter** - Match wheel diameter within range
3. **Width** - Match wheel width within range
4. **Offset** - Match offset within min/max range
5. **Center Bore** - Match or exceed center bore size

**Example WordPress Query:**

```php
$args = array(
    'post_type' => 'wheelpros_wheel',
    'meta_query' => array(
        'relation' => 'AND',
        array(
            'key' => 'bolt_pattern',
            'value' => '6x135', // From vehicle specs
            'compare' => '='
        ),
        array(
            'key' => 'diameter',
            'value' => 17, // From vehicle specs
            'compare' => '='
        ),
        array(
            'key' => 'offset',
            'value' => array(12, 35), // From offsetMinMm/offsetMaxMm
            'compare' => 'BETWEEN',
            'type' => 'NUMERIC'
        )
    )
);

$products = new WP_Query($args);
```

## Important: Authentication Required

**⚠️ The Vehicle API requires authentication!**

Before the vehicle selector will work, you must:

1. Open Postman
2. Find "WheelPros Vehicle API" collection
3. Go to **Authorization** tab
4. Set **Type:** Bearer Token
5. Set **Token:** `{{auth_token}}`
6. **Save** the collection

This ensures the WordPress plugin can authenticate with the WheelPros API.

## Caching Strategy

Vehicle data doesn't change often, so we cache API responses:

- **Years/Makes/Models:** Cached for 1 week
- **Vehicle Specs:** Cached for 1 week
- **Auth Token:** Cached for 1 hour (auto-refresh)

This reduces API calls and improves performance.

## Customization

### Styling

Edit `/css/vehicle-selector.css` to match your theme's design.

### Translations

All strings are translatable using the `harrys-wheelpros` text domain:

```php
__('Select Year', 'harrys-wheelpros')
__('Select Make', 'harrys-wheelpros')
__('Select Model', 'harrys-wheelpros')
__('Select Trim', 'harrys-wheelpros')
```

### JavaScript Events

Available events:

- `hp_vehicle_selected` - Fired when vehicle is selected
- `hp_vehicle_reset` - Fired when form is reset

## Files Reference

| File                                           | Purpose                             |
| ---------------------------------------------- | ----------------------------------- |
| `core/class-hp-wheelpros-vehicle-selector.php` | Main PHP class with AJAX handlers   |
| `js/vehicle-selector.js`                       | Cascading dropdown JavaScript logic |
| `css/vehicle-selector.css`                     | Form styling                        |
| `examples/vehicle-filter-examples.php`         | Complete filtering examples         |

## Troubleshooting

### Dropdowns not loading?

1. Check browser console for errors
2. Verify WheelPros API credentials in plugin settings
3. Ensure Vehicle API collection has authentication configured in Postman

### Products not filtering?

1. Verify vehicle specs are being retrieved (check browser console)
2. Ensure product meta fields match vehicle spec keys
3. Check `hp_get_vehicle_specs_cached()` function is working

### Authentication errors?

1. Test Auth API in Postman first
2. Verify token is being saved to collection variables
3. Check WordPress transients: `get_transient('wheelpros_auth_token')`

## Next Steps

1. **Add Vehicle Selector to Homepage** - Help customers find products immediately
2. **Enhance Product Display** - Show "Fits your [Year Make Model]" badge
3. **Save Vehicle Selection** - Remember customer's vehicle in session
4. **Garage Feature** - Let customers save multiple vehicles to their account
5. **Visual Fitment** - Show product photos installed on similar vehicles

## Support

For questions or issues with the vehicle selector, check:

- `examples/vehicle-filter-examples.php` for implementation examples
- WheelPros Vehicle API documentation
- Browser console for JavaScript errors
- WordPress debug log for PHP errors
