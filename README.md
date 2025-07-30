# Harry's WheelPros Importer

A robust WordPress plugin for importing and displaying WheelPros wheel catalog data with advanced filtering and search capabilities.

## Features

- **Secure SFTP Import**: Import wheel data directly from WheelPros SFTP server
- **Two-Phase Import System**: Memory-efficient handling of large CSV files (50k+ records)
- **Manual Upload**: Fallback CSV upload option
- **Custom Post Type**: Dedicated `hp_wheel` post type for wheel data
- **Advanced Filtering**: Frontend shortcode with multiple filter options
- **Real-time Progress**: Live import progress tracking
- **Error Handling**: Comprehensive error logging and recovery
- **Automatic Updates**: Self-updating from GitHub releases

## Installation

1. Download the latest release
2. Upload to `/wp-content/plugins/harrys-wheelpros-importer/`
3. Activate the plugin through the WordPress admin
4. Configure SFTP settings in the admin panel

## Configuration

### SFTP Settings

Navigate to **WheelPros Importer > Settings** and configure:

- SFTP Host
- Username and Password
- Port (default: 22)
- Remote file path

### Import Options

- **Two-Phase Import**: Recommended for large files (downloads then processes in batches)
- **Manual Upload**: Upload CSV files directly
- **Scheduled Import**: Automatic weekly imports via WordPress cron

"🚀 FUTURE UPDATE WORKFLOW:

1. Make your code changes locally
2. Update version in plugin header:
   Edit: harrys-wheelpros-importer.php
   Change: Version: 1.6.0

3. Commit and push changes:
   git add .
   git commit -m 'Version 1.6.0: New features added'
   git push origin main

4. Create release tag:
   git tag -a v1.6.0 -m 'Release v1.6.0: Description'
   git push origin v1.6.0

5. Users automatically get update notifications! ✨

That's it - the Plugin Update Checker handles everything else!"

## Usage

### Admin Import

1. Go to **WheelPros Importer > Import**
2. Choose import method:
   - **Two-Phase Import**: For SFTP downloads (recommended)
   - **Manual Upload**: For local CSV files

### Frontend Display

Use the shortcode to display wheels with filtering:

```
[hp_wheels]
```

## Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+
- SFTP access (for automatic imports)

## Changelog

### 1.5.9

- Cleaned up admin interface
- Removed legacy import methods
- Optimized memory usage for large imports
- Added two-phase import system
- Improved error handling

## Support

For support and updates, visit the [GitHub repository](https://github.com/MacphersonDesigns/wheelpros-hts).

## License

GPL-2.0-or-later
