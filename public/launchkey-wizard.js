// For the settings/wizard page
(function ($, config) {
    
    var nonce = config.nonce,
    // Controls the views based on url hash
    changeView = function () {
        var view = window.location.hash.substring(1).toString();
        
        // Hide/show proper views
        $('[data-view]').hide();
        $('[data-view="'+view+'"]').show();

        // If a wizard view show/hide
        // wizard header using css class
        if (view.indexOf('wizard') > -1) {
            $('body').addClass('lk-wizard');
        } else {
            $('body').removeClass('lk-wizard')
        }

        // Hide/show WL specific elements
        if (launchkey_wizard_config.implementation_type == 'white-label') {
            $('.lk-white-label-only').show();
            $('.lk-standard-only').hide();
        } else {
            $('.lk-white-label-only').hide();
            $('.lk-standard-only').show();
        }
        // Scroll back to top
        window.scrollTo(0, 0)
    },
    showErrors = function (errors) {
        $.each(errors, function(idx, val) {
            var template = $('#lk-error-template').html();
            template = template.replace('%%%', val);
            $('.launchkey-header').append(template);
        });
        $('.notice-dismiss').on('click', function() {
            $(this).parent().remove();
        });
        window.scrollTo(0, 0)
    },
    showNotice = function (notice) {
        var template = $('#lk-notice-template').html();
        template = template.replace('%%%', notice);
        $('.launchkey-header').append(template);
        $('.notice-dismiss').on('click', function() {
            $(this).parent().remove();
        });
        window.scrollTo(0, 0)
    };

    // Test to see if configured or not
    // Hide/show settings or wizard on load
    if (window.location.hash.substring(1).length > 1) {
        changeView();
    } else if (config.is_configured == '1') {
        window.location.hash = '#settings';
    } else {
        window.location.hash = '#wizard-1';
    }
    
    // Event listener to change view based on hash
    $(window).on('hashchange', changeView)

    // Accordion
    $('[data-accordion]').on('click', function (e) {
        e.preventDefault();
        var content = $(this).attr('data-accordion');
        $('[data-accordion-content="' + content + '"]').toggle();
        if ($(this).attr('data-accordion-hide-trigger')) {
            var hide = $(this).attr('data-accordion-hide-trigger');
            $('[data-accordion-hide="' + hide + '"]').toggle();
        }
    });

    // Submit Keys
    $('#lk-wizard-keys-form, #lk-settings-keys-form').on('submit', function (e) {
        e.preventDefault();
        var $self = $(this);
        var formData = new FormData(this);
        formData.append('nonce', nonce);
        formData.append('implementation_type', launchkey_wizard_config.implementation_type);
        if (launchkey_wizard_config.implementation_type == 'white-label') {
            var appDisplayName = $(this).closest('[data-view]').find('[name="app_display_name"]').val();
            formData.append('app_display_name', appDisplayName);
        } else {
            formData.append('app_display_name', 'LaunchKey'); // Default for standard
        }
        $.ajax({
            url: config.url,
            type: 'POST',
            processData: false,
            contentType: false,
            data: formData,
            success: function (data) {
                nonce = data.nonce;
                $('.launchkey-spinner-processing').removeClass('launchkey-spinner-processing');
                if (data.errors) {
                    showErrors(data.errors)
                } else {
                    if ($self.attr('id') == 'lk-wizard-keys-form') {
                        window.location.hash = '#wizard-8';
                    } else {
                        showNotice('Settings saved');
                    }
                }
            },
            error: function (data) {
                showErrors(['Plugin Error.'])
            }
        });
    });

    // Submit SSO ID Profile XML File
    $('#lk-wizard-sso-idp-form').on('submit', function (e) {
        e.preventDefault();
        var $self = $(this);
        var formData = new FormData(this);
        formData.append('nonce', nonce);
        formData.append('implementation_type', launchkey_wizard_config.implementation_type);
        formData.append('app_display_name', 'LaunchKey'); // Default for standard
        $.ajax({
            url: config.url,
            type: 'POST',
            processData: false,
            contentType: false,
            data: formData,
            success: function (data) {
                nonce = data.nonce;
                $('.launchkey-spinner-processing').removeClass('launchkey-spinner-processing');
                if (data.errors) {
                    showErrors(data.errors)
                } else {
                    if ($self.attr('id') == 'lk-wizard-sso-idp-form') {
                        window.location.hash = '#wizard-sso-8';
                    } else {
                        showNotice('Settings saved');
                    }
                }
            },
            error: function (data) {
                showErrors(['Plugin Error.'])
            }
        });
    });

    // Event listener for form submit triggers
    $('[data-launchkey-submit]').on('click', function (e) {
        e.preventDefault();
        $(this).find('.launchkey-spinner').addClass('launchkey-spinner-processing');
        var formID = $(this).attr('data-launchkey-submit');
        $('#' + formID + '').submit();
    });

    // Fouc
    $('.lk-fouc').removeClass('lk-fouc');

    $('.hide').hide();

}(jQuery, launchkey_wizard_config));
