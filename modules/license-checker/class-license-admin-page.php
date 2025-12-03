<?php

/**
 * License Admin Page renderer.
 *
 * Handles admin interface rendering and form processing.
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
 * License Admin Page class.
 * 
 * Renders the license management interface and processes form submissions.
 */
class License_Admin_Page
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
    public function __construct() {}

    /**
     * Render the admin page.
     *
     * @return void
     */
    public function render(): void
    {
        // Check user capabilities.
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'slk-cpt-table-engine'));
        }

        // Get license data.
        $manager = License_Checker::instance();
        $license_key = $manager->get_license_key();
        $activation_hash = $manager->get_activation_hash();
        $license_status = $manager->get_license_status();
        $license_counts = $manager->get_license_counts();

        // Load the view template.
        require_once __DIR__ . '/views/admin-license-page.php';
    }
}
