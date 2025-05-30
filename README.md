IT Asset Manager Lite
Plugin Name: Asset Manager MVC VErsio
Description: Custom post type for managing IT assets within WordPress, featuring detailed tracking, history logs, custom fields, notes with attachments, a dashboard for visualization, and PDF export.
Version: 1.9.1
Author: Your Name
Text Domain: asset-manager
Domain Path: /languages

Description
The IT Asset Manager Lite plugin provides a simple yet effective system for tracking your organization's IT assets directly within your WordPress dashboard. It introduces a custom post type for assets, allowing you to store detailed information, monitor their status and assignment, log historical changes, add notes and attachments, visualize data through a dashboard, and export your asset inventory to PDF.

Features
Custom Asset Post Type: A dedicated post type (asset) for managing individual IT assets.

Asset Categories Taxonomy: Organize assets using a hierarchical taxonomy (asset_category).

Custom Meta Fields: Store key asset information such as Asset Tag, Model, Serial Number, Brand, Supplier, Date Purchased, Issued To (linked to WordPress users), Status, Location, and Description.

Asset Image: Upload and associate an image with each asset.

Asset History: Automatically logs changes made to core asset details.

Asset Notes & Attachments: Add dated notes to assets and attach relevant files (documents, images, etc.) using the WordPress media uploader.

Admin List Table Enhancements:

Customizable columns displaying key asset data.

Filters for Category and Brand.

Extended search functionality covering custom meta fields, assigned user, and category name.

Asset Dashboard: Visual representation of asset data (e.g., Assets by Status, Assets by User, Assets by Category) using charts.

PDF Export: Export your entire asset inventory to a PDF file.

Auto-incrementing Titles: Automatically generates unique titles for assets (e.g., ASSET00001).

Requirements
WordPress 5.2 or higher

PHP 7.0 or higher

Composer (required to install the mPDF library for PDF export)

Installation
Upload the plugin files to the /wp-content/plugins/ directory, or install the plugin through the WordPress 'Plugins' screen directly.

Navigate to the plugin's directory via your server's command line (e.g., using SSH).

Run composer install in the plugin's root directory (it-asset-manager-lite-mvc/) to install the required mPDF library. This step is crucial for the PDF export feature to work.

Activate the plugin through the 'Plugins' screen in WordPress.

Upon activation, the custom post type and taxonomy will be registered, and rewrite rules will be flushed.

Usage
Add New Asset: Go to Assets > Add New in the WordPress admin menu.

Fill in the details in the "Asset Details" meta box. Required fields are marked with a red asterisk.

Select an "Asset Category" from the taxonomy meta box (usually on the right sidebar).

Use the "Asset Image" meta box to upload an image for the asset.

The "Asset History" meta box will display a log of changes after the asset is saved.

Use the "Asset Notes & Attachments" meta box to add notes and attach files. Click "Add Attachment(s)" to use the media uploader, then click "Add Note" to save the note and attachments via AJAX.

View Assets: Go to Assets > All Assets to see a list of your assets. You can use the search box, category filter, and brand filter to find specific assets.

Asset Dashboard: Go to Assets > Dashboard to view charts summarizing your asset data.

Export Assets: Go to Assets > Export to PDF to generate and download a PDF list of all your assets.

Changelog
1.9.1 (YYYY-MM-DD)

Fixed an issue where newly added notes and attachments were not immediately visible without a page refresh by updating the client-side JavaScript to correctly append the new note HTML via AJAX.

Improved the admin list table search functionality to use posts_search, posts_join, and posts_distinct filters for more reliable searching across custom meta fields, assigned users, and categories.

1.9.0 (Initial Release)

Initial release of the IT Asset Manager Lite plugin.

Introduced 'asset' custom post type and 'asset_category' taxonomy.

Implemented custom meta fields for asset details.

Added Asset Image and Asset History meta boxes.

Developed custom admin columns, filters, and basic search.

Included Asset Dashboard and PDF Export features.

Added auto-incrementing title functionality.

Development
The plugin follows a basic MVC-like structure with classes for different concerns (Loader, Post Types, Meta Fields, Admin, Callbacks, Assets).

Uses WordPress hooks extensively.

Includes security measures like nonces and capability checks.

Integrates the mPDF library via Composer for PDF generation.

Uses Chart.js for dashboard visualizations.

Includes AJAX for adding notes without page reloads.

Contributing
If you find issues or have suggestions, please consider contributing. (Note: As this is a "Lite" version, major feature additions might be limited, but bug fixes and minor enhancements are welcome).

License
This plugin is licensed under the GPL v2 or later.