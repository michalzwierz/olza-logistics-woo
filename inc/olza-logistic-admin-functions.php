<?php
/**
 * Helper utilities for the Olza admin area.
 */

if (!function_exists('olza_logistic_get_default_country_provider_selection')) {
    /**
     * Default configuration used for backward compatibility.
     *
     * @return array
     */
    function olza_logistic_get_default_country_provider_selection()
    {
        return array(
            'countries' => array('cz'),
            'providers' => array(
                'cz' => array('ppl-ps', 'wedo-box'),
            ),
        );
    }
}

if (!function_exists('olza_logistic_build_country_payload')) {
    /**
     * Prepare a simplified country/provider payload for the admin UI.
     *
     * @param array $countries      List of country codes.
     * @param array $providers_map  Providers keyed by country.
     *
     * @return array
     */
    function olza_logistic_build_country_payload($countries, $providers_map = array())
    {
        $payload = array();

        if (!is_array($countries)) {
            $countries = array();
        }

        foreach ($countries as $country_code) {
            $country_code = sanitize_key($country_code);

            if (empty($country_code)) {
                continue;
            }

            $provider_entries = array();

            if (isset($providers_map[$country_code]) && is_array($providers_map[$country_code])) {
                foreach ($providers_map[$country_code] as $provider_code) {
                    $provider_code = sanitize_key($provider_code);

                    if (empty($provider_code)) {
                        continue;
                    }

                    $provider_entries[] = array(
                        'code'  => $provider_code,
                        'label' => ucwords(str_replace('-', ' ', $provider_code)),
                    );
                }
            }

            $payload[] = array(
                'code'      => $country_code,
                'label'     => strtoupper($country_code),
                'providers' => $provider_entries,
            );
        }

        return $payload;
    }
}

if (!function_exists('olza_logistic_parse_countries_response')) {
    /**
     * Parse the response from the countries endpoint.
     *
     * @param array $response Response body decoded as array.
     *
     * @return array|WP_Error Associative array of country_code => label or WP_Error on failure.
     */
    function olza_logistic_parse_countries_response($response)
    {
        $countries = array();

        if (!is_array($response)) {
            return $countries;
        }

        $data = isset($response['data']) ? $response['data'] : $response;

        if (isset($data['status']) && isset($data['message']) && !isset($data['countries'])) {
            $status = sanitize_key($data['status']);
            $message = sanitize_text_field($data['message']);

            if (empty($message)) {
                $message = __('Unexpected response from the Olza API.', 'olza-logistic-woo');
            }

            return new WP_Error(
                'olza_logistic_countries_api_error',
                $message,
                array('status' => $status)
            );
        }

        if (isset($data['countries'])) {
            $data = $data['countries'];
        }

        if (!is_array($data)) {
            return $countries;
        }

        foreach ($data as $key => $country_info) {
            $country_code = '';
            $country_label = '';

            if (is_array($country_info)) {
                $country_code = !empty($country_info['code']) ? sanitize_key($country_info['code']) : sanitize_key($key);
                $country_label = !empty($country_info['name']) ? sanitize_text_field($country_info['name']) : '';

                if (empty($country_label) && !empty($country_info['title'])) {
                    $country_label = sanitize_text_field($country_info['title']);
                }
            } elseif (is_object($country_info)) {
                $country_info = (array) $country_info;
                $country_code = !empty($country_info['code']) ? sanitize_key($country_info['code']) : sanitize_key($key);
                $country_label = !empty($country_info['name']) ? sanitize_text_field($country_info['name']) : '';
            } elseif (is_string($country_info)) {
                $country_code = !is_numeric($key) ? sanitize_key($key) : sanitize_key($country_info);
                $country_label = sanitize_text_field($country_info);
            }

            if (empty($country_code)) {
                continue;
            }

            if (empty($country_label)) {
                $country_label = strtoupper($country_code);
            }

            $countries[$country_code] = $country_label;
        }

        return $countries;
    }
}

if (!function_exists('olza_logistic_parse_providers_response')) {
    /**
     * Parse the provider list from the config endpoint response.
     *
     * @param array $response Response body decoded as array.
     *
     * @return array
     */
    function olza_logistic_parse_providers_response($response)
    {
        $providers = array();

        if (!is_array($response)) {
            return $providers;
        }

        $data = isset($response['data']) ? $response['data'] : $response;

        if (!isset($data['speditions'])) {
            return $providers;
        }

        $speditions = $data['speditions'];
        $speditions = is_object($speditions) ? (array) $speditions : $speditions;

        if (!is_array($speditions)) {
            return $providers;
        }

        foreach ($speditions as $key => $spedition) {
            $code = '';
            $label = '';

            if (is_array($spedition)) {
                $code = !empty($spedition['code']) ? sanitize_key($spedition['code']) : sanitize_key($key);
                $label = !empty($spedition['name']) ? sanitize_text_field($spedition['name']) : '';
            } elseif (is_object($spedition)) {
                $spedition = (array) $spedition;
                $code = !empty($spedition['code']) ? sanitize_key($spedition['code']) : sanitize_key($key);
                $label = !empty($spedition['name']) ? sanitize_text_field($spedition['name']) : '';
            } elseif (is_string($spedition)) {
                $code = sanitize_key($spedition);
                $label = strtoupper($code);
            }

            if (empty($code)) {
                continue;
            }

            if (empty($label)) {
                $label = strtoupper($code);
            }

            $providers[] = array(
                'code'  => $code,
                'label' => $label,
            );
        }

        return $providers;
    }
}

if (!function_exists('olza_logistic_fetch_country_provider_data')) {
    /**
     * Retrieve the list of countries and providers from the Olza API.
     *
     * @param string $api_url      Base API URL.
     * @param string $access_token Access token.
     *
     * @return array
     */
    function olza_logistic_fetch_country_provider_data($api_url, $access_token)
    {
        $api_url = untrailingslashit($api_url);

        $args = array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
        );

        $countries_endpoint = olza_validate_url($api_url . '/countries');
        $countries_payload = array();

        if ($countries_endpoint) {
            $countries_response = wp_remote_get(
                add_query_arg(
                    array(
                        'access_token' => $access_token,
                    ),
                    $countries_endpoint
                ),
                $args
            );

            if (!is_wp_error($countries_response)) {
                $countries_body = json_decode(wp_remote_retrieve_body($countries_response), true);

                if (is_array($countries_body)) {
                    $countries_payload = olza_logistic_parse_countries_response($countries_body);

                    if (is_wp_error($countries_payload)) {
                        return $countries_payload;
                    }
                }
            }
        }

        if (empty($countries_payload)) {
            return array();
        }

        $config_endpoint = olza_validate_url($api_url . '/config');

        if (!$config_endpoint) {
            return array();
        }

        $country_output = array();

        foreach ($countries_payload as $country_code => $country_label) {
            $config_response = wp_remote_get(
                add_query_arg(
                    array(
                        'access_token' => $access_token,
                        'country'      => $country_code,
                    ),
                    $config_endpoint
                ),
                $args
            );

            $providers = array();

            if (!is_wp_error($config_response)) {
                $config_body = json_decode(wp_remote_retrieve_body($config_response), true);

                if (is_array($config_body)) {
                    $providers = olza_logistic_parse_providers_response($config_body);
                }
            }

            $country_output[] = array(
                'code'      => $country_code,
                'label'     => $country_label,
                'providers' => $providers,
            );
        }

        return $country_output;
    }
}

/**
 * Get Pickup Points files
 */

add_action('wp_ajax_olza_get_pickup_point_files', 'olza_get_pickup_point_files_callback');
add_action('wp_ajax_olza_get_available_options', 'olza_get_available_options_callback');

/**
 * Provide a list of available countries and providers from the API.
 */
function olza_get_available_options_callback()
{
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

    if (empty($nonce) || !wp_verify_nonce($nonce, 'olza_load_files')) {
        wp_send_json_error(array('message' => __('Security verification failed.', 'olza-logistic-woo')));
    }

    if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'olza-logistic-woo')));
    }

    $olza_options = get_option('olza_options', array());

    $api_url = isset($olza_options['api_url']) ? trim($olza_options['api_url']) : '';
    $access_token = isset($olza_options['access_token']) ? trim($olza_options['access_token']) : '';

    if (empty($api_url) || empty($access_token)) {
        wp_send_json_error(array('message' => __('Please verify APP URL & Access Token.', 'olza-logistic-woo')));
    }

    $country_payload = olza_logistic_fetch_country_provider_data($api_url, $access_token);

    if (is_wp_error($country_payload)) {
        $error_message = sanitize_text_field($country_payload->get_error_message());

        if (empty($error_message)) {
            $error_message = __('Unable to load available options from the Olza API.', 'olza-logistic-woo');
        }

        wp_send_json_error(array('message' => $error_message));
    }

    if (empty($country_payload)) {
        $defaults = olza_logistic_get_default_country_provider_selection();
        $fallback_payload = olza_logistic_build_country_payload($defaults['countries'], $defaults['providers']);

        wp_send_json_success(
            array(
                'countries' => $fallback_payload,
                'message'   => __('Unable to load available options from the Olza API. Showing default values instead.', 'olza-logistic-woo'),
                'source'    => 'fallback',
            )
        );
    }

    wp_send_json_success(
        array(
            'countries' => $country_payload,
            'source'    => 'api',
        )
    );
}

function olza_get_pickup_point_files_callback()
{
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

    if (empty($nonce) || !wp_verify_nonce($nonce, 'olza_load_files')) {
        wp_send_json_error(array('message' => __('Security verification failed.', 'olza-logistic-woo')));
    }

    if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'olza-logistic-woo')));
    }

    $olza_options = get_option('olza_options', array());

    $api_url = isset($olza_options['api_url']) ? trim($olza_options['api_url']) : '';
    $access_token = isset($olza_options['access_token']) ? trim($olza_options['access_token']) : '';

    if (empty($api_url) || empty($access_token)) {
        wp_send_json_error(array('message' => __('Please verify APP URL & Access Token.', 'olza-logistic-woo')));
    }

    $defaults = olza_logistic_get_default_country_provider_selection();

    $use_default_countries = !array_key_exists('selected_countries', $olza_options);
    $use_default_providers = !array_key_exists('selected_providers', $olza_options);

    $country_arr = $use_default_countries ? $defaults['countries'] : (array) $olza_options['selected_countries'];
    $country_arr = array_values(array_unique(array_filter(array_map('sanitize_key', $country_arr))));

    if (empty($country_arr)) {
        wp_send_json_error(array('message' => __('Please select at least one country before refreshing the data.', 'olza-logistic-woo')));
    }

    $selected_providers_map = $use_default_providers ? $defaults['providers'] : (array) $olza_options['selected_providers'];
    $normalized_provider_map = array();

    foreach ($selected_providers_map as $country_code => $providers) {
        $country_code = sanitize_key($country_code);

        if (empty($country_code)) {
            continue;
        }

        $providers = is_array($providers) ? $providers : array();
        $normalized_provider_map[$country_code] = array_values(array_unique(array_filter(array_map('sanitize_key', $providers))));
    }

    $api_url = untrailingslashit($api_url);

    $messages = array();
    $errors = array();

    wp_mkdir_p(OLZA_LOGISTIC_PLUGIN_PATH . 'data');

    $config_endpoint = olza_validate_url($api_url . '/config');
    $find_endpoint = olza_validate_url($api_url . '/find');

    if (!$config_endpoint || !$find_endpoint) {
        wp_send_json_error(array('message' => __('Unable to determine the Olza API endpoints.', 'olza-logistic-woo')));
    }

    foreach ($country_arr as $country) {
        $config_api_url = add_query_arg(
            array(
                'access_token' => $access_token,
                'country'      => $country,
            ),
            $config_endpoint
        );

        $config_args = array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
        );

        $config_response = wp_remote_get($config_api_url, $config_args);

        if (is_wp_error($config_response)) {
            $errors[] = sprintf(__('Failed to retrieve configuration for %1$s: %2$s', 'olza-logistic-woo'), strtoupper($country), $config_response->get_error_message());
            continue;
        }

        $country_data = wp_remote_retrieve_body($config_response);

        if (empty($country_data)) {
            $errors[] = sprintf(__('Empty configuration response for %s.', 'olza-logistic-woo'), strtoupper($country));
            continue;
        }

        $file_path = OLZA_LOGISTIC_PLUGIN_PATH . 'data/' . $country . '.json';
        file_put_contents($file_path, $country_data);

        $messages[] = sprintf(__('Configuration for %s saved.', 'olza-logistic-woo'), strtoupper($country));

        $country_data_arr = json_decode($country_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = sprintf(__('Invalid configuration JSON for %s.', 'olza-logistic-woo'), strtoupper($country));
            continue;
        }

        $available_providers = olza_logistic_parse_providers_response($country_data_arr);
        $available_codes = wp_list_pluck($available_providers, 'code');

        $provider_codes = array();

        if (isset($normalized_provider_map[$country]) && !empty($normalized_provider_map[$country])) {
            $provider_codes = array_values(array_intersect($normalized_provider_map[$country], $available_codes));
        } elseif ($use_default_providers && isset($defaults['providers'][$country])) {
            $provider_codes = array_values(array_intersect($defaults['providers'][$country], $available_codes));
        } else {
            $provider_codes = $available_codes;
        }

        if (empty($provider_codes)) {
            $messages[] = sprintf(__('No providers selected for %s.', 'olza-logistic-woo'), strtoupper($country));
            continue;
        }

        foreach ($provider_codes as $sped_value) {
            $find_api_url = add_query_arg(
                array(
                    'access_token' => $access_token,
                    'country'      => $country,
                    'spedition'    => $sped_value,
                ),
                $find_endpoint
            );

            $find_args = array(
                'timeout' => 300,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
            );

            $find_response = wp_remote_get($find_api_url, $find_args);

            if (is_wp_error($find_response)) {
                $errors[] = sprintf(__('Failed to fetch pickup points for %1$s (%2$s): %3$s', 'olza-logistic-woo'), strtoupper($country), $sped_value, $find_response->get_error_message());
                continue;
            }

            $find_data = wp_remote_retrieve_body($find_response);

            if (empty($find_data)) {
                $errors[] = sprintf(__('Empty pickup point response for %1$s (%2$s).', 'olza-logistic-woo'), strtoupper($country), $sped_value);
                continue;
            }

            $sped_file_name = $country . '_' . $sped_value;
            $file_path = OLZA_LOGISTIC_PLUGIN_PATH . 'data/' . $sped_file_name . '.json';
            file_put_contents($file_path, $find_data);

            $messages[] = sprintf(__('Spedition %1$s for %2$s saved.', 'olza-logistic-woo'), $sped_value, strtoupper($country));
        }
    }

    $response_message = trim(implode("\n", array_filter($messages)));

    if (!empty($errors)) {
        $response_message = trim($response_message . "\n" . implode("\n", $errors));
    }

    if (empty($messages) && !empty($errors)) {
        wp_send_json_error(array('message' => $response_message));
    }

    wp_send_json_success(array('message' => $response_message));
}

/**
 * APP Url Validation
 */

if (!function_exists('olza_validate_url')) {
    function olza_validate_url($url)
    {

        if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
            return false;
        }

        if (strpos($url, '://') !== false) {
            list($protocol, $rest_of_url) = explode('://', $url, 2);

            $rest_of_url = str_replace('//', '/', $rest_of_url);

            return $protocol . '://' . $rest_of_url;
        } else {
            return str_replace('//', '/', $url);
        }
    }
}

/**
 * Custom admin fields for country/provider selection.
 */

add_action('woocommerce_admin_field_olza_country_selector', 'olza_woo_add_admin_field_country_selector');
add_action('woocommerce_admin_field_olza_provider_selector', 'olza_woo_add_admin_field_provider_selector');

function olza_woo_add_admin_field_country_selector($field)
{
    $description = WC_Admin_Settings::get_field_description($field);
    $option_value = WC_Admin_Settings::get_option($field['id'], array());
    $option_value = is_array($option_value) ? array_values($option_value) : array();

?>
    <style>
        .olza-checkbox-field label {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin: 0 12px 8px 0;
        }

        .olza-provider-group {
            border: 1px solid #dcdcde;
            padding: 12px;
            margin: 0 0 12px;
        }

        .olza-provider-group h4 {
            margin: 0 0 8px;
            font-size: 14px;
        }

        .olza-provider-group.is-disabled {
            opacity: 0.6;
        }

        .olza-provider-group.is-disabled input[type="checkbox"] {
            cursor: not-allowed;
        }

        .olza-country-messages {
            margin-bottom: 8px;
        }
    </style>
    <tr valign="top">
        <th scope="row" class="titledesc">
            <label for="olza-country-selector"><?php echo esc_html($field['title']); ?></label>
            <?php echo $description['tooltip_html']; ?>
        </th>
        <td class="forminp forminp-<?php echo esc_attr(sanitize_title($field['type'])); ?>">
            <input type="hidden" name="<?php echo esc_attr($field['id']); ?>" value="" />
            <div class="olza-country-messages"></div>
            <div id="olza-country-selector" class="olza-checkbox-field" data-selected='<?php echo esc_attr(wp_json_encode($option_value)); ?>'></div>
            <?php echo $description['description']; ?>
        </td>
    </tr>
<?php
}

function olza_woo_add_admin_field_provider_selector($field)
{
    $description = WC_Admin_Settings::get_field_description($field);
    $option_value = WC_Admin_Settings::get_option($field['id'], array());
    $option_value = is_array($option_value) ? $option_value : array();

?>
    <tr valign="top">
        <th scope="row" class="titledesc">
            <label for="olza-provider-selector"><?php echo esc_html($field['title']); ?></label>
            <?php echo $description['tooltip_html']; ?>
        </th>
        <td class="forminp forminp-<?php echo esc_attr(sanitize_title($field['type'])); ?>">
            <input type="hidden" name="<?php echo esc_attr($field['id']); ?>" value="" />
            <div id="olza-provider-selector" class="olza-checkbox-field" data-selected='<?php echo esc_attr(wp_json_encode($option_value)); ?>'></div>
            <?php echo $description['description']; ?>
        </td>
    </tr>
<?php
}

/**
 * Add woo button field
 */


add_action('woocommerce_admin_field_button', 'olza_woo_add_admin_field_button');

function olza_woo_add_admin_field_button($value)
{
    $option_value = (array) WC_Admin_Settings::get_option($value['id']);
    $description = WC_Admin_Settings::get_field_description($value);

?>
    <style>
        .olza-admin-spinner {
            display: none;
        }
    </style>
    <tr valign="top">
        <th scope="row" class="titledesc">
            <label for="<?php echo esc_attr($value['id']); ?>"><?php echo esc_html($value['title']); ?></label>
            <?php echo $description['tooltip_html']; ?>
        </th>
        <td class="olza-table olza-table-<?php echo sanitize_title($value['type']) ?>">
            <input name="<?php echo esc_attr($value['name']); ?>" id="<?php echo esc_attr($value['id']); ?>" type="submit" style="<?php echo esc_attr($value['css']); ?>" value="<?php echo esc_attr($value['name']); ?>" class="<?php echo esc_attr($value['class']); ?>" />
            <?php echo $description['description']; ?>
            <span class="olza-admin-spinner"><img src="<?php echo OLZA_LOGISTIC_PLUGIN_URL . 'assets/images/spinner.gif'; ?>" alt="<?php echo __('Spinner', 'olza-logistic-woo'); ?>" /></span>
        </td>
    </tr>
<?php
}

/**
 * Add woo button field
 */


add_action('woocommerce_admin_field_repeater', 'olza_woo_add_admin_field_repeater');

function olza_woo_add_admin_field_repeater($field)
{
    $option_value = (array) WC_Admin_Settings::get_option($field['id']);
    $description = WC_Admin_Settings::get_field_description($field);
    $olza_options = get_option('olza_options');

?>
    <style>
        .olza-rep-sett input[type="number"] {
            width: 20% !important;
            min-height: 30px !important;
        }

        .olza-rep-sett select {
            width: 30% !important;
        }

        .olza-rep-item {
            margin: 10px 0;
        }
    </style>
    <tr valign="top" class="olza-rep-sett">
        <th scope="row" class="titledesc">
            <label for="<?php echo esc_attr($field['id']); ?>"><?php echo esc_html($field['title']); ?></label>
            <?php echo $description['tooltip_html']; ?>
        </th>
        <td class="olza-table olza-table-<?php echo sanitize_title($field['type']) ?>">
            <?php
            if (isset($olza_options[$field['key_val']]) && !empty($olza_options[$field['key_val']]) && is_array($olza_options[$field['key_val']])) {
            ?>
                <div class="olzrepeater">
                    <div data-repeater-list="<?php echo esc_attr($field['id']); ?>">
                        <?php
                        foreach ($olza_options[$field['key_val']] as $key => $backet_data) {
                            $cond_val = isset($backet_data['condition']) ? $backet_data['condition'] : '';
                        ?>
                            <div data-repeater-item class="olza-rep-item">
                                <input type="number" placeholder="<?php echo __('Basket Amount', 'olza-logistic-woo'); ?>" name="<?php echo esc_attr($field['id']); ?>[<?php echo $key; ?>][amount]" value="<?php echo isset($backet_data['amount']) ? $backet_data['amount'] : ''; ?>" />
                                <select name="<?php echo esc_attr($field['id']); ?>[<?php echo $key; ?>][condition]">
                                    <option value="equal" <?php selected($cond_val, 'equal', true); ?>><?php echo __('Equal', 'olza-logistic-woo'); ?></option>
                                    <option value="less" <?php selected($cond_val, 'less', true); ?>><?php echo __('Less', 'olza-logistic-woo'); ?></option>
                                    <option value="less_than_equal" <?php selected($cond_val, 'less_than_equal', true); ?>><?php echo __('Less than Equal', 'olza-logistic-woo'); ?></option>
                                    <option value="greater" <?php selected($cond_val, 'greater', true); ?>><?php echo __('Greater', 'olza-logistic-woo'); ?></option>
                                    <option value="greater_than_equal" <?php selected($cond_val, 'greater_than_equal', true); ?>><?php echo __('Greater than Equal', 'olza-logistic-woo'); ?></option>
                                </select>
                                <input type="number" placeholder="<?php echo __('Fee', 'olza-logistic-woo'); ?>" name="<?php echo esc_attr($field['id']); ?>[<?php echo $key; ?>][fee]" value="<?php echo isset($backet_data['fee']) ? $backet_data['fee'] : ''; ?>" />
                                <input data-repeater-delete type="button" value="<?php echo __('Delete', 'olza-logistic-woo'); ?>" class="button-secondary" />
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                    <input data-repeater-create type="button" value="<?php echo __('Add', 'olza-logistic-woo'); ?>" class="button-secondary" />
                </div>
            <?php
            } else {
            ?>
                <div class="olzrepeater">
                    <div data-repeater-list="<?php echo esc_attr($field['id']); ?>">
                        <div data-repeater-item>
                            <input type="number" name="amount" value="" placeholder="<?php echo __('Amount', 'olza-logistic-woo'); ?>" />
                            <select name="condition">
                                <option value="equal"><?php echo __('Equal', 'olza-logistic-woo'); ?></option>
                                <option value="less"><?php echo __('Less', 'olza-logistic-woo'); ?></option>
                                <option value="less_than_equal"><?php echo __('Less than Equal', 'olza-logistic-woo'); ?></option>
                                <option value="greater"><?php echo __('Greater', 'olza-logistic-woo'); ?></option>
                                <option value="greater_than_equal"><?php echo __('Greater than Equal', 'olza-logistic-woo'); ?></option>
                            </select>
                            <input type="number" name="fee" value="" placeholder="<?php echo __('Fee', 'olza-logistic-woo'); ?>" />
                            <input data-repeater-delete type="button" value="Delete" />
                        </div>
                    </div>
                    <input data-repeater-create type="button" value="Add" />
                </div>

            <?php
            }
            ?>
        </td>
    </tr>
<?php
}

add_filter('woocommerce_admin_settings_sanitize_option', 'olza_logistic_sanitize_admin_option', 10, 3);

function olza_logistic_sanitize_admin_option($value, $option, $raw_value)
{
    if (empty($option['id']) || strpos($option['id'], 'olza_options[') !== 0) {
        return $value;
    }

    if ('olza_options[selected_countries]' === $option['id']) {
        $sanitized = array();

        if (is_array($raw_value)) {
            foreach ($raw_value as $country_code) {
                $country_code = sanitize_key($country_code);

                if (!empty($country_code)) {
                    $sanitized[] = $country_code;
                }
            }
        }

        return array_values(array_unique($sanitized));
    }

    if ('olza_options[selected_providers]' === $option['id']) {
        $sanitized = array();

        if (is_array($raw_value)) {
            foreach ($raw_value as $country => $providers) {
                $country_code = sanitize_key($country);

                if (empty($country_code)) {
                    continue;
                }

                if (!is_array($providers)) {
                    $providers = array();
                }

                $provider_codes = array();

                foreach ($providers as $provider) {
                    $provider_code = sanitize_key($provider);

                    if (!empty($provider_code)) {
                        $provider_codes[] = $provider_code;
                    }
                }

                $sanitized[$country_code] = array_values(array_unique($provider_codes));
            }
        }

        return $sanitized;
    }

    return $value;
}