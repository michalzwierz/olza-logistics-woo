<?php
/**
 * Get Pickup Points files
 */

add_action('wp_ajax_olza_get_pickup_point_files', 'olza_get_pickup_point_files_callback');

function olza_get_pickup_point_files_callback()
{
    global $olza_options;
    $olza_options = get_option('olza_options');

    if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'olza_load_files')) {

        // Restrict the countries array to only 'cz'
        $country_arr = array('cz');

        $message = __('Files Not Updated', 'olza-logistic-woo');

        foreach ($country_arr as $country) {

            $api_url = isset($olza_options['api_url']) && !empty($olza_options['api_url']) ? $olza_options['api_url'] : '';
            $access_token = isset($olza_options['access_token']) && !empty($olza_options['access_token']) ? $olza_options['access_token'] : '';

            if (empty($api_url) || empty($access_token)) {

                echo json_encode(
                    array(
                        'success' => false,
                        'message' => __('Please verify APP URL & Access Token.', 'olza-logistic-woo')
                    )
                );
                wp_die();
            }

            $api_endpoint = olza_validate_url($api_url . '/config');

            $config_api_url = add_query_arg(array(
                'access_token' => $access_token,
                'country' => $country,
            ), $api_endpoint);

            $args = array(
                'timeout'   => 30, // Timeout in seconds
                'headers'   => array(
                    'Content-Type'  => 'application/json'
                )
            );

            $config_response = wp_remote_get($config_api_url, $args);

            if (is_wp_error($config_response)) {

                $error_message = $config_response->get_error_message();
                echo json_encode(array('success' => false, 'message' => $error_message));
                wp_die();
            } else {


                $country_data = wp_remote_retrieve_body($config_response);

                /**
                 * Updating country files
                 */
                $file_path = OLZA_LOGISTIC_PLUGIN_PATH . 'data/' . $country . '.json'; // Adjust the file name as needed
                file_put_contents($file_path, $country_data);

                $message = __('Countries Added Successfully', 'olza-logistic-woo');

                /**
                 * Getting Spedition data
                 */
                $country_data_arr = json_decode($country_data)->data;

                $spedition_arr = array();
                if (!empty($country_data_arr) && !empty($country_data_arr->speditions)) {
                    // Add only ppl-ps and wedo-box providers
                    foreach ($country_data_arr->speditions as $speditions_obj) {
                        if (in_array($speditions_obj->code, array('ppl-ps', 'wedo-box'))) {
                            $spedition_arr[] = $speditions_obj->code;
                        }
                    }
                }

                if (!empty($spedition_arr)) {

                    $find_api_endpoint = olza_validate_url($api_url . '/find');

                    foreach ($spedition_arr as $sped_value) {

                        $find_api_url = add_query_arg(array(
                            'access_token' => $access_token,
                            'country' => $country,
                            'spedition' => $sped_value,
                        ), $find_api_endpoint);

                        $find_args = array(
                            'timeout'   => 300, // Timeout in seconds
                            'headers'   => array(
                                'Content-Type'  => 'application/json'
                            )
                        );

                        $find_response = wp_remote_get($find_api_url, $find_args);

                        if (is_wp_error($find_response)) {

                            $error_message = $find_response->get_error_message();
                            echo json_encode(array('success' => true, 'message' => $error_message));
                            wp_die();
                        } else {

                            $find_data = wp_remote_retrieve_body($find_response);

                            /**
                             * Updating country files
                             */

                            $sped_file_name = $country . '_' . $sped_value;

                            $file_path = OLZA_LOGISTIC_PLUGIN_PATH . 'data/' . $sped_file_name . '.json'; // Adjust the file name as needed
                            file_put_contents($file_path, $find_data);

                            $message .= 'Speditions ' . $sped_value . ' added successfully \n';
                        }
                    }
                }
            }
        }

        echo json_encode(array('success' => true, 'message' => $message));
        wp_die();
    } else {
        echo json_encode(array('success' => false, 'message' => __('Security verification failed.', 'olza-logistic-woo')));
        wp_die();
    }
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