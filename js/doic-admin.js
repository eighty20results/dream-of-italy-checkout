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

var manageMappings = {
    init: function() {
        "use strict";

        // load controls
        this.recurring_list = jQuery('#pmproemd-add-skill');
        this.nrecurring_list = jQuery('#pmproemd-skill-list');
        this.add_button = jQuery('#add_map_entry');
        this.delete_buttons = jQuery(".remove-map-entry");

        // configure events
        this._bind_inputs();
    },
    _bind_inputs: function() {
        "use strict";

        var $class = this;

        $class.delete_buttons.each(function() {

            var $delete = jQuery(this);
            $delete.unbind('click').on('click', function() {

                var $row = $delete.closest('.doic-level-map-row');

                var r_id = $row.find('.delete_map_recurring').val();
                var nr_id = $row.find('.delete_map_nonrecurring').val();

                event.preventDefault();

                $class.delete_from_map(r_id, nr_id);
            });
        });

        $class.add_button.unbind('click').on('click', function() {

            event.preventDefault();

            $class.add_to_map();
        });
    },
    add_to_map: function() {
        "use strict";

        var $class = this;
        var $rid = jQuery("#doic-recurring-level").val();
        var $nrid = jQuery("#doic-nonrecurring-level").val();

        $class._send_ajax( 'add', $rid, $nrid );
    },
    delete_from_map: function( rid, nrid ) {
        "use strict";

        var $class = this;

        // var $rid = jQuery("#doic-recurring_level").val();
        // var $nrid = jQuery("#doic-nonrecurring-level").val();

        $class._send_ajax( 'delete', rid, nrid );
    },
    _send_ajax: function( $action, rid, nrid ) {
        "use strict";

        var $class = this;

        // transmit to backend (wp-admin)
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            timeout: 7000,
            dataType: 'JSON',
            data: {
                action: 'doic_manage_mapping',
                'doic-action': $action,
                'doic-manage-nonce': jQuery('#doic-manage-nonce').val(),
                'doic-recurring-level': rid,
                'doic-nonrecurring-level': nrid
            },
            error: function( xhr, textStatus, errorThrown ) {
                alert("Error: " + $action + " operation failed to update level map (" + textStatus + "): " + errorThrown );
                return false;
            },
            success: function($response) {
                console.dir($response);

                if (typeof $response.data !== 'undefined') {

                    if (typeof $response.data.message !== 'undefined') {
                        console.log($response.data.message);
                        alert($response.data.message);
                        return;
                    }

                    if (typeof $response.data.html !== 'undefined') {
                        jQuery('#doic-settings-div').html($response.data.html);
                        $class.init();
                    }

                }


                return;
            }
        });
    }
};

jQuery(document).ready(function() {
    "use strict";

    var mappings = manageMappings;
    mappings.init();
});