# License Manager Module

Integrates License Manager for WooCommerce REST API with CPT Table Engine plugin.

## Namespace
`SLK\License_Manager`

## Classes

- **License_Manager** - Main singleton controller
- **License_Helper** - API HTTP client
- **License_Admin_Page** - Admin interface renderer

## Features

- License activation/deactivation/validation
- **Automatic validation every 12 hours** (transient-based)
- REST API integration with Basic Auth
- WordPress admin UI integration
- Secure form handling with nonces
- Data persistence using WordPress Options API

## API Configuration

- **Base URL:** `https://slk-communications.de/`
- **Endpoints:**
  - `GET v2/licenses/activate/{license_key}`
  - `GET v2/licenses/deactivate/{activation_token}`
  - `GET v2/licenses/validate/{license_key}`

## Usage

Access via Settings → CPT Table Engine → License tab in WordPress admin.

## Auto-Loading

Classes are automatically loaded via the enhanced PSR-4 autoloader in the main plugin file. No manual `require` statements needed.
