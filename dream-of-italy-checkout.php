<?php
/*
Plugin Name: Recurring Payment Checkout: Dream Of Italy
Plugin URI: http://eighty20results.com/
Description: Add recurring payment checkbox for membership levels
Version: 1.0
Author: Thomas Sjolshagen <thomas@eighty20results.com>
Author URI: http://eighty20results.com/thomas-sjolshagen/
License: Limited
*/
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
function doic_manage_recurring_payment()
{

    global $pmpro_review;

    // Only display this if the selected level is a recurring level
    if (false == $pmpro_review && ! isset($_REQUEST['ap'])/* && $pmpro_level->cycle_number >= 1 */) {
        ob_start(); ?>
        <div class="doic-recurring-payments clear-fix">
            <?php wp_nonce_field('doic-set-recurrence-for-level', 'doic-nonce'); ?>
            <input type="hidden" name="doic-hidden-recurring" id="doic-hidden-recurring" value="1">
            <input type="checkbox" class="doic-recurring-checkbox" value="0" name="doic-is-not-recurring" id="doic-is-not-recurring" <?php echo doic_is_checked(); ?>>
            <label for="doic-is-not-recurring" class="doic-recurring-label pmpro_clickable">
                <span><span></span></span>
                <?php _e('Keep this box checked if you would like your membership to autorenew when the current membership period ends. This way you will never miss an issue of <strong><em>Dream of Italy</em></strong>!', 'doiclang'); ?>
            </label>
        </div>
        <?php
    }
    $html = ob_get_clean();
    echo $html;
}
add_action('pmpro_checkout_after_level_cost', 'doic_manage_recurring_payment', 15);

function doic_is_checked() {
    global $pmpro_level;

    if (isset($pmpro_level->cycle_number) && $pmpro_level->cycle_number >= 1)
    {
        return 'checked="checked"';
    }

    return null;
}

function doic_checkout_level($level)
{
    global $discount_code;

    $nr_level = doic_findNonRecurringLevel($level);
    $renewable = 1;

    if (!empty($_REQUEST['ap']) || !empty($_SESSION['ap'])) {
        return $level;
    }

    if (!empty($discount_code)) {
        return $level;
    }

    $is_not_recurring = isset($_REQUEST['doic-is-not-recurring']) ? intval($_REQUEST['doic-is-not-recurring']) : 0;

    if (isset($_SESSION['doic-is-not-recurring'])) {
        $is_not_recurring = isset($_SESSION['doic-is-not-recurring']) ? intval($_SESSION['doic-is-not-recurring']) : 0;
    }

    $current_recurring = isset($_REQUEST['doic-hidden-recurring']) ? intval($_REQUEST['doic-hidden-recurring']) : 1;

    if (0 == $is_not_recurring && $current_recurring != 1) {

        if ( WP_DEBUG ) {
            error_log( "User wants recurring membership! - by REQUEST" );
        }

        $renewable = 0;
    }

    if ( 0 == $renewable && empty($nr_level)) {

        if (WP_DEBUG) {
            error_log("No mapped non-recurring level to use...");
        }

        $level->initial_payment = $level->billing_amount;
        $level->billing_amount = 0.00;
        $level->expiration_number = $level->cycle_number;
        $level->expiration_period = $level->cycle_period;
        $level->cycle_period = 'Day';
        $level->cycle_number = 0;

    } elseif ( 0 == $renewable && ! empty($nr_level->id)) {

        if (WP_DEBUG) {
            error_log("Using mapped non-recurring level");
        }
        
        $level->billing_amount = $nr_level->billing_amount;
        $level->initial_payment = $nr_level->initial_payment;
        $level->expiration_number = $nr_level->expiration_number;
        $level->expiration_period = $nr_level->expiration_period;
        $level->cycle_period = $nr_level->cycle_period;
        $level->cycle_number = $nr_level->cycle_number;
        $level->description = $nr_level->description;
        $level->confirmation = $nr_level->confirmation;
    }

    if (WP_DEBUG) {
        error_log("Modified Level: " . print_r($level, true));
    }

    return $level;
}
add_filter('pmpro_checkout_level', 'doic_checkout_level', 15);

// find the non-recurring level definition
function doic_findNonRecurringLevel($level)
{
    $level_map = get_option('doic_level_map', array());

    // Is the specified level included in the level map?
    if (isset($level_map[$level->id])) {

        if (WP_DEBUG) {
            error_log( "Found the appropriate level: {$level->id}" );
        }

        return pmpro_getLevel($level_map[$level->id]);
    }

    return false;
}

function doic_get_level()
{
    if (WP_DEBUG)
        error_log("Checkbox for recurring level being processed");

    check_ajax_referer('doic-set-recurrence-for-level', 'doic-nonce');

    // global $pmpro_level;

    $wants_recurring = isset($_REQUEST['doic-is-not-recurring']) ? ($_REQUEST['doic-is-not-recurring'] == 'true' ? true : false ) : false;
    $current_level_id = isset($_REQUEST['doic-current-level-id']) ? intval($_REQUEST['doic-current-level-id']) : false;

    if (WP_DEBUG)
        error_log("Current level: {$current_level_id} and wants_recurring: " . ($wants_recurring ? 'true' : 'false'));

    $current_level = pmpro_getLevel($current_level_id);

    if (false === $wants_recurring && null !== $current_level_id) {

        $mapped_level = doic_findNonRecurringLevel($current_level);
        // $pmpro_level = $current_level;

        if (false === $mapped_level) {
            if (WP_DEBUG)
                error_log("Unable to locate the non-recurring level for {$current_level_id}");

            wp_send_json_error();
            wp_die();
        }
    }

    $mapped_level = doic_level_settings($mapped_level);

    wp_send_json_success(array('level_info' => $mapped_level));
    wp_die();
}
add_action("wp_ajax_doic_get_level", 'doic_get_level');
add_action("wp_ajax_nopriv_doic_get_level", 'doic_get_level');

/**
 * Set session variables in support of using PayPal gatewway
 */
function doic_paypalexpress_session_vars()
{
    $_SESSION['doic-is-not-recurring'] = isset($_REQUEST['doic-is-not-recurring']) ? ($_REQUEST['doic-is-not-recurring'] == 'true' ? true : false ) : false;
    $_SESSION['doic-current-level-id'] = isset($_REQUEST['doic-current-level-id']) ? intval($_REQUEST['doic-current-level-id']) : false;
}
add_action('pmpro_paypalexpress_session_vars', 'doic_paypalexpress_session_vars');


function doic_level_settings($level)
{
    if (!empty($_REQUEST['review'])) {
        $level->form_action = ' action=\"' . pmpro_url( "checkout", "?level=" . $level->id ) . '"';
    }

    $level->allow_signups = ($level->allow_signups == 0) ? 1 : $level->allow_signups;
    $level->pricing_text = doic_pricing_info($level);
    return $level;
}

function doic_pricing_info( $level )
{
    global $discount_code;

    // $pmpro_level->name = str_ireplace('(Non-Recurring)', '', $level->name);

    ob_start(); ?>
            <div id="pmpro_level_cost">
                <?php if ($discount_code && pmpro_checkDiscountCode($discount_code)) { ?>
                    <?php printf(__('<p class="pmpro_level_discount_applied">The <strong>%s</strong> code has been applied to your order.</p>', 'pmpro'), $discount_code); ?>
                <?php } ?>
                <?php echo wpautop(pmpro_getLevelCost($level)); ?>
                <?php echo wpautop(pmpro_getLevelExpiration($level)); ?>
            </div>
    <?php

    $html = ob_get_clean();
    return $html;
}

/**
 * Load user side scripts & styles
 */
function load_doic_js()
{
    wp_enqueue_style('doic-styles', plugin_dir_url(__FILE__) . "css/doic-styles.css", array('pmpro_frontend'), '1.0');
    wp_register_script('doic-checkout', plugin_dir_url(__FILE__) . "js/doic-checkout.js", array('jquery'), '1.0');
    wp_localize_script('doic-checkout', 'doic', array('service_url' => admin_url('admin-ajax.php')));
    wp_enqueue_script('doic-checkout');
}
add_action('wp_enqueue_scripts', 'load_doic_js');

/**
 * Load admin scripts & styles
 */
function doic_enqueue_admin()
{
    wp_register_script('doic-admin', plugin_dir_url(__FILE__) . 'js/doic-admin.js', array('jquery'), '1.0');
    wp_enqueue_script('doic-admin');
    wp_enqueue_style('doic-admin', plugin_dir_url(__FILE__) . 'css/doic-admin.css', null, '1.0');
}
add_action('admin_enqueue_scripts', 'doic_enqueue_admin');

/**
 * Configure payment info as the membership level gets changed.
 *
 * @param $level_id - The ID of the PMPro Level to change to
 * @param $user_id - The user's ID
 */
function doic_before_change_membership_level($level_id, $user_id)
{
    //are we on the cancel page?
    global $pmpro_pages, $wpdb, $pmpro_stripe_event, $pmpro_next_payment_timestamp;

    if ($level_id == 0 && (is_page($pmpro_pages['cancel']) || (is_admin() && (empty($_REQUEST['from']) || $_REQUEST['from'] != 'profile')))) {
        //get last order
        $order = new MemberOrder();
        $order->getLastMemberOrder($user_id, "success");

        //if stripe or PayPal, try to use the API
        if (!empty($order) && $order->gateway == "stripe") {
            if (!empty($pmpro_stripe_event)) {
                //cancel initiated from Stripe webhook
                if (!empty($pmpro_stripe_event->data->object->current_period_end)) {
                    $pmpro_next_payment_timestamp = $pmpro_stripe_event->data->object->current_period_end;
                }
            } else {
                //cancel initiated from PMPro
                $pmpro_next_payment_timestamp = PMProGateway_stripe::pmpro_next_payment("", $user_id, "success");
            }
        } elseif (!empty($order) && $order->gateway == "paypalexpress") {
            if (!empty($_POST['next_payment_date']) && $_POST['next_payment_date'] != 'N/A') {
                //cancel initiated from IPN
                $pmpro_next_payment_timestamp = strtotime($_POST['next_payment_date'], current_time('timestamp'));
            } else {
                //cancel initiated from PMPro
                $pmpro_next_payment_timestamp = PMProGateway_paypalexpress::pmpro_next_payment("", $user_id, "success");
            }
        }
    }
}
add_action('pmpro_before_change_membership_level', 'doic_before_change_membership_level', 10, 2);

/**
 * Give users their level back with an expiration
 *
 * @param $level_id - The Level ID
 * @param $user_id - The User's ID
 * @return bool - Always returns nothing (false)
 */
function doic_after_change_membership_level($level_id, $user_id)
{
    //are we on the cancel page?
    global $pmpro_pages, $wpdb, $pmpro_next_payment_timestamp;
    if ($level_id == 0 && (is_page($pmpro_pages['cancel']) ||
            (is_admin() && (empty($_REQUEST['from']) || $_REQUEST['from'] != 'profile')))
    ) {
        /*
            okay, let's give the user his old level back with an expiration based on his subscription date
        */
        //get last order
        $order = new MemberOrder();
        $order->getLastMemberOrder($user_id, "cancelled");

        //can't do this if we can't find the order
        if (empty($order->id))
            return false;

        //get the last level they had
        $level = $wpdb->get_row("
				SELECT *
				FROM {$wpdb->pmpro_memberships_users}
				WHERE membership_id = '{$order->membership_id}' AND user_id = '{$user_id}'
				ORDER BY id
				DESC LIMIT 1
				"
        );

        //can't do this if the level isn't recurring
        if (empty($level->cycle_number))
            return false;

        //can't do if we can't find an old level
        if (empty($level))
            return false;

        //last payment date
        $lastdate = date("Y-m-d", $order->timestamp);

        /*
            next payment date
        */
        //if stripe or PayPal, try to use the API
        if (!empty($pmpro_next_payment_timestamp)) {
            $nextdate = $pmpro_next_payment_timestamp;
        } else {
            $nextdate = $wpdb->get_var("
				SELECT UNIX_TIMESTAMP('{$lastdate}' + INTERVAL {$level->cycle_number} {$level->cycle_period})
				"
            );
        }

        //if the date in the future?
        if ($nextdate - time() > 0) {
            //give them their level back with the expiration date set
            $old_level = $wpdb->get_row("
					SELECT *
					FROM {$wpdb->pmpro_memberships_users}
					WHERE membership_id = '{$order->membership_id}' AND
						user_id = '{$user_id}'
					ORDER BY id
					DESC LIMIT 1
					",
                ARRAY_A
            );
            $old_level['enddate'] = date("Y-m-d H:i:s", $nextdate);

            //disable this hook so we don't loop
            remove_action("pmpro_after_change_membership_level", "doic_after_change_membership_level", 10);
            remove_filter('pmpro_cancel_previous_subscriptions', 'my_pmpro_cancel_previous_subscriptions');

            //change level
            pmpro_changeMembershipLevel($old_level, $user_id);

            //add the action back just in case
            add_action("pmpro_after_change_membership_level", "doic_after_change_membership_level", 10, 2);
            add_filter('pmpro_cancel_previous_subscriptions', 'my_pmpro_cancel_previous_subscriptions');

            //change message shown on cancel page
            add_filter("gettext", "doic_gettext_cancel_text", 10, 3);
        }
    }
}
add_action("pmpro_after_change_membership_level", "doic_after_change_membership_level", 10, 2);

/**
 * Replace the cancellation text so people know they'll still have access for the time they've paid for
 * @param $translated_text
 * @param $text
 * @param $domain
 * @return string
 */
function doic_gettext_cancel_text($translated_text, $text, $domain)
{
    if ($domain == "pmpro" && $text == "Your membership has been cancelled.") {
        global $current_user;
        $translated_text = "Your recurring membership payments have been cancelled. Your active membership will expire on " . date(get_option("date_format"), pmpro_next_payment($current_user->ID, "cancelled")) . ".";
    }

    return $translated_text;
}

/**
 * Update the cancellations email message to reflect the expiration date/time.
 *
 * @param $body - The body of the email message (original body).
 * @param $email - the email address
 * @return string - Modified email body text
 */
function doic_email_body($body, $email)
{
    if ($email->template == "cancel") {
        global $wpdb;
        $user_id = $wpdb->get_var(
            $wpdb->prepare("
				SELECT ID
				FROM {$wpdb->users}
				WHERE user_email = %s LIMIT 1",
                $email->email
            )
        );

        if (!empty($user_id)) {
            $expiration_date = pmpro_next_payment($user_id);

            //if the date in the future?
            if ($expiration_date - time() > 0) {
                $body .= "<p>Your access will expire on " . date(get_option("date_format"), $expiration_date) . ".</p>";
            }
        }
    }

    return $body;
}
add_filter("pmpro_email_body", "doic_email_body", 10, 2);

/**
 * Process & load the admin page
 */
function doic_admin_page()
{

    // Process settings if needed.
    if (!empty($_REQUEST['save_doic_map'])) {

        // save it as a WP options array
        // update_option('doic_level_map', $level_map, false );
    }

    echo doic_load_page();
}

/**
 * Display the level Mapping for the admin page.
 *
 * @param $level_map (array) - Map of recurring to non-recurring membership levels.
 * @return string (html) - Table containing the mappings.
 */
function doic_display_level_map($level_map)
{
    $all_levels = pmpro_getAllLevels(true, true);

    ob_start(); ?>
    <div class="doic-center">
        <table id="doic-level-map">
            <thead>
            <tr>
                <th><?php _e("Level w/recurring billing", "doiclang"); ?></th>
                <th>&nbsp;</th>
                <th><?php _e("Level w/o recurring billing", "doiclang"); ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php
            if (!empty($level_map)) {
                foreach ($level_map as $r_id => $nr_id) {
                    $r = $all_levels[$r_id];
                    $nr = $all_levels[$nr_id]; ?>
                    <tr class="doic-level-map-row">
                        <td class="doic-level-map-recurring">
                            <input type="hidden" name="delete_map_recurring[]" class="delete_map_recurring"
                                   value="<?php echo $r->id; ?>">
                            <?php echo $r->name; ?>
                        </td>
                        <td class="doic-level-map-symbol">
                            &rightarrow;
                        </td>
                        <td class="doic-level-map-nonrecurring">
                            <input type="hidden" name="delete_map_nonrecurring[]" class="delete_map_nonrecurring"
                                   value="<?php echo $nr->id; ?>">
                            <?php echo $nr->name; ?>
                        </td>
                        <td class="doic-level-map-action"><a href="javascript:false;"
                                                             class="remove-map-entry"><?php _e("Remove", "doiclang"); ?></a>
                        </td>
                    </tr> <?php
                }
            } else { ?>
                <tr class="doic-level-map-row">
                <td colspan="4" style="text-align: center;"><?php _e("No mapped levels found", "doiclang"); ?></td>
                </tr><?php
            } ?>
            </tbody>
        </table>
    </div>
    <hr style="width: 100%;"/>
    <?php

    $html = ob_get_clean();
    return $html;
}

/**
 * Load the content for the Membership Level Mapping page
 *
 * @return string - HTML containing the level map editor
 */
function doic_load_page()
{

    ob_start();

    $all_levels = pmpro_getAllLevels(true, true);
    $level_map = get_option('doic_level_map', array());

    // render Membership Level mapping page content
    ?>
    <div id="doic-settings-div">
        <h2>Map Membership Levels</h2>
        <?php echo doic_display_level_map($level_map); ?>
        <form id="doic_settings">
            <?php wp_nonce_field('map-levels-list', 'doic-manage-nonce'); ?>
            <table id="doic-manage-level-entries">
                </tr>
                <th class="doic-heading"><?php _e("Level w/recurring billing", "doiclang"); ?>:</th>
                <th class="doic-heading"><?php _e("Level w/o recurring billing", "doiclang"); ?>:</th>
                <tr>
                    <td class="doic-recurring-list" style="vertical-align: text-top;">
                        <select id="doic-recurring-level" name="doic-recurring-level" class="doic-recurring-levels"
                                style="width: 300px; height: auto;">
                            <option
                                value="-1" <?php echo empty($level_map) ? 'selected="selected"' : null; ?>><?php _e("None", "doiclang"); ?></option>
                            <?php

                            if (!empty($all_levels))
                                foreach ($all_levels as $key => $l) {
                                    if ($l->cycle_number != 0) { ?>
                                        <option value="<?php echo $key; ?>"><?php echo $l->name; ?></option><?php
                                    }
                                }
                            ?>
                        </select>
                    </td>
                    <td class="doic-non-recurring-list" style="vertical-align: text-top;">
                        <select id="doic-nonrecurring-level" name="doic-nonrecurring-level"
                                class="doic-nonrecurring-levels" style="width: 300px; height: auto;">
                            <option
                                value="-1" <?php echo empty($level_map) ? 'selected="selected"' : null; ?>><?php _e("None", "doiclang"); ?></option>
                            <?php

                            if (!empty($all_levels))
                                foreach ($all_levels as $key => $l) {
                                    if ($l->cycle_number == 0) { ?>
                                        <option value="<?php echo $key; ?>"><?php echo $l->name; ?></option><?php
                                    }
                                }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <p class="submit">
                            <input id="add_map_entry" name="add_map_entry" type="submit" style="float: right;"
                                   class="button button-primary" value="<?php _e('Add mapping', 'doiclang'); ?>"/>
                        </p>
                    </td>
                    <!-- <td>
                        <p class="submit">
                            <input id="delete_dir_entry" style="float: left;" name="delete_dir_entry" type="submit" class="button button-secondary" value="<?php _e('Remove', 'doiclang'); ?>" />
                        </p>

                    </td> -->
                </tr>
            </table>
        </form>
    </div>
    <?php

    $html = ob_get_clean();

    return $html;
}

/**
 * Adds the received specialty to the specialties array.
 */
function doic_manage_mapping()
{
    check_ajax_referer('map-levels-list', 'doic-manage-nonce');

    $recurring = isset($_REQUEST['doic-recurring-level']) ? intval($_REQUEST['doic-recurring-level']) : null;
    $non_recurring = isset($_REQUEST['doic-nonrecurring-level']) ? intval($_REQUEST['doic-nonrecurring-level']) : null;
    $action = isset($_REQUEST['doic-action']) ? sanitize_text_field($_REQUEST['doic-action']) : null;

    if (empty($action) || empty($recurring) || empty($non_recurring)) {
        if (WP_DEBUG === true)
            error_log("Recurring: {$recurring} -> Non-Recurring: {$non_recurring}");

        wp_send_json_error();
    }

    $level_map = get_option('doic_level_map', array());

    if ('add' === $action) {
        if (!empty($recurring) && !empty($non_recurring)) {

            if (!in_array($non_recurring, $level_map)) {
                $level_map[$recurring] = $non_recurring;
            }
        }
    } elseif ('delete' === $action) {
        if (!empty($recurring)) {
            unset($level_map[$recurring]);
        }
    }

    if ((!empty($non_recurring) && !empty($recurring)) && update_option('doic_level_map', $level_map, false)) {
        wp_send_json_success(array('html' => doic_load_page()));
        wp_die();
    } else {
        wp_send_json_error(array('message' => __('Unable to save mapping. Is it a duplicate?', 'doiclang')));
        wp_die();
    }
}
add_action('wp_ajax_doic_manage_mapping', 'doic_manage_mapping');

/**
 * Returns error message to caller.
 */
function doic_unpriv()
{
    wp_send_json_error(array(
        'message' => __('You must be logged in to edit specialties', "doiclang")
    ));
    wp_die();
}
add_action('wp_ajax_nopriv_doic_manage_mapping', 'doic_unpriv');

/**
 * Define menu item & load admin page for wp-admin menu item
 */
function doic_loadAdminPage()
{

    if (!current_user_can('manage_options')) {
        if (WP_DEBUG)
            error_log("User doesn't have the right permissions to view the mapping menu item");
        wp_die(__("You do not have permission to perform this action.", "doiclang"));
    }

    if (WP_DEBUG)
        error_log("Loading menu item for membership level maps");

    add_submenu_page( 'pmpro-membershiplevels', __("Map Levels", "doiclang"), __("Map Levels", "doiclang"), 'manage_options', 'doic_map_levels', 'doic_admin_page');
}

add_action('admin_menu', 'doic_loadAdminPage', 11);

/**
 * Check that PMPro is loaded and active on the site.
 */
function doic_prereqs()
{

    //require PMPro
    if (defined('PMPRO_VERSION'))
        return;

    // PMPro is missing!
    ?>
    <div class="update-nag error notice">
    <p><?php _e("The Recurring Payments Checkbox add-on needs the Paid Memberships Pro plugin to be active", 'doiclang'); ?></p>
    </div><?php
}

add_action('admin_notices', 'doic_prereqs');