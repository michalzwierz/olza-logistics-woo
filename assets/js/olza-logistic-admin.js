(function ($) {

    /*
     * Responsible for admin custom js
     */

    jQuery(document).ready(function () {

        function olzaGetResponseMessage(response) {
            if (!response) {
                return '';
            }

            if (typeof response.message === 'string' && response.message.length) {
                return response.message;
            }

            if (response.data && typeof response.data.message === 'string') {
                return response.data.message;
            }

            return '';
        }

        jQuery('.olzrepeater').repeaterolz({
            initEmpty: false,
            show: function () {
                jQuery(this).slideDown("slow", function () { });
            },
            hide: function (deleteElement) {
                jQuery(this).slideUp(deleteElement);
            }
        });

        jQuery(document).on('click', '#olza-refresh', function (e) {

            var olz_obj = jQuery(this);

            e.preventDefault();
            var refresh_flag = olza_global_admin.confirm_msg;
            if (confirm(refresh_flag) === true) {

                jQuery('.olza-admin-spinner').show();

                olz_obj.prop('disabled', true);

                var olza_data = {
                    nonce: olza_global_admin.nonce,
                    action: 'olza_get_pickup_point_files'
                };
                $.ajax({
                    type: 'POST',
                    data: olza_data,
                    dataType: 'json',
                    url: olza_global_admin.ajax_url,
                    crossDomain: true,
                    cache: false,
                    async: true,
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', olza_global_admin.nonce);
                    },
                }).done(function (response) {
                    olz_obj.prop('disabled', false);
                    jQuery('.olza-admin-spinner').hide();
                    var message = olzaGetResponseMessage(response);

                    if (!message && response && response.success === false) {
                        message = olza_global_admin.genericError || '';
                    }

                    if (!message && response && response.success) {
                        message = olza_global_admin.refreshSuccess || '';
                    }

                    if (!message) {
                        message = olza_global_admin.genericError || '';
                    }

                    alert(message);
                }).fail(function () {
                    olz_obj.prop('disabled', false);
                    jQuery('.olza-admin-spinner').hide();
                    alert(olza_global_admin.genericError || '');
                });

            } else {
                return false;
            }

        });

        var countryContainer = $('#olza-country-selector');
        var providerContainer = $('#olza-provider-selector');
        var messageContainer = $('.olza-country-messages');
        var adminData = window.olza_global_admin || {};

        if (countryContainer.length && providerContainer.length) {
            var selectedCountries = Array.isArray(adminData.selectedCountries) ? adminData.selectedCountries.slice() : [];
            var selectedProviders = $.extend(true, {}, (adminData.selectedProviders && typeof adminData.selectedProviders === 'object') ? adminData.selectedProviders : {});
            var fallbackCountries = Array.isArray(adminData.fallbackCountries) ? adminData.fallbackCountries : [];
            var countriesAction = adminData.countriesAction || 'olza_get_available_options';

            var requestData = {
                nonce: adminData.nonce,
                action: countriesAction
            };

            $.ajax({
                type: 'POST',
                url: adminData.ajax_url,
                dataType: 'json',
                data: requestData,
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', adminData.nonce);
                }
            }).done(function (response) {
                var message = olzaGetResponseMessage(response);

                if (message && messageContainer.length) {
                    messageContainer.text(message);
                }

                if (response && response.success && response.data && Array.isArray(response.data.countries)) {
                    renderCountryOptions(response.data.countries);
                } else if (fallbackCountries.length) {
                    renderCountryOptions(fallbackCountries);
                } else {
                    renderCountryOptions([]);
                }
            }).fail(function () {
                if (messageContainer.length && adminData.genericError) {
                    messageContainer.text(adminData.genericError);
                }

                if (fallbackCountries.length) {
                    renderCountryOptions(fallbackCountries);
                } else {
                    renderCountryOptions([]);
                }
            });

            function renderCountryOptions(countries) {
                countryContainer.empty();
                providerContainer.empty();

                if (!Array.isArray(countries) || !countries.length) {
                    if (adminData.noCountriesMessage) {
                        countryContainer.append($('<p/>', { text: adminData.noCountriesMessage }));
                    }
                    return;
                }

                countries.forEach(function (country) {
                    var code = country.code || '';
                    var label = country.label || code;

                    if (!code) {
                        return;
                    }

                    var checkboxId = 'olza-country-' + code;
                    var countryLabel = $('<label/>', { 'for': checkboxId });
                    var countryCheckbox = $('<input/>', {
                        type: 'checkbox',
                        id: checkboxId,
                        value: code,
                        name: 'olza_options[selected_countries][]'
                    });

                    if (selectedCountries.indexOf(code) !== -1) {
                        countryCheckbox.prop('checked', true);
                    }

                    countryLabel.append(countryCheckbox);
                    countryLabel.append(document.createTextNode(' ' + label));

                    countryContainer.append(countryLabel);

                    var providerGroup = $('<div/>', {
                        'class': 'olza-provider-group',
                        'data-country': code
                    });

                    providerGroup.append($('<input/>', {
                        type: 'hidden',
                        name: 'olza_options[selected_providers][' + code + '][]',
                        value: ''
                    }));

                    providerGroup.append($('<h4/>').text(label));

                    var providerList = $('<div/>', { 'class': 'olza-provider-options' });
                    var providers = Array.isArray(country.providers) ? country.providers : [];

                    if (!providers.length) {
                        if (adminData.noProvidersMessage) {
                            providerList.append($('<p/>', { text: adminData.noProvidersMessage }));
                        }
                    } else {
                        providers.forEach(function (provider) {
                            var providerCode = provider.code || '';
                            var providerLabelText = provider.label || providerCode;

                            if (!providerCode) {
                                return;
                            }

                            var providerId = 'olza-provider-' + code + '-' + providerCode;
                            var providerLabel = $('<label/>', { 'for': providerId });
                            var providerCheckbox = $('<input/>', {
                                type: 'checkbox',
                                id: providerId,
                                value: providerCode,
                                name: 'olza_options[selected_providers][' + code + '][]'
                            });

                            if (selectedProviders[code] && selectedProviders[code].indexOf(providerCode) !== -1) {
                                providerCheckbox.prop('checked', true);
                            }

                            providerLabel.append(providerCheckbox);
                            providerLabel.append(document.createTextNode(' ' + providerLabelText));
                            providerList.append(providerLabel);
                        });
                    }

                    providerGroup.append(providerList);
                    providerContainer.append(providerGroup);
                });

                toggleProviderGroups();
            }

            function toggleProviderGroups() {
                providerContainer.find('.olza-provider-group').each(function () {
                    var group = $(this);
                    var country = group.data('country');
                    var isChecked = countryContainer.find('input[type="checkbox"][value="' + country + '"]').is(':checked');

                    group.toggleClass('is-disabled', !isChecked);
                    group.find('input[type="checkbox"]').prop('disabled', !isChecked);
                });
            }

            countryContainer.on('change', 'input[type="checkbox"]', function () {
                toggleProviderGroups();
            });

            providerContainer.on('change', 'input[type="checkbox"]', function () {
                var group = $(this).closest('.olza-provider-group');
                var country = group.data('country');
                var checked = group.find('input[type="checkbox"]:checked').map(function () {
                    return $(this).val();
                }).get();

                selectedProviders[country] = checked;
            });
        }

    });

})(jQuery);
