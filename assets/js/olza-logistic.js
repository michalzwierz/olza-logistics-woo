(function ($) {

    /*
     * Responsible for custom js functions
     */

    jQuery(document).ready(function () {

        if (jQuery('#olza-spedition-dropdown').length > 0) {
            jQuery('#olza-spedition-dropdown').select2({ width: '100%' });
        }

        jQuery(document).on('click', '.olza-load-map', function (e) {
			
			
    	var selectedMethod = jQuery('input[name="shipping_method[0]"]:checked').val();
			
		
			
		if (selectedMethod) {
        // Determine the value to select based on the shipping method
        var selectedValue = '';

        if (selectedMethod === 'olza_pickup_wedobox_28') {
            selectedValue = 'wedo-box'; // Value for Wedo Box
        } else if (selectedMethod === 'olza_pickup_29') {
            selectedValue = 'ppl-ps'; // Value for PPL PS
        } else {
            selectedValue = ''; // Handle other cases if necessary
        }
			  }
            if ($('.shipping_method:checked').filter('[value*="olza_pickup"]').length > 0) {

                jQuery("body").addClass("modal-open");

                jQuery('#olza-spedition-dropdown').select2({ width: '100%' });

                olza_load_map_cluster_data(selectedValue);

            } else {

                if ($('.shipping_method[value*="olza_pickup"]').is(':hidden') && $('.shipping_method:checked').length === 0) {
                    jQuery("body").addClass("modal-open");

                    jQuery('#olza-spedition-dropdown').select2({ width: '100%' });

                    olza_load_map_cluster_data(selectedValue);
                } else {
                    alert(olza_global.chose_ship_method);
                }

            }

        });

        jQuery(document).on('click', '.olza-close-modal', function (e) {


           var pick_selection_val = jQuery('#olza_pickup_option').val();

            if (
                pick_selection_val !== undefined &&
                pick_selection_val !== null &&
                pick_selection_val.trim() !== '' &&
                pick_selection_val.length !== 0
            ) {

                $.confirm({
                    title: olza_global.r_u_sure,
                    content: olza_global.pic_selection + pick_selection_val,
                    boxWidth: '30%',
                    useBootstrap: false,
                    closeIcon: true,
                    closeIconClass: 'fa fa-close',
                    type: 'orange',
                    buttons: {
                        confirm: {
                            text: olza_global.confirm,
                            action: function () {
                                jQuery("body").removeClass("modal-open");
                            }
                        },
                        somethingElse: {
                            text: olza_global.choose_another,
                            action: function () {
                                jQuery('#olza_pickup_option').val('');
                                jQuery('#delivery_point_id').val('');
                                jQuery('#delivery_courier_id').val('');

                                jQuery('.oloz-pickup-selection').hide();
                            }
                        },
                        cancel: {
                            text: olza_global.goto_checkout,
                            action: function () {
                                jQuery("body").removeClass("modal-open");
                                jQuery('#olza_pickup_option').val('');
                                jQuery('#delivery_point_id').val('');
                                jQuery('#delivery_courier_id').val('');
                                jQuery('.oloz-pickup-selection').hide();
                            }
                        }
                    }
                });

            } else {
                jQuery("body").removeClass("modal-open");
            }

        });

        /**
         * Load point after spedition change
         */

        jQuery(document).on('change', '#olza-spedition-dropdown', function (e) {

            var olza_ship_value = jQuery(this).val();

            olza_load_map_cluster_data(olza_ship_value, 'dropdown');

        });


        /**
         * Gload function to load points
         */


        function olza_load_map_cluster_data(spedition, loadType = 'all') {
           
            /**
             * Reset geocode container
             */
            jQuery('#olza-geocoder').html('');

            /**
             * Getting country values
             */

            var country_val = jQuery('#billing_country').val();
            var country_label = jQuery('#billing_country').find('option:selected').text();
			var spedition = jQuery('input[name="shipping_method[0]"]:checked').val();
		
            console.log(spedition);

            /**
             * Adding Loader
             */

            jQuery('.olza-loader-overlay').css('display', 'flex');

            /**
             * Making request data
             */

            var olza_data = {
                nonce: olza_global.nonce,
                action: 'olza_get_pickup_points',
                country: country_val,
                country_name: country_label,
                spedition: spedition
            };

            /**
             * Request calling to load data
             */

            $.ajax({
                type: 'POST',
                data: olza_data,
                dataType: 'json',
                url: olza_global.ajax_url,
                crossDomain: true,
                cache: false,
                async: true,
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', olza_global.nonce);
                },
            }).done(function (response) {

                console.log(response);

                jQuery('.olza-loader-overlay').css('display', 'none'); // stop loader

                if (response.success !== undefined && response.success) {

                    if (response.data !== undefined) {

                        /**
                         * Adding all spedition
                         */

                        if (loadType == 'all') {
                            jQuery("#olza-spedition-dropdown").html('').select2({ data: response.dropdown, width: '100%' });
                        }

                        /**
                         * Adding Nearby Listings
                         */

                        jQuery('.olza-closest-listings').html(response.listings);

                        /**
                         * Init map
                         */

                        mapboxgl.accessToken = olza_global.mapbox_token;
                        const map = new mapboxgl.Map({
                            container: 'olza-pickup-map',
                            style: 'mapbox://styles/mapbox/streets-v12',
                            center: response.center,
                            zoom: 8,
                        });

                        map.on('load', () => {

                            /**
                             * Loading Map data points
                             */
                            map.addSource('places', {
                                type: 'geojson',
                                'data': {
                                    'type': 'FeatureCollection',
                                    'features': response.data
                                },
                                cluster: true,
                                clusterMaxZoom: 14,
                                clusterRadius: 50
                            });

                            /**
                             * Adding points cluster layer
                             */

                            map.addLayer({
                                id: 'clusters',
                                type: 'circle',
                                source: 'places',
                                filter: ['has', 'point_count'],
                                paint: {
                                    'circle-color': [
                                        'step',
                                        ['get', 'point_count'],
                                        '#51bbd6',
                                        100,
                                        '#f1f075',
                                        750,
                                        '#f28cb1'
                                    ],
                                    'circle-radius': [
                                        'step',
                                        ['get', 'point_count'],
                                        20,
                                        100,
                                        30,
                                        750,
                                        40
                                    ]
                                }
                            });

                            /**
                            * Cluster points count stylying
                            */

                            map.addLayer({
                                id: "cluster-count",
                                type: "symbol",
                                source: "places",
                                filter: ["has", "point_count"],
                                layout: {
                                    "text-field": "{point_count_abbreviated}",
                                    "text-font": ["DIN Offc Pro Medium", "Arial Unicode MS Bold"],
                                    "text-size": 12
                                },
                                paint: {
                                    "text-color": "#000"
                                }
                            });

                            /**
                             * Speading Cluster on click
                             */

                            map.on('click', 'clusters', (e) => {

                                const features = map.queryRenderedFeatures(e.point, {
                                    layers: ['clusters']
                                });
                                const clusterId = features[0].properties.cluster_id;

                                map.getSource('places').getClusterExpansionZoom(
                                    clusterId,
                                    (err, zoom) => {
                                        if (err) return;

                                        map.easeTo({
                                            center: features[0].geometry.coordinates,
                                            zoom: zoom
                                        });
                                    }
                                );
                            });

                            /**
                             * Set cursor on cluster
                             */

                            map.on('mouseenter', 'clusters', () => {
                                map.getCanvas().style.cursor = 'pointer';
                            });

                            /**
                             * Removing cursor on cluster
                             */

                            map.on('mouseleave', 'clusters', () => {
                                map.getCanvas().style.cursor = '';
                            });

                            /**
                             * Set style of un-cluster (circle marker) points
                             */

                            map.addLayer({
                                id: 'unclustered-point',
                                type: 'circle',
                                source: 'places',
                                filter: ['!', ['has', 'point_count']],
                                paint: {
                                    'circle-color': '#4264fb',
                                    'circle-radius': 6,
                                    'circle-stroke-width': 2,
                                    'circle-stroke-color': '#ffffff'
                                }
                            });

                            /**
                             * Init Popup
                             */

                            const popup = new mapboxgl.Popup({
                                closeButton: false,
                                closeOnClick: false
                            });

                            /**
                             * Set cursor and popup after click of un-cluster (circle marker) points
                             */

                            map.on('click', 'unclustered-point', (e) => {

                                map.getCanvas().style.cursor = 'pointer';

                                const coordinates = e.features[0].geometry.coordinates.slice();
                                const title = e.features[0].properties.title;
                                const pointid = e.features[0].properties.pointid;
                                const spedition = e.features[0].properties.spedition;


                                while (Math.abs(e.lngLat.lng - coordinates[0]) > 180) {
                                    coordinates[0] += e.lngLat.lng > coordinates[0] ? 360 : -360;
                                }

                                popup.setLngLat(coordinates).setHTML(title).addTo(map);

                                $.confirm({
                                    title: olza_global.r_u_sure,
                                    content: olza_global.pic_selection + title,
                                    boxWidth: '30%',
                                    useBootstrap: false,
                                    closeIcon: true,
                                    closeIconClass: 'fa fa-close',
                                    type: 'orange',
                                    buttons: {
                                        confirm: {
                                            text: olza_global.confirm,
                                            action: function () {
                                                jQuery('#olza_pickup_option').val(title);
                                                jQuery('#delivery_point_id').val(pointid);
                                                jQuery('#delivery_courier_id').val(spedition);
                                                jQuery('.oloz-pickup-selection').show();
                                                jQuery('.oloz-pickup-selection span').html(title);
                                            }
                                        },
                                        cancel: {
                                            text: olza_global.cancel,
                                            action: function () {
                                                jQuery('#olza_pickup_option').val('');
                                                jQuery('#delivery_point_id').val('');
                                                jQuery('#delivery_courier_id').val('');
                                                jQuery('.oloz-pickup-selection').hide();
                                                jQuery('.oloz-pickup-selection span').html('');
                                            }
                                        }
                                    }
                                });

                            });

                            /**
                            * Set cursor and popup after hover on un-cluster (circle marker) points
                            */


                            map.on('mouseenter', 'unclustered-point', (e) => {

                                map.getCanvas().style.cursor = 'pointer';
                                const coordinates = e.features[0].geometry.coordinates.slice();
                                const title = e.features[0].properties.title;

                                while (Math.abs(e.lngLat.lng - coordinates[0]) > 180) {
                                    coordinates[0] += e.lngLat.lng > coordinates[0] ? 360 : -360;
                                }

                                popup.setLngLat(coordinates).setHTML(title).addTo(map);

                            });

                            /**
                            * removing cursor and popup after leaving un-cluster (circle marker) points
                            */

                            map.on('mouseleave', 'unclustered-point', () => {
                                map.getCanvas().style.cursor = '';
                                popup.remove();
                                console.log('uncluster-leave');
                            });

                            /**
                             * Function to move canvous to map center
                             * when click on nearby listing values
                             * also add a marker to the point
                             * for 3 seconds
                             * save & display the selection value
                             *
                             * @param {float} lat
                             * @param {float} lng
                             * @param {string} address
                             */

                            function flyToLocation(lat, lng, address, pointid, spedition) {

                                console.log('in');
                                $.confirm({
                                    title: olza_global.r_u_sure,
                                    content: olza_global.pic_selection + address,
                                    boxWidth: '30%',
                                    useBootstrap: false,
                                    closeIcon: true,
                                    closeIconClass: 'fa fa-close',
                                    type: 'orange',
                                    buttons: {
                                        confirm: {
                                            text: olza_global.confirm,
                                            action: function () {
                                                jQuery('#olza_pickup_option').val(address);
                                                jQuery('#delivery_point_id').val(pointid);
                                                jQuery('#delivery_courier_id').val(spedition);
                                                jQuery('.oloz-pickup-selection').show();
                                                jQuery('.oloz-pickup-selection span').html(address);

                                                const marker = new mapboxgl.Marker({
                                                    color: '#f56142',
                                                })
                                                    .setLngLat([lng, lat])
                                                    .addTo(map);

                                                map.flyTo({
                                                    center: [lng, lat],
                                                    essential: true,
                                                });


                                                setTimeout(() => {
                                                    marker.remove();
                                                }, 3000);
                                            }
                                        },
                                        cancel: {
                                            text: olza_global.cancel,
                                            action: function () {
                                                jQuery('#olza_pickup_option').val('');
                                                jQuery('#delivery_point_id').val('');
                                                jQuery('#delivery_courier_id').val('');
                                                jQuery('.oloz-pickup-selection').hide();
                                                jQuery('.oloz-pickup-selection span').html('');
                                            }
                                        }
                                    }
                                });


                            }

                            /**
                             * Adding event of nearby listing click
                             */

                            //jQuery(document).on('click', '.olza-flyto', function (e) {
                                jQuery(document).off('click', '.olza-flyto').on('click', '.olza-flyto', function(e) {

                                e.preventDefault();
                                const lat = parseFloat(jQuery(this).attr('lat'));
                                const lng = parseFloat(jQuery(this).attr('long'));
                                const nerabadd = jQuery(this).attr('address');
                                const pointidd = jQuery(this).attr('pointid');
                                const speditionn = jQuery(this).attr('spedition');
                                console.log('click-ev');
                                flyToLocation(lat, lng, nerabadd, pointidd, speditionn);
                            });

                            /**
                             * PickPoint Data for Geocoding Search
                             */

                            const pickPointData = {
                                'features': response.data,
                                'type': 'FeatureCollection'
                            };

                            function mergePickupdata(query) {
                                const matchingPickup = [];
                                for (const pickuppoint of pickPointData.features) {
                                    if (
                                        pickuppoint.properties.title
                                            .toLowerCase()
                                            .includes(query.toLowerCase())
                                    ) {
                                        pickuppoint['place_name'] = `${pickuppoint.properties.title}`;
                                        pickuppoint['center'] = pickuppoint.geometry.coordinates;
                                        matchingPickup.push(pickuppoint);
                                    }
                                }
                                return matchingPickup;
                            }

                            /**
                             * Adding Pickup Points Search(geocode) on sidebar
                             */

                            const geocoder = new MapboxGeocoder({
                                accessToken: mapboxgl.accessToken,
                                localGeocoder: mergePickupdata,
                                localGeocoderOnly: true,
                                marker: true,
                                zoom: 14,
                                placeholder: olza_global.geocode_placeholder,
                                mapboxgl: mapboxgl
                            }).on('result', (selected) => {

                                console.log(selected);

                                /**
                                 * Save Pickup after search selection
                                 */


                                jQuery('#olza_pickup_option').val(selected.result.place_name);
                                jQuery('#delivery_point_id').val(selected.result.properties.pointid);
                                jQuery('#delivery_courier_id').val(selected.result.properties.spedition);
                                jQuery('.oloz-pickup-selection').show();
                                jQuery('.oloz-pickup-selection span').html(selected.result.place_name);


                                /**
                                 * Load nearby place after address selection
                                 * @param
                                 * latitude
                                 * longitude
                                 * country
                                 * spedition
                                 */

                                load_nearby_places(selected.result.center[0], selected.result.center[1], country_val, spedition);

                            });

                            document.getElementById('olza-geocoder').appendChild(geocoder.onAdd(map));

                        });

                    } else {
                        alert(response.message);
                    }

                } else {
                    alert(response.message);
                }

            });

            return false;

        }

    });

    /**
     *
     * load nearby places after geocoding serach place
     *
     * @param {float} lat
     * @param {float} lng
     * @param {string} cont
     * @param {string} sped
     */

    function load_nearby_places(lat, lng, cont, sped) {

        /**
         * making spedition values for requesting
         * pass all spedition values to request
         * if the selection is all
         * for other selection go and proceed
         */

        if (sped === 'all') {

            var allSped = $("#olza-spedition-dropdown").find('option').map(function () {
                if ($(this).val() !== 'all') {
                    return $(this).val();
                }
            }).get();

            sped = allSped.join(',');
        }

        /**
         * Loading
         */

        jQuery('.olza-point-listings .olza-loader-overlay').css('display', 'flex');

        /**
         * Making Nearby data
         */

        var nearby_data = {
            nonce: olza_global.nonce,
            action: 'olza_get_nearby_points',
            lat: lat,
            lng: lng,
            cont: cont,
            sped: sped
        };

        /**
         * Request calling
         */

        $.ajax({
            type: 'POST',
            data: nearby_data,
            dataType: 'json',
            url: olza_global.ajax_url,
            crossDomain: true,
            cache: false,
            async: true,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', olza_global.nonce);
            },
        }).done(function (response) {

            /**
             * Stop Loading
             */

            jQuery('.olza-loader-overlay').css('display', 'none');

            /**
             * success response checking
             */

            if (response.success !== undefined && response.success) {

                if (response.listings !== undefined) {

                    /**
                     * reset listing container
                     */
                    jQuery('.olza-closest-listings').html('');
                    /**
                     * Loading new nearby listings
                     */
                    jQuery('.olza-closest-listings').html(response.listings);
                }
            } else {
                alert(response.message);
            }

        });

    }



})(jQuery);


