jQuery(document).ready(function ($) {
    const $licenseForm = $('.cpt-license-form');
    const $licenseInput = $('#license_key');
    const $activateBtn = $('#slk-activate-btn');
    const $deactivateBtn = $('#slk-deactivate-btn');
    const $messageContainer = $('#slk-license-message');
    const $spinner = $('.slk-spinner');

    function showMessage(message, type) {
        $messageContainer.html('<div class="notice notice-' + type + ' inline"><p>' + message + '</p></div>').show();
    }

    function clearMessage() {
        $messageContainer.hide().empty();
    }

    function toggleLoading(isLoading) {
        if (isLoading) {
            $spinner.addClass('is-active');
            $activateBtn.prop('disabled', true);
            $deactivateBtn.prop('disabled', true);
        } else {
            $spinner.removeClass('is-active');
            $activateBtn.prop('disabled', false);
            $deactivateBtn.prop('disabled', false);
        }
    }

    // Handle Activation
    $activateBtn.on('click', function (e) {
        e.preventDefault();
        clearMessage();

        const licenseKey = $licenseInput.val().trim();
        if (!licenseKey) {
            showMessage(slk_license_vars.strings.enter_key, 'error');
            return;
        }

        toggleLoading(true);

        $.ajax({
            url: slk_license_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'slk_manage_license',
                security: slk_license_vars.nonce,
                method: 'activate',
                license_key: licenseKey,
                domain: slk_license_vars.domain
            },
            success: function (response) {
                toggleLoading(false);
                if (response.success) {
                    showMessage(response.data.message, 'success');

                    // Update UI
                    $licenseInput.val(response.data.masked_key).prop('readonly', true).prop('disabled', true).css('background-color', '#f0f0f1');
                    $activateBtn.hide();
                    $deactivateBtn.show();
                    $('.slk-license-status-text').text('active').removeClass('slk-status-inactive').addClass('slk-status-active');
                    $('.slk-license-status-icon').removeClass('dashicons-minus').addClass('dashicons-yes').css('color', 'green');

                    // Update description text
                    $('.description').text(slk_license_vars.strings.active_desc);

                    // Update usage and show row
                    if (response.data.usage) {
                        $('.slk-license-usage').text(response.data.usage);
                        $('.slk-activations-row').show();
                    }
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function (xhr, status, error) {
                toggleLoading(false);
                showMessage(slk_license_vars.strings.network_error, 'error');
            }
        });
    });

    // Handle Deactivation
    $deactivateBtn.on('click', function (e) {
        e.preventDefault();
        clearMessage();

        if (!confirm(slk_license_vars.strings.confirm_deactivate)) {
            return;
        }

        toggleLoading(true);

        $.ajax({
            url: slk_license_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'slk_manage_license',
                security: slk_license_vars.nonce,
                method: 'deactivate'
            },
            success: function (response) {
                toggleLoading(false);
                if (response.success) {
                    showMessage(response.data.message, 'success');

                    // Update UI
                    $licenseInput.val(response.data.license_key).prop('readonly', false).prop('disabled', false).css('background-color', '');
                    $deactivateBtn.hide();
                    $activateBtn.show();
                    $('.slk-license-status-text').text('inactive').removeClass('slk-status-active').addClass('slk-status-inactive');
                    $('.slk-license-status-icon').removeClass('dashicons-yes').addClass('dashicons-minus').css('color', '#999');

                    // Update description text
                    $('.description').text(slk_license_vars.strings.inactive_desc);

                    // Hide activations row
                    $('.slk-activations-row').hide();
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function (xhr, status, error) {
                toggleLoading(false);
                showMessage(slk_license_vars.strings.network_error, 'error');
            }
        });
    });

    // Check status on load if active
    if (slk_license_vars.status === 'active') {
        $.ajax({
            url: slk_license_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'slk_manage_license',
                security: slk_license_vars.nonce,
                method: 'check_status'
            },
            success: function (response) {
                if (response.success) {
                    // Update usage
                    if (response.data.usage) {
                        $('.slk-license-usage').text(response.data.usage);
                        $('.slk-activations-row').show();
                    }

                    // If status changed to inactive (e.g. revoked), update UI
                    if (response.data.status !== 'active') {
                        // Reset UI to inactive state
                        $licenseInput.val('').prop('readonly', false).prop('disabled', false).css('background-color', '');
                        $deactivateBtn.hide();
                        $activateBtn.show();
                        $('.slk-license-status-text').text('inactive').removeClass('slk-status-active').addClass('slk-status-inactive');
                        $('.slk-license-status-icon').removeClass('dashicons-yes').addClass('dashicons-minus').css('color', '#999');
                        $('.description').text(slk_license_vars.strings.inactive_desc);
                        $('.slk-activations-row').hide();

                        showMessage(slk_license_vars.strings.inactive_desc, 'warning');
                    }
                }
            }
        });
    }
});
