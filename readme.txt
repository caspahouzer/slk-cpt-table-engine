=== SLK CPT Table Engine ===
Contributors: Sebastian Klaus
Tags: custom post type, cpt, database, performance, custom table
Requires at least: 6.7
Requires PHP: 8.2
Tested up to: 6.8
Stable tag: 1.1.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Optimizes database performance by storing Custom Post Types in dedicated custom tables instead of `wp_posts` and `wp_postmeta`.

== Description ==

Optimizes database performance by storing Custom Post Types in dedicated custom tables instead of `wp_posts` and `wp_postmeta`.

**Features**

*   **Custom Table Storage**: Store selected CPTs in dedicated database tables
*   **Bidirectional Migration**: Safely migrate content between storage modes
*   **WP_Query Compatible**: All standard WordPress functions continue to work
*   **Batch Processing**: Efficient migration of large datasets
*   **Object Caching**: Integrated caching for optimal performance
*   **Admin Interface**: Easy-to-use settings page with toggle switches
*   **Real-time Progress**: Live migration status updates
*   **Data Integrity**: Maintains ID relationships and foreign keys
*   **Performance Optimized**: Indexed columns, prepared statements, bulk operations

== Installation ==

1.  Upload the `slk-cpt-table-engine` folder to `/wp-content/plugins/`
2.  Activate the plugin through the 'Plugins' menu in WordPress
3.  Navigate to Settings → CPT Table Engine
4.  Toggle custom table storage for your desired post types

== Screenshots ==

1. The main settings page for the CPT Table Engine.

== How It Works ==

**Enabling Custom Tables**

When you enable custom table storage for a CPT:

1.  Plugin creates two tables: `{prefix}cpt_{post_type}` and `{prefix}cpt_{post_type}_meta`
2.  All existing posts are migrated from `wp_posts` to the custom table
3.  All post meta is migrated from `wp_postmeta` to the custom meta table
4.  Future CRUD operations automatically use the custom tables
5.  WP_Query is transparently redirected to custom tables

**Disabling Custom Tables**

When you disable custom table storage:

1.  Confirmation dialog appears to prevent accidental data loss
2.  All posts are migrated back to `wp_posts`
3.  All meta is migrated back to `wp_postmeta`
4.  Custom tables can optionally be dropped during uninstall

**Performance Benefits**

*   **Faster Queries**: Smaller, focused tables improve query performance
*   **Better Indexing**: Optimized indexes for specific post types
*   **Reduced Table Bloat**: Separate tables prevent wp_posts from becoming too large
*   **Scalability**: Handle 100k+ posts per post type efficiently

== Troubleshooting ==

**Migration Failed**

Check the WordPress debug log for detailed error messages:
`define( 'WP_DEBUG', true );`
`define( 'WP_DEBUG_LOG', true );`

**WP_Query Not Working**

Ensure the post type is properly registered before the plugin initializes. Custom post types should be registered on the `init` hook with priority < 10.

**Performance Issues**

*   Increase batch size using the filter
*   Ensure your database server has adequate resources
*   Check for slow queries in the MySQL slow query log

== Uninstallation ==

When you uninstall the plugin:

1.  All custom CPT tables are dropped
2.  All plugin options are deleted
3.  All transients are cleaned up
4.  Object cache is flushed

**Note**: Data in custom tables will be lost unless you migrate back to wp_posts before uninstalling.

== Changelog ==

= 1.1.0 - 2025-11-27 =
* Enhancement: Refactored the entire plugin to use the `SLK\Cpt_Table_Engine` namespace for better code organization and to align with modern PHP standards.
* Enhancement: Added internationalization support. The plugin is now translation-ready and includes a `.pot` file. A German translation (`de_DE`) is also included.
* Fix: Resolved several fatal errors related to incorrect namespace usage and class loading.

= 1.0.0 =
- Initial release
- Custom table storage for CPTs
- Bidirectional migration
- WP_Query integration
- Admin interface
- Batch processing
- Object caching
