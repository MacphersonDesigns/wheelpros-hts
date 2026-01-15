# Vehicle Search Setup - Quick Start Guide

## ✅ What I Just Built For You

I've created a complete **Vehicle Fitment Search System** for your WheelPros plugin! Here's what's included:

### New Files Created

1. **`core/class-hp-wheelpros-vehicle-selector.php`** (280 lines)

   - Main PHP class with AJAX handlers
   - Integrates with WheelPros Vehicle API
   - Handles authentication automatically
   - Renders the vehicle search form

2. **`js/vehicle-selector.js`** (450 lines)

   - Cascading dropdown logic
   - AJAX calls to load years → makes → models → trims
   - Session storage for vehicle selection
   - Custom events for integration

3. **`css/vehicle-selector.css`** (150 lines)

   - Professional styling
   - Responsive design
   - Loading states and animations

4. **`examples/vehicle-filter-examples.php`** (350 lines)

   - 5 complete implementation examples
   - Product filtering by vehicle specs
   - AJAX loading examples
   - Custom callbacks

5. **`VEHICLE-SEARCH.md`** (Complete documentation)
   - How the system works
   - API endpoints explained
   - Usage instructions
   - Troubleshooting guide

### Updated Files

- `harrys-wheelpros-importer.php` - Added vehicle selector initialization and shortcode
- `version.json` - Updated to v1.10.0 with changelog

---

## 🚀 How to Use It

### Step 1: Test the Vehicle Selector

Add this shortcode to any WordPress page:

```
[wheelpros_vehicle_search]
```

**That's it!** You'll get a form with cascading dropdowns:

- Year dropdown loads automatically
- Select year → Makes load
- Select make → Models load
- Select model → Trims load
- Click "Search Products" → Vehicle is selected

### Step 2: Configure Authentication (IMPORTANT!)

**⚠️ The Vehicle API needs authentication configured in Postman:**

1. Open Postman
2. Find "**WheelPros Vehicle API**" collection
3. Go to **Authorization** tab
4. Set **Type:** Bearer Token
5. Set **Token:** `{{auth_token}}`
6. Click **Save**

This ensures your WordPress site can authenticate with the API.

### Step 3: Filter Products by Vehicle

Choose one of these methods:

#### Option A: URL Redirect (Simple)

When a vehicle is selected, redirect to your shop page with parameters:

```
/shop/?year=2020&make=Ford&model=F-150
```

Then add this to your theme's `functions.php`:

```php
require_once WP_PLUGIN_DIR . '/harrys-wheelpros-importer/examples/vehicle-filter-examples.php';
add_action('pre_get_posts', 'hp_filter_products_by_vehicle');
```

#### Option B: AJAX Loading (Advanced)

Load products without page reload:

```
[wheelpros_vehicle_search callback="loadProductsAjax"]
```

See `examples/vehicle-filter-examples.php` for complete code.

#### Option C: Custom JavaScript

Listen for vehicle selection event:

```javascript
jQuery(document).on("hp_vehicle_selected", function (event, vehicle) {
	console.log(vehicle); // {year: 2020, make: "Ford", model: "F-150", ...}
	// Your custom logic
});
```

---

## 🎯 How the Vehicle API Works

### Cascading Pattern

```
1. GET /vehicles/v1/years
   → Returns: [2024, 2023, 2022...]

2. GET /vehicles/v1/years/2020/makes
   → Returns: ["Ford", "Audi", "BMW"...]

3. GET /vehicles/v1/years/2020/makes/Ford/models
   → Returns: ["F-150", "Mustang", "Explorer"...]

4. GET /vehicles/v1/years/2020/makes/Ford/models/F-150/submodels
   → Returns: ["Raptor", "Lariat", "King Ranch"...]

5. GET /vehicles/v1/years/2020/makes/Ford/models/F-150/submodels/Raptor
   → Returns: Complete vehicle specs
```

### Vehicle Specs Response

When a vehicle is fully selected, you get:

```json
{
	"axles": {
		"front": {
			"boltPatternMm": "135.00",
			"boltPatternTx": "6x135",
			"centerBoreMm": "87.10",
			"oeWidthIn": "8.50",
			"oeDiameterIn": "17.00",
			"oeTireTx": "315/70R17",
			"offset": {
				"offsetMinMm": 12,
				"offsetMaxMm": 35
			}
		}
	}
}
```

Use these specs to filter products by:

- Bolt pattern
- Wheel diameter
- Wheel width
- Offset range
- Center bore

---

## 📋 Quick Implementation Examples

### Example 1: Basic Vehicle Search Page

Create a page called "Find Products for Your Vehicle":

```
[wheelpros_vehicle_search show_specs="true"]
```

### Example 2: Wheel-Only Search

```
[wheelpros_vehicle_search type="wheel"]
```

### Example 3: With Custom Handler

```
[wheelpros_vehicle_search callback="myCustomFunction"]

<script>
function myCustomFunction(vehicle) {
    alert('You selected: ' + vehicle.year + ' ' + vehicle.make + ' ' + vehicle.model);
}
</script>
```

---

## 🔧 Filtering Products

Once you have the vehicle specs, filter products in WordPress:

```php
$args = array(
    'post_type' => 'wheelpros_wheel',
    'meta_query' => array(
        array(
            'key' => 'bolt_pattern',
            'value' => '6x135', // From vehicle specs
            'compare' => '='
        ),
        array(
            'key' => 'diameter',
            'value' => 17, // From vehicle specs
            'compare' => '='
        )
    )
);

$products = new WP_Query($args);
```

**Full examples in:** `examples/vehicle-filter-examples.php`

---

## 📁 File Structure

```
harrys-wheelpros-importer/
├── core/
│   └── class-hp-wheelpros-vehicle-selector.php ← Main class
├── js/
│   └── vehicle-selector.js ← Cascading logic
├── css/
│   └── vehicle-selector.css ← Styling
├── examples/
│   └── vehicle-filter-examples.php ← Implementation examples
├── VEHICLE-SEARCH.md ← Complete documentation
└── harrys-wheelpros-importer.php ← Updated with shortcode
```

---

## ⚠️ Important Notes

1. **Authentication Required**

   - Vehicle API collection must have Bearer Token auth configured in Postman
   - Token is auto-cached for 1 hour
   - WordPress plugin handles auth automatically

2. **Caching Strategy**

   - Years/Makes/Models cached for 1 week
   - Vehicle specs cached for 1 week
   - Auth token cached for 1 hour
   - Reduces API calls and improves speed

3. **Product Meta Fields**
   - Ensure your wheel products have meta fields like:
     - `bolt_pattern`
     - `diameter`
     - `width`
     - `offset`
   - Match these to vehicle specs for filtering

---

## 🎨 Customization

### Change Colors

Edit `css/vehicle-selector.css`:

```css
.hp-vehicle-selector__submit {
	background-color: #YOUR_COLOR;
}
```

### Translations

All text is translatable:

```php
__('Select Year', 'harrys-wheelpros')
```

---

## 📖 Documentation

For complete details, see:

- **`VEHICLE-SEARCH.md`** - Full documentation
- **`examples/vehicle-filter-examples.php`** - 5 implementation examples

---

## 🐛 Troubleshooting

### Dropdowns not loading?

1. Check browser console for errors
2. Verify Postman Vehicle API has authentication
3. Test Auth API first

### Products not filtering?

1. Check vehicle specs are retrieved (console.log)
2. Verify product meta fields exist
3. Test SQL query directly

### Authentication errors?

1. Confirm Auth API works in Postman
2. Check token is saved to `{{auth_token}}`
3. Test `get_transient('wheelpros_auth_token')` in WordPress

---

## ✅ Next Steps

1. **Test the shortcode** - Add `[wheelpros_vehicle_search]` to a page
2. **Configure authentication** - Add Bearer Token to Vehicle API in Postman
3. **Choose filtering method** - Pick URL redirect, AJAX, or custom
4. **Customize styling** - Match your theme colors
5. **Test with real data** - Select a vehicle and verify products filter

---

## 🎉 What You Can Do Now

- ✅ Add vehicle search to any page with a shortcode
- ✅ Filter products by year/make/model/trim
- ✅ Show only products that fit customer's vehicle
- ✅ Improve customer experience and reduce returns
- ✅ Integrate with existing shop/product pages

**Everything is ready to go!** Just add the shortcode and test it out.

For questions, check `VEHICLE-SEARCH.md` or `examples/vehicle-filter-examples.php`.
