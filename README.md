# SLK CPT Table Engine

Contributors: Sebastian Klaus
Tags: custom post type, cpt, database, performance, custom table
Requires at least: 6.7
Tested up to: 6.8
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Optimizes database performance by storing Custom Post Types in dedicated custom tables instead of `wp_posts` and `wp_postmeta`.

## Features

- **Custom Table Storage**: Store selected CPTs in dedicated database tables
- **Bidirectional Migration**: Safely migrate content between storage modes
- **WP_Query Compatible**: All standard WordPress functions continue to work
- **Batch Processing**: Efficient migration of large datasets
- **Object Caching**: Integrated caching for optimal performance
- **Admin Interface**: Easy-to-use settings page with toggle switches
- **Real-time Progress**: Live migration status updates
- **Data Integrity**: Maintains ID relationships and foreign keys
- **Performance Optimized**: Indexed columns, prepared statements, bulk operations

## Requirements

- WordPress 6.7+
- PHP 8.2+
- MySQL 5.7+ or MariaDB 10.3+

## Installation

1. Upload the `slk-cpt-table-engine` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Settings → CPT Table Engine
4. Toggle custom table storage for your desired post types

## How It Works

### Enabling Custom Tables

When you enable custom table storage for a CPT:

1. Plugin creates two tables: `{prefix}cpt_{post_type}` and `{prefix}cpt_{post_type}_meta`
2. All existing posts are migrated from `wp_posts` to the custom table
3. All post meta is migrated from `wp_postmeta` to the custom meta table
4. Future CRUD operations automatically use the custom tables
5. WP_Query is transparently redirected to custom tables

### Disabling Custom Tables

When you disable custom table storage:

1. Confirmation dialog appears to prevent accidental data loss
2. All posts are migrated back to `wp_posts`
3. All meta is migrated back to `wp_postmeta`
4. Custom tables can optionally be dropped during uninstall

### Performance Benefits

- **Faster Queries**: Smaller, focused tables improve query performance
- **Better Indexing**: Optimized indexes for specific post types
- **Reduced Table Bloat**: Separate tables prevent wp_posts from becoming too large
- **Scalability**: Handle 100k+ posts per post type efficiently

## Architecture

### Folder Structure

```
slk-cpt-table-engine/
├── includes/
│   ├── admin/              # Admin interface components
│   ├── database/           # Table schema and management
│   ├── migrations/         # Migration engine
│   ├── controllers/        # CRUD controllers
│   ├── integration/        # WP_Query and CRUD interceptors
│   ├── helpers/            # Utilities (cache, logger, sanitizer, validator)
│   └── bootstrap.php       # Plugin initialization
├── assets/
│   ├── css/               # Admin styles
│   └── js/                # Admin JavaScript
├── languages/             # Translation files
├── slk-cpt-table-engine.php   # Main plugin file
└── uninstall.php          # Uninstall handler
```

### Database Schema

**Main Table** (`{prefix}cpt_{post_type}`):
- All standard wp_posts columns
- Indexed on: ID, post_status, post_date, post_author, post_name, post_parent, menu_order

**Meta Table** (`{prefix}cpt_{post_type}_meta`):
- meta_id, post_id, meta_key, meta_value
- Indexed on: meta_id, post_id, meta_key, (post_id + meta_key)

## Developer Documentation

### Filters

**Modify migration batch size:**
```php
add_filter( 'cpt_table_engine_migration_batch_size', function( $batch_size ) {
    return 200; // Default is 100
} );
```

### Programmatic Usage

**Check if CPT uses custom table:**
```php
use SLK_Cpt_Table_Engine\Controllers\Settings_Controller;

if ( Settings_Controller::is_enabled( 'my_cpt' ) ) {
    // CPT uses custom table
}
```

**Manually trigger migration:**
```php
use SLK_Cpt_Table_Engine\Migrations\Migration_Manager;

// Migrate to custom table
$result = Migration_Manager::migrate_to_custom_table( 'my_cpt' );

// Migrate back to wp_posts
$result = Migration_Manager::migrate_to_wp_posts( 'my_cpt' );
```

## Coding Standards

- Follows WordPress Coding Standards
- PSR-4 autoloading
- Strict typing (PHP 8.2+)
- Comprehensive PHPDoc blocks
- Object-oriented architecture

## Security

- Capability checks on all admin actions
- Nonce validation on forms and AJAX
- Input sanitization using WordPress functions
- Output escaping
- Prepared SQL statements

## Performance

- Batch processing for migrations
- Object caching integration
- Indexed database columns
- Optimized SQL queries
- No SQL_CALC_FOUND_ROWS

## Compatibility

- Works with all standard WordPress functions
- Compatible with WP_Query
- Supports meta queries
- Works with popular page builders
- Multisite compatible (with network activation)

## Troubleshooting

### Migration Failed

Check the WordPress debug log for detailed error messages:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

### WP_Query Not Working

Ensure the post type is properly registered before the plugin initializes. Custom post types should be registered on the `init` hook with priority < 10.

### Performance Issues

- Increase batch size using the filter
- Ensure your database server has adequate resources
- Check for slow queries in the MySQL slow query log

## Uninstallation

When you uninstall the plugin:

1. All custom CPT tables are dropped
2. All plugin options are deleted
3. All transients are cleaned up
4. Object cache is flushed

**Note**: Data in custom tables will be lost unless you migrate back to wp_posts before uninstalling.

## Support

For issues, questions, or feature requests, please use the GitHub issue tracker.

## License

GPL v2 or later

## Credits

Developed by Sebastian Klaus

## Changelog

### 1.0.0
- Initial release
- Custom table storage for CPTs
- Bidirectional migration
- WP_Query integration
- Admin interface
- Batch processing
- Object caching
