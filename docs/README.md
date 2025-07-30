# Harry's WheelPros Importer Plugin

This plugin provides secure integration between Harry’s Tire Service and WheelPros’ product catalog.  It imports wheel data from a CSV or JSON file stored on a remote SFTP server and creates or updates a custom post type (`hp_wheel`) in WordPress.  Each wheel can be filtered by display style, brand and finish via custom taxonomies.

## Features

- **Custom Post Type** – A dedicated `hp_wheel` post type stores wheel inventory.  Posts include meta fields for attributes like part number, size, bolt pattern, MSRP, MAP and more.
- **Taxonomies for Grouping** – Wheels are grouped by `DisplayStyleNo` (display style taxonomy) and assigned brand and finish taxonomies.  This enables easy filtering on the front‑end.
- **Secure Credentials** – SFTP credentials are encrypted using AES‑256‑CBC before being stored in the database.  The encryption key should be defined in `wp-config.php` as `HP_WHEELPROS_SECRET_KEY` to prevent hard‑coding secrets.
- **Modular Architecture** – Core functionality resides in `core/`, admin UI in `admin/`, logs in the database, and documentation in `docs/`.  This separation facilitates maintenance and extension.
- **Scheduled Imports** – A weekly cron event downloads the latest CSV/JSON file and synchronizes the catalog.  Manual imports are available from the admin page for immediate updates.
- **Activity Logging** – Each import writes a log entry with counts of imported, updated and deactivated items.  Logs are viewable from the “Logs” submenu.
- **Security Best Practices** – All admin actions are protected with capability checks and nonces.  Input is validated and sanitized early, and sensitive data is never exposed in logs.

## Setup

1. **Install the Plugin** – Copy the plugin folder into your WordPress installation’s `wp-content/plugins/` directory.  Activate it from the Plugins page.
2. **Configure Settings** – Navigate to **WheelPros → Settings** in the admin dashboard.  Enter your SFTP host, port, username, password, remote file path and choose the file type (CSV or JSON).  Save the settings.
3. **Run an Import** – To trigger an import immediately, go to **WheelPros → Import**, upload a CSV or JSON file that matches the configured type, and click **Run Import**.  The import process may take several minutes for large files.
4. **Scheduled Imports** – The plugin automatically runs a weekly import based on the configured SFTP settings.  You can modify the schedule by changing the `weekly` cron interval or by using the WordPress cron API.
5. **View Logs** – Check the **WheelPros → Logs** page to see a history of imports and their outcomes.

## Notes

- **Deactivation** – If a wheel part number does not appear in the latest import, its post status is changed to `draft`.  This keeps a history of past products without deleting them.
- **Customization** – Feel free to extend the import logic (in `core/class-hp-wheelpros-importer.php`) to handle additional fields or integrate WheelPros’ APIs for real‑time data.  The plugin’s modular structure is designed for easy expansion.
- **Security** – Always define a unique `HP_WHEELPROS_SECRET_KEY` constant in your `wp-config.php` file to ensure password encryption is secure.  Follow WordPress’s recommendations for validating, sanitizing and escaping data when modifying the plugin【726752494393687†L48-L73】.
