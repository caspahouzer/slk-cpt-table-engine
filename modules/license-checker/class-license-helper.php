<?php

/**
 * License Helper for License Manager for WooCommerce API integration.
 *
 * Handles HTTP communication with the License Manager REST API.
 *
 * @package SLK\License_Checker
 */

declare(strict_types=1);

namespace SLK\License_Checker;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * License Helper class.
 * 
 * Provides methods for communicating with the License Manager for WooCommerce REST API.
 */
class License_Helper
{
    /**
     * API base URL.
     */
    private const API_BASE_URL = 'https://slk-communications.de/';

    /**
     * Log debug messages.
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
            $log_message .= ' | Data: ' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log($log_message);
    }

    /**
     * Make an API request.
     *
     * @param string $endpoint The API endpoint.
     * @param array  $body     The request body.
     * @return array Response array with 'success', 'data', and 'message' keys.
     */
    private static function make_request(string $endpoint, array $body = []): array
    {
        $url = self::API_BASE_URL . 'wp-json/slk-license-manager/v1/' . ltrim($endpoint, '/');

        self::log('Making API request', ['url' => $url, 'body' => $body]);

        $args = [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => json_encode($body),
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            self::log('API request failed with WP_Error', $response->get_error_message());
            return [
                'success' => false,
                'data'    => null,
                'message' => $response->get_error_message(),
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        self::log('API response received', ['status_code' => $response_code, 'body_length' => strlen($response_body)]);

        $data = json_decode($response_body, true);

        if ($response_code < 200 || $response_code >= 300) {
            $error_message = isset($data['message'])
                ? sanitize_text_field($data['message'])
                : sprintf(__('API request failed with status code: %d', 'slk-cpt-table-engine'), $response_code);

            self::log('API returned error status', ['code' => $response_code, 'message' => $error_message, 'data' => $data]);

            return [
                'success' => false,
                'data'    => $data,
                'message' => $error_message,
            ];
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            self::log('JSON decode failed', ['error' => json_last_error_msg(), 'body' => $response_body]);
            return [
                'success' => false,
                'data'    => null,
                'message' => __('Failed to parse API response.', 'slk-cpt-table-engine'),
            ];
        }

        self::log('API request successful', ['data' => $data]);

        return $data;
    }

    public static function delete_license_data(): void
    {
        delete_option(License_Checker::OPTION_LICENSE_KEY);
        delete_option(License_Checker::OPTION_ACTIVATION_HASH);
        delete_option(License_Checker::OPTION_LICENSE_STATUS);
        delete_option(License_Checker::OPTION_LICENSE_COUNTS);
        delete_transient(License_Checker::TRANSIENT_LICENSE_VALIDATION);

        self::log('License data deleted');
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

        $license_key = sanitize_text_field($license_key);

        if (empty($license_key)) {
            self::log('Activation failed: empty license key');
            return [
                'success' => false,
                'data'    => null,
                'message' => __('License key is required.', 'slk-cpt-table-engine'),
            ];
        }

        $body = [
            'license_key' => $license_key,
            'domain'      => home_url(),
        ];

        $result = self::make_request('activate', $body);

        self::log('Activate license result', $result);
        return $result;
    }

    /**
     * Deactivate a license.
     *
     * @param string $license_key      The license key.
     * @param string $activation_hash The activation hash.
     * @return array Response array.
     */
    public static function deactivate_license(string $license_key, string $activation_hash): array
    {
        self::log('Deactivate license called', [
            'license_key_length' => strlen($license_key),
            'hash_length'       => strlen($activation_hash)
        ]);

        $license_key = sanitize_text_field($license_key);
        $activation_hash = sanitize_text_field($activation_hash);

        if (empty($license_key) || empty($activation_hash)) {
            self::log('Deactivation failed: empty license key or hash');
            return [
                'success' => false,
                'data'    => null,
                'message' => __('License key and activation hash are required.', 'slk-cpt-table-engine'),
            ];
        }

        $body = [
            'license_key'     => $license_key,
            'domain'          => home_url(),
            'activation_hash' => $activation_hash,
        ];

        $result = self::make_request('deactivate', $body);

        self::log('Deactivate license result', $result);
        return $result;
    }

    /**
     * Validate a license.
     *
     * @param string $license_key      The license key to validate.
     * @param string $activation_hash The activation hash.
     * @return array Response array.
     */
    public static function validate_license(string $license_key, string $activation_hash): array
    {
        self::log('Validate license called', ['license_key_length' => strlen($license_key)]);

        $license_key = sanitize_text_field($license_key);

        if (empty($license_key)) {
            self::log('Validation failed: empty license key');
            return [
                'success' => false,
                'data'    => null,
                'message' => __('License key is required.', 'slk-cpt-table-engine'),
            ];
        }

        $body = [
            'license_key'     => $license_key,
            'domain'          => home_url(),
            'activation_hash' => $activation_hash,
        ];

        $result = self::make_request('validate', $body);

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

        $license_key = sanitize_text_field($license_key);

        if (empty($license_key)) {
            self::log('Get license details failed: empty license key');
            return [
                'success' => false,
                'data'    => null,
                'message' => __('License key is required.', 'slk-cpt-table-engine'),
            ];
        }

        $body = [
            'license_key' => $license_key,
        ];

        $result = self::make_request('details', $body);

        self::log('Get license details result', $result);
        return $result;
    }
}
