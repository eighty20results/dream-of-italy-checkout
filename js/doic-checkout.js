/*
 License:

 Copyright 2016 Eighty/20 Results,
 a Wicked Strong Chicks, LLC company - Thomas Sjolshagen (thomas@eighty20results.com)

 This program is custom software developed for the owners of the
 Dream of Italy (dreamofitaly.com) website.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

 As the owner of dreamofitaly.com website, you have been granted
 permission to use and modify this software as you see fit for
 use on the dreamofitaly.com website.
 */
jQuery.noConflict();
var doic_checkout = {
    init: function () {
        "use strict";

        this.recurring_button = jQuery('#doic-is-not-recurring');
        this.want_recurring = this.recurring_button.is(':checked');
        this.level = null;
        var $class = this;

        $class._bind_inputs();
    },
    check_level: function (level_info) {
        "use strict";

        var $class = this;
        var current_level = jQuery('#level').val();

        console.dir("level_info:", level_info);

        if (current_level !== level_info.id) {
            // Update all relevant level fields for checkout:

            console.log("Received level ID is different: " + level_info.id);
            jQuery("#pmpro_level_cost").html(level_info.pricing_text);

            // jQuery('#level').val(level_info.id);
            $class.init();
        }
        else
        {
            console.log("No need to change the level info since the level ID is the same: " + current_level + " vs " + level_info.id);
        }
    },
    set_membership_level: function () {
        "use strict";

        var $class = this;

    },
    _get_membership_level: function () {
        "use strict";

        var $class = this;
        var current_level = jQuery('#level').val();

        console.log("Current level ID: " + current_level);

        var data = {
            'action': 'doic_get_level',
            'doic-nonce': jQuery('#doic-nonce').val(),
            'doic-hidden-recurring': jQuery('#doic-hidden-recurring').val(),
            'doic-is-not-recurring': $class.want_recurring,
            'doic-current-level-id': current_level
        };

        jQuery.ajax({
            url: doic.service_url,
            type: 'POST',
            timeout: 7000,
            data: data,
            success: function ($response) {
                console.dir($response);

                if ( $response.sucess === true ) {
                    $class.check_level($response.data.level_info);
                }

                return;
            },
            error: function ($error) {
                console.dir($error);
                // alert("Error: " + $error);
                return false;
            }
        });

        return false;
    },
    _bind_inputs: function() {
        "use strict";

        var $class = this;

        $class.recurring_button.on('click', function () {

            $class.want_recurring = jQuery(this).is(':checked');

            jQuery("#doic-hidden-recurring").val($class.want_recurring);

            if ($class.want_recurring === true) {
                location.reload();
            }

            $class._get_membership_level();
        });
    }
};

// Load & initiate the checkout JS.
jQuery(document).ready(function () {
    "use strict";

    doic_checkout.init();
});
