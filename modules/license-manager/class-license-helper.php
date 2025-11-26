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
class License_Helper
{
    /**
     * API base URL.
     */
    private const API_BASE_URL = 'https://slk-communications.de/';

    /**
     * A secret key for decrypting the credentials fetched from the mu-plugin.
     * IMPORTANT: This exact same key must be used in the mu-plugin to encrypt the credentials.
     * We use the WordPress AUTH_KEY salt for this, which is unique to each installation.
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
     * @param string $endpoint The API endpoint (e.g., 'v2/licenses/activate/{key}').
     * @param array  $params   Optional URL parameters.
     * @return array Response array with 'success', 'data', and 'message' keys.
     */
    private static function make_request(string $endpoint, array $params = [], bool $with_auth = true): array
    {
        // Build URL.
        $url = self::build_url($endpoint, $params);

        self::log('Making API request', ['url' => $url, 'params' => $params, 'with_auth' => $with_auth]);

        // Prepare request arguments.
        $args = [
            'timeout' => 30,
        ];

        if ($with_auth) {
            // Get credentials securely.
            $credentials = self::get_credentials();
            self::log('Fetched API credentials', is_wp_error($credentials) ? $credentials->get_error_message() : ['key_length' => strlen($credentials['key'])]);
            if (is_wp_error($credentials)) {
                return [
                    'success' => false,
                    'data'    => null,
                    'message' => $credentials->get_error_message(),
                ];
            }

            $args['headers'] = [
                'Authorization' => 'Basic ' . base64_encode($credentials['key'] . ':' . $credentials['secret']),
            ];
        }

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
            if (isset($data['errors']) && is_array($data['errors'])) {
                if (isset($data['errors']['lmfwc_rest_data_error'])) {
                    $data['message'] = isset($data['errors']['lmfwc_rest_data_error'][0])
                        ? sanitize_text_field($data['errors']['lmfwc_rest_data_error'][0])
                        : __('API returned an error.', 'slk-cpt-table-engine');
                    self::log('API returned specific lmfwc_rest_data_error', $data['errors']['lmfwc_rest_data_error']);
                }
            }

            $error_message = isset($data['message'])
                ? sanitize_text_field($data['message'])
                :
                /* translators: %d: HTTP status code */
                sprintf(__('API request failed with status code: %d', 'slk-cpt-table-engine'), $response_code);

            self::log('API returned error status', ['code' => $response_code, 'message' => $error_message, 'data' => $data]);

            // Delete license data on error.
            if ($data['code'] === 'lmfwc_rest_license_not_found') {
                self::log('License not found on server, deleting local license data to resync.', ['code' => $data['code']]);
                self::delete_license_data();
            }

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
                'message' => __('Failed to parse API response.', 'slk-cpt-table-engine'),
            ];
        }

        self::log('API request successful', ['data' => $data]);

        return [
            'success' => true,
            'data'    => $data,
            'message' => isset($data['message']) ? sanitize_text_field($data['message']) : __('Request successful.', 'slk-cpt-table-engine'),
        ];
    }

    public static function delete_license_data(): void
    {
        delete_option(License_Manager::OPTION_LICENSE_KEY);
        delete_option(License_Manager::OPTION_ACTIVATION_TOKEN);
        delete_option(License_Manager::OPTION_LICENSE_STATUS);
        delete_option(License_Manager::OPTION_LICENSE_COUNTS);
        delete_transient(License_Manager::TRANSIENT_LICENSE_VALIDATION);

        self::log('License data deleted');
    }

    /**
     * Get API credentials from the secure mu-plugin endpoint.
     * Caches the result in a transient for performance.
     *
     * @return array|WP_Error An array with 'key' and 'secret' on success, or WP_Error on failure.
     */
    private static function get_credentials()
    {
        $transient_key = 'slk_license_credentials_' . SLK_LICENSE_MANAGER_VERSION;
        $cached_credentials = get_transient($transient_key);

        self::log('Fetched cached credentials', ['cached' => $cached_credentials !== false]);

        if (false !== $cached_credentials && is_array($cached_credentials)) {
            return $cached_credentials;
        }

        // --- Fetch from the remote credential provider endpoint ---
        $token = md5(gmdate('Y-m-d'));
        $endpoint = 'wp-json/slk/v1/lic';
        $params = ['token' => $token];

        $result = self::make_request($endpoint, $params, false); // Important: false to prevent recursion

        if (!$result['success']) {
            return new \WP_Error('credential_request_failed', 'Could not connect to the credential provider.', $result['message']);
        }

        $credentials = $result['data'];

        if (!is_array($credentials) || !isset($credentials['key']) || !isset($credentials['secret'])) {
            return new \WP_Error('invalid_credential_response', 'Invalid response from the credential provider.');
        }


        // Cache for 12 hours.
        set_transient($transient_key, $credentials, 12 * HOUR_IN_SECONDS);

        return $credentials;
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
                'message' => __('License key is required.', 'slk-cpt-table-engine'),
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
     * @param string $license_key      The license key.
     * @param string $activation_token The activation token.
     * @return array Response array.
     */
    public static function deactivate_license(string $license_key, string $activation_token): array
    {
        self::log('Deactivate license called', [
            'license_key_length' => strlen($license_key),
            'token_length'       => strlen($activation_token)
        ]);

        // Sanitize input.
        $license_key = sanitize_text_field($license_key);
        $activation_token = sanitize_text_field($activation_token);

        if (empty($license_key) || empty($activation_token)) {
            self::log('Deactivation failed: empty license key or token');
            return [
                'success' => false,
                'data'    => null,
                'message' => __('License key and activation token are required.', 'slk-cpt-table-engine'),
            ];
        }

        // Make API request.
        // Endpoint: /licenses/deactivate/{license_key}?token={token}
        $endpoint = 'wp-json/lmfwc/v2/licenses/deactivate/' . urlencode($license_key);
        $params = ['token' => $activation_token];

        $result = self::make_request($endpoint, $params);

        self::log('Processing deactivate license result', $result);

        // Handle the case where the token is already invalid on the server.
        // If the API says the token is not found, it's effectively deactivated.
        if (
            ! $result['success'] &&
            isset($result['message']) &&
            strpos($result['message'], 'could not be found or is deactivated') !== false
        ) {
            self::log('Deactivation token was not found on the server. Treating as a successful deactivation locally.', $result);
            $result['success'] = true;
            $result['message'] = __('The license is already inactive on the server.', 'slk-cpt-table-engine');
        }

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
                'message' => __('License key is required.', 'slk-cpt-table-engine'),
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
                'message' => __('License key is required.', 'slk-cpt-table-engine'),
            ];
        }

        // Make API request.
        $endpoint = 'wp-json/lmfwc/v2/licenses/' . urlencode($license_key);
        $result = self::make_request($endpoint);

        self::log('Get license details result', $result);
        return $result;
    }
}
