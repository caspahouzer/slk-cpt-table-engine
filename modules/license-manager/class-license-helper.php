<?php

/**
 * License Helper for License Manager for WooCommerce API integration.
 *
 * Handles HTTP communication with the License Manager REST API.
 *
 * @package SLK\License_Manager
 */

declare(strict_types=1);

namespace SLK\License_Manager;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * License Helper class.
 * 
 * Provides methods for communicating with the License Manager for WooCommerce REST API.
 */
final class License_Helper
{
    /**
     * API base URL.
     */
    private const API_BASE_URL = 'https://slk-communications.de/';

    /**
     * API consumer key.
     */
    private const CONSUMER_KEY = 'ck_683ce90cb4f537d47d293a8afbcb089309ee5853';

    /**
     * API consumer secret.
     */
    private const CONSUMER_SECRET = 'cs_296a62cfb54ed59d0670e8c37248e1ec222cf5c0';

    /**
     * Log debug messages when SLK_DEBUG is enabled.
     *
     * @param string $message Log message.
     * @param mixed  $data    Optional data to log.
     * @return void
     */
    private static function log(string $message, $data = null): void
    {
        if (!defined('SLK_DEBUG') || !SLK_DEBUG) {
            return;
        }

        $log_message = '[SLK License Helper] ' . $message;

        if ($data !== null) {
            $log_message .= ' | Data: ' . print_r($data, true);
        }

        error_log($log_message);
    }

    /**
     * Make an API request.
     *
     * @param string $endpoint The API endpoint (e.g., 'v2/licenses/activate/{key}').
     * @param array  $params   Optional URL parameters.
     * @return array Response array with 'success', 'data', and 'message' keys.
     */
    private static function make_request(string $endpoint, array $params = []): array
    {
        // Build URL.
        $url = self::build_url($endpoint, $params);

        self::log('Making API request', ['url' => $url, 'params' => $params]);

        // Prepare request arguments.
        $args = [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(self::CONSUMER_KEY . ':' . self::CONSUMER_SECRET),
            ],
            'timeout' => 30,
        ];

        // Make request.
        $response = wp_remote_get($url, $args);

        // Check for WP_Error.
        if (is_wp_error($response)) {
            self::log('API request failed with WP_Error', $response->get_error_message());
            return [
                'success' => false,
                'data'    => null,
                'message' => $response->get_error_message(),
            ];
        }

        // Get response code and body.
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        self::log('API response received', ['status_code' => $response_code, 'body_length' => strlen($response_body)]);

        // Decode JSON response.
        $data = json_decode($response_body, true);

        // Handle non-2xx responses.
        if ($response_code < 200 || $response_code >= 300) {
            $error_message = isset($data['message'])
                ? sanitize_text_field($data['message'])
                : sprintf(__('API request failed with status code: %d', 'cpt-table-engine'), $response_code);

            self::log('API returned error status', ['code' => $response_code, 'message' => $error_message, 'data' => $data]);

            return [
                'success' => false,
                'data'    => $data,
                'message' => $error_message,
            ];
        }

        // Handle JSON decode errors.
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::log('JSON decode failed', ['error' => json_last_error_msg(), 'body' => $response_body]);
            return [
                'success' => false,
                'data'    => null,
                'message' => __('Failed to parse API response.', 'cpt-table-engine'),
            ];
        }

        self::log('API request successful', ['data' => $data]);

        return [
            'success' => true,
            'data'    => $data,
            'message' => isset($data['message']) ? sanitize_text_field($data['message']) : __('Request successful.', 'cpt-table-engine'),
        ];
    }

    /**
     * Build API URL with endpoint and parameters.
     *
     * @param string $endpoint The API endpoint.
     * @param array  $params   Optional URL parameters.
     * @return string Complete URL.
     */
    private static function build_url(string $endpoint, array $params = []): string
    {
        // Remove leading slash from endpoint.
        $endpoint = ltrim($endpoint, '/');

        // Build base URL.
        $url = trailingslashit(self::API_BASE_URL) . $endpoint;

        // Add query parameters if provided.
        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        return $url;
    }

    /**
     * Activate a license.
     *
     * @param string $license_key The license key to activate.
     * @return array Response array.
     */
    public static function activate_license(string $license_key): array
    {
        self::log('Activate license called', ['license_key_length' => strlen($license_key)]);

        // Sanitize input.
        $license_key = sanitize_text_field($license_key);

        if (empty($license_key)) {
            self::log('Activation failed: empty license key');
            return [
                'success' => false,
                'data'    => null,
                'message' => __('License key is required.', 'cpt-table-engine'),
            ];
        }

        // Make API request.
        $endpoint = 'wp-json/lmfwc/v2/licenses/activate/' . urlencode($license_key);
        $result = self::make_request($endpoint);

        self::log('Activate license result', $result);
        return $result;
    }

    /**
     * Deactivate a license.
     *
     * @param string $activation_token The activation token.
     * @return array Response array.
     */
    public static function deactivate_license(string $activation_token): array
    {
        self::log('Deactivate license called', ['token_length' => strlen($activation_token)]);

        // Sanitize input.
        $activation_token = sanitize_text_field($activation_token);

        if (empty($activation_token)) {
            self::log('Deactivation failed: empty activation token');
            return [
                'success' => false,
                'data'    => null,
                'message' => __('Activation token is required.', 'cpt-table-engine'),
            ];
        }

        // Make API request.
        $endpoint = 'wp-json/lmfwc/v2/licenses/deactivate/' . urlencode($activation_token);
        $result = self::make_request($endpoint);

        self::log('Deactivate license result', $result);
        return $result;
    }

    /**
     * Validate a license.
     *
     * @param string $license_key The license key to validate.
     * @return array Response array.
     */
    public static function validate_license(string $license_key): array
    {
        self::log('Validate license called', ['license_key_length' => strlen($license_key)]);

        // Sanitize input.
        $license_key = sanitize_text_field($license_key);

        if (empty($license_key)) {
            self::log('Validation failed: empty license key');
            return [
                'success' => false,
                'data'    => null,
                'message' => __('License key is required.', 'cpt-table-engine'),
            ];
        }

        // Make API request.
        $endpoint = 'wp-json/lmfwc/v2/licenses/validate/' . urlencode($license_key);
        $result = self::make_request($endpoint);

        self::log('Validate license result', $result);
        return $result;
    }

    /**
     * Get license details including activations.
     *
     * @param string $license_key The license key to retrieve details for.
     * @return array Response array.
     */
    public static function get_license_details(string $license_key): array
    {
        self::log('Get license details called', ['license_key_length' => strlen($license_key)]);

        // Sanitize input.
        $license_key = sanitize_text_field($license_key);

        if (empty($license_key)) {
            self::log('Get license details failed: empty license key');
            return [
                'success' => false,
                'data'    => null,
                'message' => __('License key is required.', 'cpt-table-engine'),
            ];
        }

        // Make API request.
        $endpoint = 'wp-json/lmfwc/v2/licenses/' . urlencode($license_key);
        $result = self::make_request($endpoint);

        self::log('Get license details result', $result);
        return $result;
    }
}
