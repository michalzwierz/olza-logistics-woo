<?php

/**
 * Adding Olza Shipping Setting Section
 */

add_filter('woocommerce_get_sections_shipping', 'add_custom_shipping_link', 10);

function add_custom_shipping_link($sections)
{

    $sections['olza_settings'] = __('Olza Logistic', 'olza-logistic-zoo');
    return $sections;
}

/**
 * Adding Shipping Setting Fields
 */

add_filter('woocommerce_get_settings_shipping', 'olza_logistic_get_settings', 10, 1);


function olza_logistic_get_settings($settings)
{

    global $current_section;

    if ($current_section == 'olza_settings') {

        $settings = array(

            array(
                'title' => __('Olza API Settings', 'olza-logistic-woo'),
                'type' => 'title',
                'desc' =>  __('Manage your API settings for the Pickup Points.', 'olza-logistic-woo'),
                'id' => 'olza_logistic_woocommerce_settings'
            ),
            array(
                'title' => __('API URL', 'olza-logistic-woo'),
                'type' => 'text',
                'desc' => __('Add your olza logistic API URL.', 'olza-logistic-woo'),
                'desc_tip' => true,
                'id' => 'olza_options[api_url]',
                'css' => 'min-width:300px;',
            ),
            array(
                'title' => __('API Access Token', 'olza-logistic-woo'),
                'type' => 'text',
                'desc' => __('Add your olza logistic API access token.', 'olza-logistic-woo'),
                'desc_tip' => true,
                'id' => 'olza_options[access_token]',
                'css' => 'min-width:300px;',
            ),
            array(
                'title' => __('Map API key', 'olza-logistic-woo'),
                'type' => 'text',
                'desc' => __('Add your Mapbox API key.', 'olza-logistic-woo'),
                'desc_tip' => true,
                'id' => 'olza_options[mapbox_api]',
                'css' => 'min-width:300px;',
            ),
            array(
                'name' => __('Sync Olza Logistic Data', 'olza-logistic-woo'),
                'type' => 'button',
                'desc' => __('Click this button to refresh your olza logistic API data files.', 'olza-logistic-woo'),
                'desc_tip' => true,
                'class' => 'button-secondary',
                'id'    => 'olza-refresh',
            ),
            array(
                'name' => __('Set Pickup Pricing', 'olza-logistic-woo'),
                'type' => 'repeater',
                'desc' => __('Add fee according to the basket amount low to high.', 'olza-logistic-woo'),
                'desc_tip' => true,
                'id'    => 'olza_options[basket_fee]',
                'key_val'    => 'basket_fee',

            ),
            array(
                'type' => 'sectionend',
                'id' => 'olza_logistic_woocommerce_settings'
            ),
        );
    }

    return $settings;
}