(function ($) {

    /*
     * Responsible for admin custom js
     */

    jQuery(document).ready(function () {

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
            let refresh_flag = olza_global_admin.confirm_msg;
            if (confirm(refresh_flag) == true) {

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
                    alert(response.message);
                });

            } else {
                return false;
            }

        });

    });

})(jQuery);