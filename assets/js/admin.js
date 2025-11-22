/**
 * Admin JavaScript for CPT Table Engine.
 *
 * @package CPT_Table_Engine
 */

(function ($) {
    'use strict';

    /**
     * CPT Table Engine Admin.
     */
    const CPTTableEngine = {
        /**
         * Interval for funny messages.
         */
        messageInterval: null,

        /**
         * Initialize.
         */
        init: function () {
            this.bindEvents();
        },

        /**
         * Bind events.
         */
        bindEvents: function () {
            $('.cpt-toggle-checkbox').on('change', this.handleToggle.bind(this));
        },

        /**
         * Handle toggle switch change.
         *
         * @param {Event} e The event object.
         */
        handleToggle: function (e) {
            const $checkbox = $(e.target);
            const postType = $checkbox.data('post-type');
            const enabled = $checkbox.is(':checked');
            const $row = $checkbox.closest('tr');

            // If disabling, show confirmation.
            if (!enabled) {
                if (!confirm(cptTableEngine.i18n.confirmDisable)) {
                    // User cancelled, revert checkbox.
                    $checkbox.prop('checked', true);
                    return;
                }
            }

            // Disable checkbox during migration.
            $checkbox.prop('disabled', true);

            // Show progress indicator.
            this.showProgress($row, cptTableEngine.i18n.migrating);

            // Perform AJAX request.
            $.ajax({
                url: cptTableEngine.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cpt_table_engine_toggle_cpt',
                    nonce: cptTableEngine.nonce,
                    post_type: postType,
                    enabled: enabled ? 'true' : 'false'
                },
                success: (response) => {
                    if (response.success) {
                        this.handleSuccess($row, $checkbox, enabled);
                    } else {
                        this.handleError($row, $checkbox, enabled, response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    this.handleError($row, $checkbox, enabled, cptTableEngine.i18n.error);
                }
            });
        },

        /**
         * Show progress indicator.
         *
         * @param {jQuery} $row The table row.
         * @param {string} message The progress message.
         */
        showProgress: function ($row, message) {
            $row.find('.cpt-status').hide();
            $row.find('.cpt-progress').show();
            $row.find('.cpt-progress-text').text(message);

            // Start showing funny messages.
            this.messageInterval = setInterval(() => {
                const messages = cptTableEngine.i18n.funnyMessages;
                const randomIndex = Math.floor(Math.random() * messages.length);
                $row.find('.cpt-progress-text').text(messages[randomIndex]);
            }, 10000);
        },

        /**
         * Hide progress indicator.
         *
         * @param {jQuery} $row The table row.
         */
        hideProgress: function ($row) {
            // Clear the message interval.
            if (this.messageInterval) {
                clearInterval(this.messageInterval);
                this.messageInterval = null;
            }

            $row.find('.cpt-progress').hide();
            $row.find('.cpt-status').show();
        },

        /**
         * Handle successful migration.
         *
         * @param {jQuery} $row The table row.
         * @param {jQuery} $checkbox The checkbox element.
         * @param {boolean} enabled Whether custom table is enabled.
         */
        handleSuccess: function ($row, $checkbox, enabled) {
            // Re-enable checkbox.
            $checkbox.prop('disabled', false);

            // Hide progress.
            this.hideProgress($row);

            // Update status text.
            const $status = $row.find('.cpt-status');
            if (enabled) {
                $status.html(
                    '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ' +
                    cptTableEngine.i18n.usingCustomTable
                );
            } else {
                $status.html(
                    '<span class="dashicons dashicons-minus" style="color: #999;"></span> ' +
                    cptTableEngine.i18n.usingWpPosts
                );
            }

            // Show success notice.
            this.showNotice('success', cptTableEngine.i18n.success);
        },

        /**
         * Handle migration error.
         *
         * @param {jQuery} $row The table row.
         * @param {jQuery} $checkbox The checkbox element.
         * @param {boolean} enabled Whether custom table was being enabled.
         * @param {string} message The error message.
         */
        handleError: function ($row, $checkbox, enabled, message) {
            // Re-enable checkbox and revert state.
            $checkbox.prop('disabled', false);
            $checkbox.prop('checked', !enabled);

            // Hide progress.
            this.hideProgress($row);

            // Show error notice.
            this.showNotice('error', message || cptTableEngine.i18n.error);
        },

        /**
         * Show admin notice.
         *
         * @param {string} type The notice type ('success' or 'error').
         * @param {string} message The notice message.
         */
        showNotice: function (type, message) {
            const $notice = $('<div>')
                .addClass('notice notice-' + type + ' is-dismissible')
                .html('<p>' + message + '</p>');

            $('.wrap h1').after($notice);

            // Auto-dismiss after 5 seconds.
            setTimeout(function () {
                $notice.fadeOut(function () {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize on document ready.
    $(document).ready(function () {
        CPTTableEngine.init();
    });

})(jQuery);
