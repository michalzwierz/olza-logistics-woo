<?php

/**
 * Adding Additional Map Container checkout
 */

function change_woocommerce_field_markup($field, $key, $args, $value)
{
    $field = str_replace('form-row', '', $field);

    // Add pickup hidden fields with unique IDs
    if ($key === 'billing_country') {
        // Change the IDs here to be unique for each context
        $field .= '<input type="hidden" name="olza_pickup_option" id="olza_pickup_option" value="" />';
        $field .= '<input type="hidden" name="olza_pickup_option_wedobox" id="olza_pickup_option_wedobox" value="" />';
        $field .= '<input type="hidden" name="delivery_point_id" id="delivery_point_id" value="" />';
        $field .= '<input type="hidden" name="delivery_courier_id" id="delivery_courier_id" value="" />';
    }
    return $field;
}


add_filter("woocommerce_form_field", "change_woocommerce_field_markup", 10, 4);


/**
 * Validate pickup field
 */

add_action('woocommerce_checkout_process', 'olza_pickup_field_validation');

function olza_pickup_field_validation()

{

    $chosen_methods = WC()->session->get('chosen_shipping_methods');
    if (strpos($chosen_methods[0], 'olza_pickup') !== false) {
        if (!$_POST['olza_pickup_option']) wc_add_notice(__('Please select a pickup point.', 'olza-logistic-woo'), 'error');
    }
}

/**
 * Update Pickup Points
 */


add_action('woocommerce_checkout_update_order_meta', 'olza_logistic_update_pickup_point', 10, 2);

function olza_logistic_update_pickup_point($order_id, $data)
{
    $chosen_methods = WC()->session->get('chosen_shipping_methods');

    if (strpos($chosen_methods[0], 'olza_pickup') !== false) {
        // Check the pickup type from POST data
        $pickup_type = isset($_POST['pickup_type']) ? sanitize_text_field($_POST['pickup_type']) : '';

        // Save the pickup option based on the selected pickup type
        if (!empty($_POST['olza_pickup_option'])) {
            if ($pickup_type === 'ppl-ps') {
                update_post_meta($order_id, 'Pickup Point (PPL-PS)', sanitize_text_field($_POST['olza_pickup_option']));
            } elseif ($pickup_type === 'wedo-box') {
                update_post_meta($order_id, 'Pickup Point (WEDO-BOX)', sanitize_text_field($_POST['olza_pickup_option']));
            }
        }

        // Save additional data for delivery point and courier ID
        if (!empty($_POST['delivery_point_id'])) {
            update_post_meta($order_id, 'delivery_point_id', sanitize_text_field($_POST['delivery_point_id']));
        }
        if (!empty($_POST['delivery_courier_id'])) {
            update_post_meta($order_id, 'delivery_courier_id', sanitize_text_field($_POST['delivery_courier_id']));
        }
    }
}
add_action('woocommerce_checkout_create_order', 'olza_update_pickup_order_meta');
function olza_update_pickup_order_meta($order) {
    $chosen_methods = WC()->session->get('chosen_shipping_methods');

    // Check if the chosen shipping method is either olza_pickup or olza_pickup_wedobox
    if (isset($chosen_methods[0]) && (strpos($chosen_methods[0], 'olza_pickup') !== false || strpos($chosen_methods[0], 'olza_pickup_wedobox') !== false)) {

        // Add meta data to order if available
        if (isset($_POST['olza_pickup_option']) && !empty($_POST['olza_pickup_option'])) {
            $order->update_meta_data('Pickup Point', sanitize_text_field($_POST['olza_pickup_option']));
        }
        if (isset($_POST['delivery_point_id']) && !empty($_POST['delivery_point_id'])) {
            $order->update_meta_data('Delivery Point ID', sanitize_text_field($_POST['delivery_point_id']));
        }
        if (isset($_POST['delivery_courier_id']) && !empty($_POST['delivery_courier_id'])) {
            $order->update_meta_data('Delivery Courier', sanitize_text_field($_POST['delivery_courier_id']));
        }

        // Dynamically set shipping method title with country and courier ID
        $pickup_address = isset($_POST['olza_pickup_option']) ? sanitize_text_field($_POST['olza_pickup_option']) : '';
        $delivery_courier_id = isset($_POST['delivery_courier_id']) ? sanitize_text_field($_POST['delivery_courier_id']) : '';
        $country_name = isset($_POST['country_name']) && !empty($_POST['country_name']) ? sanitize_text_field($_POST['country_name']) : 'CZ';

        // Set new shipping title
        $new_shipping_title = $country_name . '-' . $delivery_courier_id;

        // Update the shipping method title in the order
        foreach ($order->get_items('shipping') as $item_id => $shipping_item) {
            if (strpos($shipping_item->get_method_id(), 'olza_pickup') !== false) {
                $shipping_item->set_name($new_shipping_title); // Set new title
                $shipping_item->save(); // Save the changes
            }
        }
    }
}

// add_action('woocommerce_checkout_create_order', 'olza_update_pickup_order_meta');
// function olza_update_pickup_order_meta($order) {

//     $chosen_methods = WC()->session->get('chosen_shipping_methods');
//     if (strpos($chosen_methods[0], 'olza_pickup') !== false) {

//         // Add meta data to order
//         if (isset($_POST['olza_pickup_option']) && !empty($_POST['olza_pickup_option'])) {
//             $order->update_meta_data('Pickup Point', $_POST['olza_pickup_option']);
//         }
//         if (isset($_POST['delivery_point_id']) && !empty($_POST['delivery_point_id'])) {
//             $order->update_meta_data('Delivery Point ID', $_POST['delivery_point_id']);
//         }
//         if (isset($_POST['delivery_courier_id']) && !empty($_POST['delivery_courier_id'])) {
//             $order->update_meta_data('Delivery Courier', $_POST['delivery_courier_id']);
//         }

//         // Dynamically set shipping method title
//         $pickup_address = isset($_POST['olza_pickup_option']) ? $_POST['olza_pickup_option'] : '';
//         $delivery_courier_id = isset($_POST['delivery_courier_id']) ? $_POST['delivery_courier_id'] : '';
//         $country_name = isset($_POST['country_name']) && $_POST['country_name'] != '' ? $_POST['country_name'] : 'CZ';
        
//         // Update the shipping title with country
//         $new_shipping_title = $country_name . '-' . $delivery_courier_id;

//         // Update shipping method title
//         foreach ($order->get_items('shipping') as $item_id => $shipping_item) {
//             if ($shipping_item->get_method_id() == 'olza_pickup') {
//                 $shipping_item->set_name($new_shipping_title); // Set new title
//                 $shipping_item->save(); // Save the changes
//             }
//         }
//     }
// }

add_action('woocommerce_admin_order_data_after_billing_address', 'olza_display_pickup_in_admin_orders', 10, 1);
function olza_display_pickup_in_admin_orders($order)
{

    $pickup_field_value = $order->get_meta('Pickup Point');

    if (!empty($pickup_field_value)) {
        echo '<p><strong>' . __('Pickup Point', 'olza-logistic-woo') . ':</strong> ' . $pickup_field_value . '</p>';
    }
}

add_action('woocommerce_order_details_after_order_table_items', 'olza_display_pickup_at_order_details');

function olza_display_pickup_at_order_details($order)
{

    $chosen_methods = WC()->session->get('chosen_shipping_methods');
    if (strpos($chosen_methods[0], 'olza_pickup') !== false) {
        $pickup_point = $order->get_meta('Pickup Point');

        if ($pickup_point) :
?>
            <tr>
                <th scope="row"><?php echo __('Pickup Point', 'olz-logistic-woo'); ?> </th>
                <td><?php echo esc_html($pickup_point) ?></td>
				
            </tr>
        <?php
        endif;
    }
}


/**
 * APP Url Validation
 */

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


/**
 * Get Pickup Points
 */

add_action('wp_ajax_olza_get_pickup_points', 'olza_get_pickup_points_callback');
add_action('wp_ajax_nopriv_olza_get_pickup_points', 'olza_get_pickup_points_callback');

function olza_get_pickup_points_callback()
{
    global $olza_options;
    $olza_options = get_option('olza_options');
	
    if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'olza_checkout')) {

        $country = isset($_POST['country']) && $_POST['country'] != '' ? $_POST['country'] : '';
        $country_name = isset($_POST['country_name']) && $_POST['country_name'] != '' ? $_POST['country_name'] : '';

        $api_url = isset($olza_options['api_url']) && !empty($olza_options['api_url']) ? $olza_options['api_url'] : '';
        $access_token = isset($olza_options['access_token']) && !empty($olza_options['access_token']) ? $olza_options['access_token'] : '';
		
		if(isset($_POST['spedition']) && !empty($_POST['spedition'])){
			 $spedition_data =  $_POST['spedition'];
			
			if( $spedition_data == 'olza_pickup_39'){
				$spedition =  'ppl-ps';
			}elseif($spedition_data == 'olza_pickup_wedobox_41'){
				$spedition =  'wedo-box';
			}
		}else{
			 $spedition =  'all';
		}
       

        $lat =  0;
        $lng =  0;

        if (empty($api_url) || empty($access_token)) {
			
            echo json_encode(
                array(
                    'success' => false,
                    'message' => __('Please verify APP URL & Acess Token.', 'olza-logistic-woo')
                )
            );
            wp_die();
        }

        $args = array(
            'timeout'   => 30, // Timeout in seconds
            'headers'   => array(
                'Content-Type'  => 'application/json'
            )
        );

        $country_file_path =  OLZA_LOGISTIC_PLUGIN_PATH . 'data/' . strtolower($country) . '.json';


        if (file_exists($country_file_path)) {

            $config_response = file_get_contents($country_file_path);

            if (!json_decode($config_response)->success) {
				
                echo json_encode(
                    array(
                        'success' => false,
                        'message' => json_decode($config_response)->code . ' - ' . json_decode($config_response)->message,
                    )
                );
                wp_die();
            }

            $country_data_arr = json_decode($config_response)->data;

            $spedition_arr = array();
            $spedition_dropdown_arr = array();

            if (!empty($country_data_arr) && !empty($country_data_arr->speditions)) {

                $spedition_dropdown_arr[] = array('id' => 'all', 'text' => 'ALL');

                foreach ($country_data_arr->speditions as $key => $speditions_obj) {
                    $spedition_arr[] = $speditions_obj->code;

                    $spedition_dropdown_item = [];
                    $spedition_dropdown_item['id'] =  $speditions_obj->code;
                    $spedition_dropdown_item['text'] =  $speditions_obj->code;
                    $spedition_dropdown_arr[] = $spedition_dropdown_item;
                }
            }
			
            if (!empty($spedition_arr)) {
// 			 $spedition = 'all'; // Default fallback

// 				if (isset($_POST['spedition']) && !empty($_POST['spedition'])) {
// 					$spedition_data = $_POST['spedition'];

// 					if ($spedition_data === 'olza_pickup_39') {
// 						$spedition = 'ppl-ps';
// 					} elseif ($spedition_data === 'olza_pickup_wedobox_41') {
// 						$spedition = 'wedo-box';
// 					}
// 				}
                if ($spedition == 'all') {
                    $spedition_list = implode(',', $spedition_arr);

                    $sped_file_name = strtolower($country) . '_' . 'all';
				
                } else {
                    $spedition_list = $spedition;
                    $sped_file_name = strtolower($country) . '_' . $spedition;
                }

                $find_file_path =  OLZA_LOGISTIC_PLUGIN_PATH . 'data/' . $sped_file_name . '.json';

                $find_response = file_get_contents($find_file_path);

//                 echo '<pre>';
//                 print_r(json_decode($find_response));
//                 echo '</pre>';

                // echo json_encode(
                //     array(
                //         'success' => false,
                //         'message' => '',
                //     )
                // );
                // wp_die();

                if (!json_decode($find_response)->success) {

                    echo json_encode(
                        array(
                            'success' => false,
                            'message' => $find_file_path . ' -content not found ' . json_decode($find_response)->message,
                        )
                    );
                    wp_die();
                } else {

                    $find_data_arr = json_decode($find_response)->data;



                    $pickup_list = array();

                    $pickup_full_list = array();

                    if (!empty($find_data_arr) && !empty($find_data_arr->items)) {

                        foreach ($find_data_arr->items as $key => $pickup_obj) {



                            $pickup_list = [
                                'type' => 'Feature',
                                'properties' => [
                                    'title' => html_entity_decode($pickup_obj->address->full),
                                    'pointid' => html_entity_decode($pickup_obj->id),
                                    'spedition' => html_entity_decode($pickup_obj->spedition),
                                ],
                                'geometry' => [
                                    'type' => 'Point',
                                    'coordinates' => [$pickup_obj->location->longitude, $pickup_obj->location->latitude]
                                ]
                            ];
                            $pickup_full_list[] = $pickup_list;
                            $centerpoint = [$pickup_obj->location->longitude, $pickup_obj->location->latitude];
                            $lat = $pickup_obj->location->latitude;
                            $lng = $pickup_obj->location->longitude;
                        }

                        /**
                         * Nearby Places
                         */

                        $nearby_api_endpoint = olza_validate_url($api_url . '/nearby');

                        $nearby_t_args = array(
                            'access_token' => $access_token,
                            'country' => $country,
                            'spedition' => $spedition_list,
                        );

                        if ($lat != 0 && $lng != 0) {
                            $nearby_t_args['location'] = $lat . ',' . $lng;
                        }

                        $nearby_api_url = add_query_arg($nearby_t_args, $nearby_api_endpoint);

                        $nrearby_response = wp_remote_get($nearby_api_url, $args);

                        $item_listings = '';
                        $item_listings .= '<ul>';
                        if (is_wp_error($nrearby_response)) {

                            $error_message = $nrearby_response->get_error_message();
                            $item_listings .= '<li>' . $error_message . '</li>';
                        } else {

                            $nearbydata = wp_remote_retrieve_body($nrearby_response);

                            $nearbydata_arr = json_decode($nearbydata)->data;

                            if (!empty($nearbydata_arr) && !empty($nearbydata_arr->items)) {

                                foreach ($nearbydata_arr->items as $key => $nearby_obj) {
									
									// echo "testing";
  if ($nearby_obj->spedition == 'WEDO-BOX' || $nearby_obj->spedition == 'PPL-PS') {
                                    $item_listings .= '<li><a class="olza-flyto" href="javascript:void(0)" pointid="' . $nearby_obj->id . '" spedition="' . $nearby_obj->spedition . '" lat="' . $nearby_obj->location->latitude . '" long="' . $nearby_obj->location->longitude . '" address="' . html_entity_decode($nearby_obj->address->full) . '"> <p class="ad-name">' . html_entity_decode($nearby_obj->names[0]) . '</p><p class="ad-full">' . html_entity_decode($nearby_obj->address->full) . '</p><p class="ad-dis">' . $nearby_obj->location->distance . ' m</p></a></li>';
 }     
								}
                            } else {
                              $item_listings .= '<li>No places found</li>';
                            }
                        }

                        $item_listings .= '</ul>';

                        echo json_encode(array('success' => true, 'dropdown' => $spedition_dropdown_arr, 'listings' => $item_listings, 'center' => $centerpoint, 'data' => $pickup_full_list, 'message' => __('Pick Points Loaded Successfully.', 'olza-logistic-woo')));
                        wp_die();
                    } else {
                        echo json_encode(array('success' => true, 'message' => sprintf(__('There are no pickup points in %s.', 'olza-logistic-woo'), $country_name)));
                        wp_die();
                    }
                }
            } else {
                echo json_encode(array('success' => true, 'message' => sprintf(__('There are no pickup points in %s.', 'olza-logistic-woo'), $country_name)));
                wp_die();
            }
        } else {
            echo json_encode(array('success' => false, 'message' => __('Country file not exists.', 'olza-logistic-woo')));
            wp_die();
        }
    } else {

        echo json_encode(array('success' => false, 'message' => __('Security verification failed.', 'olza-logistic-woo')));
        wp_die();
    }
}


add_filter('woocommerce_shipping_methods', 'register_tyche_method');

/**
 * Register Shipping Method
 *
 * @param [type] $methods
 * @return void
 */
function register_tyche_method($methods)
{
    $methods['olza_pickup'] = 'WC_Shipping_Olza_Pickup';
    $methods['olza_pickup_wedobox'] = 'WC_Shipping_Olza_Pickup_wedobox'; // Updated ID
    return $methods;
}

/**
 * WC_Shipping_Olza_Pickup class.
 *
 * @class WC_Shipping_Olza_Pickup
 * @version 1.0.0
 * @package Shipping-for-WooCommerce/Classes
 * @category Class
 * @author Tyche Softwares
 */
class WC_Shipping_Olza_Pickup extends WC_Shipping_Method
{
    public function __construct($instance_id = 0)
    {
        $this->id = 'olza_pickup';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('PickUp Points', 'olza-logistic-woo');
        $this->method_description = __('PickUp Points Shipping method.', 'olza-logistic-woo');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
        );
        $this->instance_form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'olza-logistic-woo'),
                'type' => 'checkbox',
                'label' => __('Enable this shipping method', 'olza-logistic-woo'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Method Title', 'olza-logistic-woo'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'olza-logistic-woo'),
                'default' => __('PickUp Points', 'olza-logistic-woo'),
                'desc_tip' => true
            )
        );
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    public function calculate_shipping($package = array())
    {
        $this->add_rate(array(
            'id' => $this->id . '_' . $this->instance_id,
            'label' => $this->title,
        ));
    }
}

class WC_Shipping_Olza_Pickup_wedobox extends WC_Shipping_Method
{
    public function __construct($instance_id = 0)
    {
        $this->id = 'olza_pickup_wedobox'; // Unique ID
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Wedo Box', 'olza-logistic-woo');
        $this->method_description = __('Wedo Box Shipping method.', 'olza-logistic-woo');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
        );
        $this->instance_form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'olza-logistic-woo'),
                'type' => 'checkbox',
                'label' => __('Enable this shipping method', 'olza-logistic-woo'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Method Title', 'olza-logistic-woo'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'olza-logistic-woo'),
                'default' => __('Wedo Box', 'olza-logistic-woo'),
                'desc_tip' => true
            )
        );
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title'); // Use the title from the settings

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    public function calculate_shipping($package = array())
    {
        $this->add_rate(array(
            'id' => $this->id . '_' . $this->instance_id,
            'label' => $this->title, // Use the title from the settings
        ));
    }
}


add_action('woocommerce_after_shipping_rate', 'add_link_to_custom_shipping_method', 10, 2);

function add_link_to_custom_shipping_method($method, $index)
{
if ($method->method_id === 'olza_pickup') {
    echo '<div class="oloz-pickup-selection pickup-olza" style="display:none;">
        <p><span></span></p>
    </div>';
    echo '<a href="javascript:void(0)" class="olza-load-map" style="display:none;">' . __('Choose Pickup', 'olza-logistic-woo') . '</a>';
} elseif ($method->method_id === 'olza_pickup_wedobox') {
    echo '<div class="oloz-pickup-selection pickup-olza-wedobox" style="display:none;">
        <p><span></span></p>
    </div>';
    echo '<a href="javascript:void(0)" class="olza-load-map" style="display:none;">' . __('Choose Pickup', 'olza-logistic-woo') . '</a>';
}
}

add_action('wp_footer', 'olz_logistic_load_moadal_map_pickups');

function olz_logistic_load_moadal_map_pickups()
{

    if (class_exists('WooCommerce') && (is_checkout() || is_cart())) {

        ?>
        <div id="custom-modal" class="custom-modal">
            <div class="custom-modal-dialog">
                <div class="custom-modal-content">
                    <a href="javascript:void(0)" class="olza-close-modal">X</a>
                    <div class="custom-modal-body">
                        <div class="custom-modal-inner">
                            <div class="olza-map-dialog">

                                <div class="olza-map-filters">
                                    <div class="olza-filters-wrap">
                                        <span class="olza-loader-overlay"></span>
<!--                                         <div class="olza-spedition-wrap">
                                            <select id="olza-spedition-dropdown">
                                                <option value="all"><?php //echo __('ALL', 'olza-logistic-woo'); ?></option>
                                            </select>
                                        </div> -->
                                        <div class="olza-search-wrap" id="olza-geocoder"></div>
                                    </div>
                                    <div class="olza-point-listings">
                                        <span class="olza-loader-overlay"></span>
                                        <div class="olza-listings-head">
                                            <p><?php echo __('Closest Pickup Points', 'olza-logistic-woo'); ?></p>
                                        </div>
                                        <div class="olza-closest-listings">
                                            <ul>
                                                <li>
                                                    <?php echo __('No Nearby Found', 'olza-pickup-woo'); ?>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="olza-map-data">
                                    <div id="olza-pickup-map"><span class="olza-loader-overlay"></span></div>
                                    <div class="oloz-pickup-selection">
                                        <p><strong><?php echo __('PickUp Selection : ', 'olza-logistic-woo'); ?></strong><span></span></p>
                                    </div>
                                </div>

                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            jQuery(function($) {
                function olza_update_map_btn() {

//                     if (jQuery('#shipping_method').length > 0) {
//                         var selectedShippingMethod = jQuery('input[name="shipping_method[0]"]:checked').val();

//                         if (selectedShippingMethod.includes('olza_pickup')) {
//                             jQuery('.olza-load-map').show();
//                         } else {
//                             jQuery('.olza-load-map').hide();
//                         }
//                     }
jQuery(document).ready(function() {

    // Initially hide all the olza-load-map anchors and oloz-pickup-selection
    jQuery('.olza-load-map').hide();
    jQuery('.pickup-olza').css('display', 'none');
    jQuery('.pickup-olza-wedobox').css('display', 'none');

    // Check the selected shipping method and show the corresponding map and pickup selection
    var selectedShippingMethod = jQuery('input[name="shipping_method[0]"]:checked').attr('id');

    // If the selected shipping method contains 'olza_pickup', show the map and pickup selection for that method
    if (selectedShippingMethod && selectedShippingMethod.includes('olza_pickup')) {
        var closestLi = jQuery('#' + selectedShippingMethod).closest('li');

        // Check for specific pickup methods
        if (selectedShippingMethod.includes('olza_pickup_wedobox')) {
            closestLi.find('.pickup-olza-wedobox').css('display', 'block');
            closestLi.find('.olza-load-map').show();
        } else {
            closestLi.find('.pickup-olza').css('display', 'block');
            closestLi.find('.olza-load-map').show();
        }
    }

    // Re-run on change of shipping method
    jQuery('input[name="shipping_method[0]"]').change(function() {
        // Hide all maps and pickup selections initially
        jQuery('.olza-load-map').hide();
        jQuery('.oloz-pickup-selection').css('display', 'none');

        // Check the selected shipping method again
        var selectedShippingMethod = jQuery('input[name="shipping_method[0]"]:checked').attr('id');

        // If the selected method includes 'olza_pickup', show the relevant map and pickup selection
        if (selectedShippingMethod && selectedShippingMethod.includes('olza_pickup')) {
            var closestLi = jQuery('#' + selectedShippingMethod).closest('li');

            // Check for specific pickup methods
            if (selectedShippingMethod.includes('olza_pickup_wedobox')) {
                closestLi.find('.pickup-olza-wedobox').css('display', 'block');
                closestLi.find('.olza-load-map').show();
            } else {
                closestLi.find('.pickup-olza').css('display', 'block');
                closestLi.find('.olza-load-map').show();
            }
        }
    });

    // Hide pickup options initially when clicking the close modal button
    jQuery('.olza-close-modal').click(function() {
        jQuery('.pickup-olza').css('display', 'none');
        jQuery('.pickup-olza-wedobox').css('display', 'none');

        // Check the selected shipping method again after closing the modal
        var selectedShippingMethod = jQuery('input[name="shipping_method[0]"]:checked').attr('id');
        
        // Show the corresponding pickup based on the selected shipping method
        if (selectedShippingMethod && selectedShippingMethod.includes('olza_pickup')) {
            var closestLi = jQuery('#' + selectedShippingMethod).closest('li');

            // Show the correct pickup selection based on the method
            if (selectedShippingMethod.includes('olza_pickup_wedobox')) {
                closestLi.find('.pickup-olza-wedobox').css('display', 'block');
                closestLi.find('.olza-load-map').show();
            } else {
                closestLi.find('.pickup-olza').css('display', 'block');
                closestLi.find('.olza-load-map').show();
            }
        }
    });

});




                }

                jQuery(document.body).on('updated_checkout', function() {
                    olza_update_map_btn();
                });

                jQuery(document).ready(function() {
                    olza_update_map_btn();
                });

  // Check if .oloz-pickup-selection has display: block
//   if (jQuery('.oloz-pickup-selection').css('display') === 'block') {
//     // Apply top: 555px to .olza-load-map
//     jQuery('.olza-load-map').css('top', '555px');
//   }

// 				$(document).ready(function() {
//    jQuery('.olza-load-map').click(function() {
//         const selectedMethod = jQuery('input[name="shipping_method[0]"]:checked').val();

//         let dropdownValue = '';
//         if (selectedMethod === 'olza_pickup_wedobox_25') {
//             dropdownValue = 'wedo-box';
//         } else if (selectedMethod === 'olza_pickup_21') {
//             dropdownValue = 'ppl-ps';
//         }

//         // Set the dropdown value if it matches
//         if (dropdownValue) {
//             jQuery('#olza-spedition-dropdown').val(dropdownValue).trigger('change');
//         }
//     });
// });

			
            });

// jQuery(document).on('click', '.olza-load-map', function() {
//     var selectedMethod = jQuery('input[name="shipping_method[0]"]:checked').val();

//     if (selectedMethod) {
//         // Determine the value to select based on the shipping method
//         var selectedValue = '';

//         if (selectedMethod === 'olza_pickup_wedobox_28') {
//             selectedValue = 'wedo-box'; // Value for Wedo Box
// alert(selectedValue);
//         } else if (selectedMethod === 'olza_pickup_29') {
//             selectedValue = 'ppl-ps'; // Value for PPL PS
//         } else {
//             // Handle other cases if necessary
//             selectedValue = ''; // Default selection or another relevant option
//         }

//         // Set the value in the Select2 dropdown
// //        jQuery('#olza-spedition-dropdown').val(selectedValue).trigger('change');
//   jQuery.ajax({
//             url: ajaxurl, // WordPress AJAX URL
//             type: 'POST',
//             data: {
//                 action: 'olza_get_pickup_points', // Your AJAX action
//                 selected_value: selectedValue // Send the selected value
//             },
//             success: function(response) {
//                 // Handle the response from the server
//                 console.log(response); // You can modify this to update the UI or show a message
//             },
//             error: function(xhr, status, error) {
//                 console.error(error); // Handle any errors
//             }
//         });
//         // Show the popup
//         jQuery('#pickupModal').show();
//     } else {
//         alert('Please select a shipping method.');
//     }
// });

// jQuery(document).ready(function() {
//     jQuery('#olza-spedition-dropdown').select2();
// });

        </script>
<style>
	.custom-modal {
    z-index: 999;
}
body .olza-load-map {
    white-space: nowrap;
    max-width: 170px !important;
}
	#shipping_method li {
    position: relative;
}
	
/* body .olza-load-map {
    padding: 10px;
    background: #9c80b7;
    text-decoration: none;
    color: #fff;
    border-radius: 5px;
    margin-top: 5px;
    display: inline-block;
    position: absolute;
    top: 630px;
    right: 55px;
    align-items: center;
    text-align: center;
    max-width: 160px;
    margin: auto;
} */
	body li:last-child .olza-load-map{
bottom: -55px;
}
body .olza-load-map {
    padding: 10px;
    background: #9c80b7;
    text-decoration: none;
    color: #fff;
    border-radius: 5px;
    margin-top: 5px;
    display: inline-block;
    position: absolute;
    top: auto;
    right: 0px;
/* 	bottom: -55px; */
    bottom: -90px; 
    align-items: center;
    text-align: center;
    max-width: 160px;
    margin: auto;
}
#shipping_method li:last-child {
    margin-bottom: 50px !important;
}
	@media only screen and (max-width: 800px) {
    .olza-close-modal {
        position: sticky;
        display: flex
;
        justify-content: center;
        align-items: center;
        margin-left: auto;
    }
		    .olza-map-filters {
        width: 100%;
        padding: 20px;
        padding-top: 0;
    }
		    .custom-modal-dialog {
        height: 81%;
        overflow-y: scroll;
        border-radius: 10px;
        margin-top: 50px;
        z-index: 99999;
    }
}
</style>
<?php

    }
}


/**
 * Get Pickup Points
 */

add_action('wp_ajax_olza_get_nearby_points', 'olza_get_nearby_points_callback');
add_action('wp_ajax_nopriv_olza_get_nearby_points', 'olza_get_nearby_points_callback');

function olza_get_nearby_points_callback()
{
    global $olza_options;
    $olza_options = get_option('olza_options');

    if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'olza_checkout')) {

        $lat = isset($_POST['lat']) && $_POST['lat'] != '' ? $_POST['lat'] : '';
        $lng = isset($_POST['lng']) && $_POST['lng'] != '' ? $_POST['lng'] : '';
        $cont = isset($_POST['cont']) && !empty($_POST['cont']) ? $_POST['cont'] : '';
        $sped = isset($_POST['sped']) && !empty($_POST['sped']) ? $sped : '';

        $api_url = isset($olza_options['api_url']) && !empty($olza_options['api_url']) ? $olza_options['api_url'] : '';
        $access_token = isset($olza_options['access_token']) && !empty($olza_options['access_token']) ? $olza_options['access_token'] : '';

        /**
         * Nearby Places
         */

        $nearby_api_endpoint = olza_validate_url($api_url . '/nearby');

        $nearby_t_args = array(
            'access_token' => $access_token,
            'country' => $cont,
            'spedition' => $sped,
        );

        if ($lat != 0 && $lng != 0) {
            $nearby_t_args['location'] = $lng . ',' . $lat;
        }

        $nearby_api_url = add_query_arg($nearby_t_args, $nearby_api_endpoint);

        $nearby_args = array(
            'timeout'   => 300, // Timeout in seconds
            'headers'   => array(
                'Content-Type'  => 'application/json'
            )
        );

        $nrearby_response = wp_remote_get($nearby_api_url, $nearby_args);

        // Initialize the dropdown options
        $item_listings = '<ul>';
        if (is_wp_error($nrearby_response)) {
            $error_message = $nrearby_response->get_error_message();
            $item_listings .= '<li>' . $error_message . '</li>';
        } else {
            $nearbydata = wp_remote_retrieve_body($nrearby_response);
            $nearbydata_arr = json_decode($nearbydata)->data;
          //  alert($nearbydata_arr);
            // Filtered providers list
            $allowed_providers = ['ppl-ps', 'wedo-box'];

            if (!empty($nearbydata_arr) && !empty($nearbydata_arr->items)) {
                foreach ($nearbydata_arr->items as $key => $nearby_obj) {
                    // Check if the provider is one of the allowed providers
                    $spedition = strtolower($nearby_obj->spedition);
                  //  echo "test";
					if (in_array($spedition, array_map('strtolower', $allowed_providers))) {
                  //  if (in_array($nearby_obj->spedition, $allowed_providers)) {
                        $item_listings .= '<li><a class="olza-flyto" href="javascript:void(0)" pointid="' . $nearby_obj->id . '" spedition="' . $nearby_obj->spedition . '" lat="' . $nearby_obj->location->latitude . '" long="' . $nearby_obj->location->longitude . '" address="' . html_entity_decode($nearby_obj->address->full) . '"> <p class="ad-name">' . html_entity_decode($nearby_obj->names[0]) . '</p><p class="ad-full">' . html_entity_decode($nearby_obj->address->full) . '</p><p class="ad-dis">' . $nearby_obj->location->distance . ' m</p></a></li>';
                    }
                }
            } else {
                $item_listings .= '<li>' . __('No Nearby Found', 'olza-pickup-woo') . '</li>';
            }
        }

        $item_listings .= '</ul>';

        echo json_encode(array('success' => true, 'listings' => $item_listings, 'message' => __('Nearby Points Loaded Successfully.', 'olza-logistic-woo')));
        wp_die();
    } else {
        echo json_encode(array('success' => false, 'message' => __('Security verification failed.', 'olza-logistic-woo')));
        wp_die();
    }
}




/**
 * Adding Cart Fee
 */

add_action('woocommerce_cart_calculate_fees', 'olza_add_cart_fee', 20, 1);
function olza_add_cart_fee($cart)
{
    if (is_admin() && !defined('DOING_AJAX'))
        return;

    global $woocmmerce, $olza_options;
    $chosen_methods = WC()->session->get('chosen_shipping_methods');
    if (strpos($chosen_methods[0], 'olza_pickup') !== false) {


        $olza_options = get_option('olza_options');
        $basket_fee = isset($olza_options['basket_fee']) && !empty($olza_options['basket_fee']) ? $olza_options['basket_fee'] : '';

        //$total_coast = (int) WC()->cart->get_cart_contents_total();
		$total_coast = (int) $cart->subtotal;

        $fee_amount = olza_calculateBasketFee($total_coast, $basket_fee);

        $fee_text = __("Pickup Fee", "olza-logistic-woo");
        $cart->add_fee($fee_text, $fee_amount, false);
    }
}


function olza_calculateBasketFee($basketAmount, $feeRules)
{
    foreach ($feeRules as $rule) {
        switch ($rule['condition']) {
            case 'less':
                if ($basketAmount < $rule['amount']) {
                    return $rule['fee'];
                }
                break;
            case 'greater_than_equal':
                if ($basketAmount >= $rule['amount']) {
                    return $rule['fee'];
                }
                break;
            case 'equal':
                if ($basketAmount = $rule['amount']) {
                    return $rule['fee'];
                }
                break;
            case 'less_than_equal':
                if ($basketAmount <= $rule['amount']) {
                    return $rule['fee'];
                }
                break;
            case 'greater':
                if ($basketAmount > $rule['amount']) {
                    return $rule['fee'];
                }
                break;
        }
    }
    return 0;
}


add_action( 'woocommerce_checkout_order_processed', 'custom_update_shipping_address', 25, 3 );
//add_action('woocommerce_checkout_update_order_meta', 'custom_update_shipping_address', 10, 2);

function custom_update_shipping_address($order_id, $posted_data, $order)
{
    $order = wc_get_order($order_id);
    $shipping_methods = $order->get_shipping_methods();

    $update_shipping_address = false;

    foreach ($shipping_methods as $shipping_method) {
        if (strpos($shipping_method->get_method_id(), 'olza_pickup') !== false) {
            $update_shipping_address = true;
            break;
        }
    }

    if ($update_shipping_address) {

        $pickup_address = get_post_meta($order_id, 'Pickup Point', true);

        // $new_shipping_address = array();

        // if (!empty($pickup_address)) {
        //     $new_shipping_address['address_1'] = $pickup_address;
        // }

        // if (!empty($new_shipping_address) && sizeof($new_shipping_address) > 0) {
        //     foreach ($new_shipping_address as $key => $value) {
        //         $order->update_meta_data('_shipping_' . $key, $value);
        //     }
        // }

        $order->set_shipping_address_1( $pickup_address );
        $order->save();
    }
}


add_action('woocommerce_admin_order_data_after_shipping_address', 'olza_logistic_update_admin_order_metabox', 10, 1);
function olza_logistic_update_admin_order_metabox($order)
{

    $order_id = $order->get_id();
    $order = wc_get_order($order_id);
    $shipping_methods = $order->get_shipping_methods();

    $update_shipping_address = false;

    foreach ($shipping_methods as $shipping_method) {
        if (strpos($shipping_method->get_method_id(), 'olza_pickup') !== false) {
            $update_shipping_address = true;
            break;
        }
    }

    if ($update_shipping_address) {

        $pickup_address = get_post_meta($order_id, 'Pickup Point', true);
        $delivery_point_id = get_post_meta($order_id, 'delivery_point_id', true);
        $delivery_courier_id = get_post_meta($order_id, 'delivery_courier_id', true);

        echo '<p><strong>' . __('Pickup Points Data', 'olza-logistic-woo') . '</strong></br>';
        echo '<strong>' . __('Pickup Address', 'olza-logistic-woo') . '</strong> : ' . $pickup_address . '</br>';
        echo '<strong>' . __('Pickup Point ID', 'olza-logistic-woo') . '</strong> : ' . $delivery_point_id . '</br>';
        echo '<strong>' . __('Pickup Point Courier', 'olza-logistic-woo') . '</strong> : ' . $delivery_courier_id . ' </p>';
    }
}