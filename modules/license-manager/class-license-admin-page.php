<?php

/**
 * License Admin Page renderer.
 *
 * Handles admin interface rendering and form processing.
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
 * License Admin Page class.
 * 
 * Renders the license management interface and processes form submissions.
 */
final class License_Admin_Page
{
    /**
     * Nonce action name.
     */
    public const NONCE_ACTION = 'slk_license_action';

    /**
     * Nonce field name.
     */
    public const NONCE_FIELD = 'slk_license_nonce';

    /**
     * Admin notice message.
     *
     * @var string
     */
    private string $notice = '';

    /**
     * Admin notice type.
     *
     * @var string
     */
    private string $notice_type = 'info';

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Process form submission.
        $this->process_form();
    }

    /**
     * Process form submission.
     *
     * @return void
     */
    private function process_form(): void
    {
        // Check if form was submitted.
        if (!isset($_POST[self::NONCE_FIELD])) {
            return;
        }

        // Verify nonce.
        if (!wp_verify_nonce($_POST[self::NONCE_FIELD], self::NONCE_ACTION)) {
            $this->set_notice(__('Security check failed. Please try again.', 'cpt-table-engine'), 'error');
            return;
        }

        // Check user capabilities.
        if (!current_user_can('manage_options')) {
            $this->set_notice(__('You do not have permission to perform this action.', 'cpt-table-engine'), 'error');
            return;
        }

        // Get the action.
        $action = isset($_POST['license_action']) ? sanitize_text_field($_POST['license_action']) : '';

        // Get license manager instance.
        $manager = License_Manager::instance();

        // Process based on action.
        switch ($action) {
            case 'activate':
                $this->handle_activate($manager);
                break;

            case 'deactivate':
                $this->handle_deactivate($manager);
                break;

            case 'validate':
                $this->handle_validate($manager);
                break;

            default:
                $this->set_notice(__('Invalid action.', 'cpt-table-engine'), 'error');
        }
    }

    /**
     * Handle license activation.
     *
     * @param License_Manager $manager License manager instance.
     * @return void
     */
    private function handle_activate(License_Manager $manager): void
    {
        $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';

        if (empty($license_key)) {
            $this->set_notice(__('Please enter a license key.', 'cpt-table-engine'), 'error');
            return;
        }

        $response = $manager->activate_license($license_key);

        if ($response['success']) {
            $this->set_notice(__('License activated successfully!', 'cpt-table-engine'), 'success');
        } else {
            $this->set_notice(
                sprintf(__('Activation failed: %s', 'cpt-table-engine'), $response['message']),
                'error'
            );
        }
    }

    /**
     * Handle license deactivation.
     *
     * @param License_Manager $manager License manager instance.
     * @return void
     */
    private function handle_deactivate(License_Manager $manager): void
    {
        $activation_token = $manager->get_activation_token();

        // If no activation token, try using the license key as fallback.
        if (empty($activation_token)) {
            $license_key = $manager->get_license_key();

            if (empty($license_key)) {
                $this->set_notice(__('No license information found. Please activate a license first.', 'cpt-table-engine'), 'error');
                return;
            }

            // Use license key for deactivation.
            $activation_token = $license_key;
        }

        $response = $manager->deactivate_license($activation_token);

        if ($response['success']) {
            $this->set_notice(__('License deactivated successfully!', 'cpt-table-engine'), 'success');
        } else {
            $this->set_notice(
                sprintf(__('Deactivation failed: %s', 'cpt-table-engine'), $response['message']),
                'error'
            );
        }
    }

    /**
     * Handle license validation.
     *
     * @param License_Manager $manager License manager instance.
     * @return void
     */
    private function handle_validate(License_Manager $manager): void
    {
        $license_key = $manager->get_license_key();

        if (empty($license_key)) {
            $this->set_notice(__('No license key stored. Please activate a license first.', 'cpt-table-engine'), 'error');
            return;
        }

        $response = $manager->validate_license($license_key);

        if ($response['success']) {
            $is_valid = isset($response['data']['valid']) && $response['data']['valid'];
            $message = $is_valid
                ? __('License is valid and active!', 'cpt-table-engine')
                : __('License validation failed. The license may be invalid or expired.', 'cpt-table-engine');
            $this->set_notice($message, $is_valid ? 'success' : 'warning');
        } else {
            $this->set_notice(
                sprintf(__('Validation failed: %s', 'cpt-table-engine'), $response['message']),
                'error'
            );
        }
    }

    /**
     * Set admin notice.
     *
     * @param string $message Notice message.
     * @param string $type    Notice type (success, error, warning, info).
     * @return void
     */
    private function set_notice(string $message, string $type = 'info'): void
    {
        $this->notice = $message;
        $this->notice_type = $type;
    }

    /**
     * Render the license management page.
     *
     * @return void
     */
    public function render(): void
    {
        // Check user capabilities.
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'cpt-table-engine'));
        }

        // Get license data.
        $manager = License_Manager::instance();
        $license_key = $manager->get_license_key();
        $activation_token = $manager->get_activation_token();
        $license_status = $manager->get_license_status();

        // Load the view template.
        require_once __DIR__ . '/views/admin-license-page.php';
    }
}
