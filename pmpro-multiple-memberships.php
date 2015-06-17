<?php
/*
Plugin Name: PMPro Multiple Membership Levels
Plugin URI: http://www.internetfitnessincome.com
Description: Allows PMPro WordPress users to add additional levels to their customer membership
Version: 1.1
Author: Internet Fitness Income
Author URI: http://www.internetfitnessincome.com
*/


	// =============================================
	// Hide Levels From Levels Page, but still allow checkout for that level
	// =============================================
global $phl_hidden_levels;
$phl_hidden_levels = array(1,0);

/*
	Remove the levels from the levels page.
*/				
function phl_pmpro_levels_array($levels)
{
	global $phl_hidden_levels;
		
	$newarray = array();
	foreach($levels as $level)
	{
		if(!in_array($level->id, $phl_hidden_levels))
			$newarray[] = $level;
	}
	
	return $newarray;
}
add_filter("pmpro_levels_array", "phl_pmpro_levels_array");


	// ===================================================
	// These functions are used to grant access to addon levels
	// or check if a member has been added to an addon level
	// ===================================================
	
// this function adds the addon levels to the user meta data
// add the addon level to the user's meta as an array
function ifi_pmpro_add_addon_level_to_member($user_id, $lid)
{
	$ifi_user_addon_levels = get_user_meta($user_id, "ifi_membership_levels", true);
	$date = new DateTime($dateTimeString);
	$start_date = current_time("mysql");
	//$status = "active";
		//add the level id to the user
	if(is_array($ifi_user_addon_levels))
	{
		if(!in_array($lid, $ifi_user_addon_levels))
		{
			$ifi_user_addon_levels[] = array("lid" => $lid, "start_date" => $start_date);
		}
	}
	else
	{
		$ifi_user_addon_levels = array(array("lid" => $lid, "start_date" => $start_date));
	}	
		//save the user meta for access
	update_user_meta($user_id, "ifi_membership_levels", $ifi_user_addon_levels);
	
	//Trigger that user has been added. 
	do_action('ifi_pmproap_action_add_to_package', $user_id, $lid);	
}

// this function removes an addon membership level from the user data
// removes the addon level from the user's meta as an array
function ifi_pmpro_remove_addon_level_from_member($user_id, $lid)
{
	$ifi_user_addon_levels = get_user_meta($user_id, "ifi_membership_levels", true);
	//var_dump($lid);
	//var_dump($ifi_user_addon_levels);
	if(is_array($ifi_user_addon_levels))
	{
		$key = array_search($lid, array_column($ifi_user_addon_levels, 'lid'));
		//var_dump($key);
		unset($ifi_user_addon_levels[$key]);
		//var_dump($ifi_user_addon_levels);
		$ifi_user_addon_levels = array_values($ifi_user_addon_levels);
		//var_dump($ifi_user_addon_levels);
	}
	//die;
	//save the meta
	update_user_meta($user_id, "ifi_membership_levels", $ifi_user_addon_levels);
	
	//Trigger that user has been added. 
	do_action('ifi_pmproap_action_remove_from_package', $user_id, $lid);	
}


	
// this function determines whether they have access or not
function ifi_pmpro_has_membership_access_filter($hasaccess, $mypost, $myuser, $post_membership_levels)
{
global $current_user, $wpdb, $post;
	//use globals if no values supplied
	if(!$post_id && !empty($post))
		$post_id = $post->ID;
	if(!$user_id)
		$user_id = $current_user->ID;
		
	//no post, return true (changed from false in version 1.7.2)
	if(!$post_id)
		return true;
		
	//if no post or current_user object, set them up
	if(!empty($post->ID) && $post_id == $post->ID)
		$mypost = $post;
	else
		$mypost = get_post($post_id);

	if($user_id == $current_user->ID)
		$myuser = $current_user;
	else
		$myuser = get_userdata($user_id);

		
	//for these post types, we want to check the parent
	if($mypost->post_type == "attachment" || $mypost->post_type == "revision")
	{
		$mypost = get_post($mypost->post_parent);
	}

	if($mypost->post_type == "post") // if it's a post
	{
		$post_categories = wp_get_post_categories($mypost->ID);

		if(!$post_categories)
		{
			//just check for entries in the memberships_pages table
			$sqlQuery = "SELECT m.id, m.name FROM $wpdb->pmpro_memberships_pages mp LEFT JOIN $wpdb->pmpro_membership_levels m ON mp.membership_id = m.id WHERE mp.page_id = '" . $mypost->ID . "'";
		}
		else
		{
			//are any of the post categories associated with membership levels? also check the memberships_pages table
			$sqlQuery = "(SELECT m.id, m.name FROM $wpdb->pmpro_memberships_categories mc LEFT JOIN $wpdb->pmpro_membership_levels m ON mc.membership_id = m.id WHERE mc.category_id IN(" . implode(",", $post_categories) . ") AND m.id IS NOT NULL) UNION (SELECT m.id, m.name FROM $wpdb->pmpro_memberships_pages mp LEFT JOIN $wpdb->pmpro_membership_levels m ON mp.membership_id = m.id WHERE mp.page_id = '" . $mypost->ID . "')";
		}
	}
	else // it's a page, not a post
	{
		//are any membership levels associated with this page?
		$sqlQuery = "SELECT m.id, m.name FROM $wpdb->pmpro_memberships_pages mp LEFT JOIN $wpdb->pmpro_membership_levels m ON mp.membership_id = m.id WHERE mp.page_id = '" . $mypost->ID . "'";
	}

	$post_membership_levels = $wpdb->get_results($sqlQuery);

	if(!$post_membership_levels) // if no membership level is required
	{
		return true;
		//$hasaccess = true;
	}
	else // membership level is required for access
	{
		//levels found. check if this is in a feed or if the current user is in at least one of those membership levels
		if(is_feed())
		{
			//always block restricted feeds
			return false;

		}
		elseif(!empty($myuser->ID))
		{
			$ifi_levels = get_user_meta($myuser->ID, "ifi_membership_levels", true); // returns addon levels as array
			if(!is_array($ifi_levels)) // if not an array, turn it into one
			{
				$ifi_levels = array($ifi_levels);
			}
			foreach($ifi_levels as $ifi_level)
			{
				foreach($post_membership_levels as $post_membership_level)
					{
						foreach($post_membership_level as $key=>$value)
							{
							$$key = $value;
							}
						foreach($ifi_level as $key=>$value)
								{ 
								$$key = $value;
								}
						if($id == $lid)
						{
							//the users membership id is one that will grant access
							return true;
							//$hasaccess = true;
						}
						else
						{
							//user isn't a member of a level with access
							//return false;
							//$hasaccess = false;
						}
					}
			}
			//var_dump($post_membership_levels);
		}
		else
		{
			//user is not logged in and this content requires membership
			return false;
			//$hasaccess = false;
		}
	}

}
add_filter("pmpro_has_membership_access_filter", "ifi_pmpro_has_membership_access_filter");

/*
	Add lid to PayPal Express return url parameters
*/
function ifi_pmpro_paypal_express_return_url_parameters($params)
{
	if(!empty($_REQUEST['lid']))
		$params["lid"] = $_REQUEST['lid'];

	return $params;
}
add_filter("pmpro_paypal_express_return_url_parameters", "ifi_pmpro_paypal_express_return_url_parameters");


	// =============================================
	// Tweak the checkout page when lid is passed in.
	// =============================================
//update the level cost
function ifi_pmproap_pmpro_checkout_level($level)
{
	// get variables from URL
	$ifi_checkout_level = $_REQUEST['level'];
	$ifi_ap_sent = $_REQUEST['ap']; // gotta add stuff to check and make sure that ap is really in db
	$ifi_lid_sent = $_REQUEST['lid']; // gotta add stuff to check and make sure that lid is really in db
			
		// if we're on checkout for level 1, AND the ap AND the lid are empty, then do this...
		if($ifi_checkout_level == 1 && (empty($ifi_ap_sent)) && (empty($ifi_lid_sent)))
		{
		// redirect to checkout level 2 to prevent anyone
		// from signing up to free customer level without
		// actually becoming a customer
		header("Location: ?level=2/");
		exit();
		}
		else //if we're not on checkout for level 1...
		{
			// do nothing
		}
		
		// if we're on checkout for level 1, AND the lid is invalid, then do this...
				// Pull the add-on membership level's info from database
				global $wpdb;
				$ifi_level_count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->pmpro_membership_levels" ); // returns number of levels available

		if($ifi_checkout_level == 1 && $ifi_lid_sent > $ifi_level_count)
		{
		// redirect to checkout level 2 to prevent anyone
		// from signing up to free customer level without
		// actually becoming a customer
		header("Location: ?level=2/");
		exit();
		}
		else //if we're not on checkout for level 1...
		{
			// do nothing
		}



	//are we purchasing an additional membership level?
	if(!empty($_REQUEST['lid']))
	{
		$ifi_addon_lid = intval($_REQUEST['lid']);  // turns the URL's lid into a variable
			if(!empty($ifi_addon_lid))  // if that variable is not empty, do this
			{
				// Pull the add-on membership level's info from database
				global $wpdb;
				$ifi_addon_level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_membership_levels WHERE id = $ifi_addon_lid",ARRAY_A); // returns level info as array
			//var_dump($ifi_addon_level);
				// break the array into variables I can use
				foreach($ifi_addon_level as $key=>$value) { 
					$$key = $value;
					}
					if(pmpro_hasMembershipLevel($level->id)) // if they already have the membership for this checkout's level
					{
						// they already have this membership, so just add the add-on's price
						//is this level one-time purchase or recurring subscription?
							if($billing_amount > 0 || $trial_amount > 0)  // if billing amount or trial amount is greater than ZERO, it's a recurring purchase, so do this
							{
								// adjust the recurring subscription pricing
								$level->initial_payment = $initial_payment;
								$level->billing_amount = $billing_amount;
								$level->cycle_number = $cycle_number;
								$level->cycle_period = $cycle_period;
								$level->billing_limit = $billing_limit;
								$level->trial_amount = $trial_amount;
								$level->trial_limit = $trial_limit;
								$level->name = $name;
								$level->description = $description;
							}
							else // otherwise, it's a one-time purchase, so do this
							{
								// adjust the initial payment price dynamically
								$level->initial_payment = $initial_payment;
								$level->name = $name;
								$level->description = $description;
							}
						//don't unsubscribe to the old level after checkout
						if(!function_exists("pmproap_pmpro_cancel_previous_subscriptions"))
							{
							function pmproap_pmpro_cancel_previous_subscriptions($cancel)
							{
							return false;
							}
							}
							add_filter("pmpro_cancel_previous_subscriptions", "pmproap_pmpro_cancel_previous_subscriptions");
						
					}
					else //they don't already have the membership for this checkout's level
					{
						//add the lid price to the membership
						//is this level one-time purchase or recurring subscription?
							if($billing_amount > 0 || $trial_amount > 0)  // if billing amount or trial amount is greater than ZERO, it's a recurring purchase, so do this
							{
								// adjust the recurring subscription pricing
								$level->initial_payment = $initial_payment;
								$level->billing_amount = $billing_amount;
								$level->cycle_number = $cycle_number;
								$level->cycle_period = $cycle_period;
								$level->billing_limit = $billing_limit;
								$level->trial_amount = $trial_amount;
								$level->trial_limit = $trial_limit;
							}
							else // otherwise, it's a one-time purchase, so do this
							{
								// adjust the initial payment price dynamically
								$level->initial_payment = $initial_payment;
							}
						
						//update the name
						if(pmpro_hasMembershipLevel($level->id))
							$level->name = $name;
						else
							$level->name = $name;
							
						// update description
						if(pmpro_hasMembershipLevel($level->id))
							$level->description = $description;
						else
							$level->description = $description;
						


						//don't show the discount code field
						if(!function_exists("pmproap_pmpro_show_discount_code"))
						{
							function pmproap_pmpro_show_discount_code($show)
							{
								return false;
							}
						}
						add_filter("pmpro_show_discount_code", "pmproap_pmpro_show_discount_code");

						//add hidden input to carry lid value
						if(!function_exists("pmproap_pmpro_checkout_boxes"))
						{
							function pmproap_pmpro_checkout_boxes()
							{
								if(!empty($_REQUEST['lid']))
								{
								$lid = $_REQUEST['lid'];
								?>
									<input type="hidden" name="ifi_membership_levels" value="<?php echo $lid; ?>" />
								<?php
								}
							}
						}
						add_action("pmpro_checkout_boxes", "pmproap_pmpro_checkout_boxes");
						
					}
						// give member access after checkout
			if(!function_exists("ifi_pmpro_after_checkout"))
			{
				function ifi_pmpro_after_checkout($user_id)
				{
					global $lid;
					if(!empty($_SESSION['lid']))
					{
						$lid = $_SESSION['lid'];
						unsset($_SESSION['lid']);
					}
					elseif(!empty($_REQUEST['lid']))
					{
						$lid = $_REQUEST['lid'];
					}

					if(!empty($lid))
					{
						ifi_pmpro_add_addon_level_to_member($user_id, $lid);

						//update the confirmation url
						//if(!function_exists("pmproap_pmpro_confirmation_url"))
						//{
						//	function pmproap_pmpro_confirmation_url($url, $user_id, $level)
						//	{
						//		global $pmproap_ap;
						//		$url = add_query_arg("ap", $pmproap_ap, $url);
//
						//		return $url;
						//	}
						//}
						//add_filter("pmpro_confirmation_url", "pmproap_pmpro_confirmation_url", 10, 3);
					}
				}
			}
			add_action("pmpro_after_checkout", "ifi_pmpro_after_checkout");
			}
			else // the variable is empty, so do this
			{
			// woah, they passed a level id that doesn't exist
			// do nothing - just show level checkout as normal
			}
	}
	return $level;
}
add_filter("pmpro_checkout_level", "ifi_pmproap_pmpro_checkout_level");


	// ===================================================
	// These functions update things on the backend
	// ===================================================
	
/*
	Add invoices to user meta to store lid and order code
	so it can be pulled up on Account, Billing Info, and Invoice pages.
*/
function ifi_pmpro_added_lid_to_order($order)
{
	global $pmpro_pages;
		
		// Make sure this is a checkout page AND it has a lid
	if(is_page($pmpro_pages['checkout']) &&	!empty($_REQUEST['lid']))
	{		
		global $wpdb, $current_user;
			// get lid
		$level_lid = $_REQUEST['lid'];
			// get the customer invoices array for this user from user meta
		$user_id = $current_user->ID;
			// get user's previous invoices from user meta
		$ifi_previous_invoices = get_user_meta($user_id, "ifi_customer_invoices", true);
			// invoice data needs to be set as an array
		if(is_array($ifi_previous_invoices))
		{
			if(!in_array($lid, $ifi_previous_invoices))
			{
				$ifi_previous_invoices[] = array("lid" => $level_lid, "invoice_id" => $order->code, "sub_id" => $order->subscription_transaction_id);
			}
		}
		else
		{
			$ifi_previous_invoices = array(array("lid" => $level_lid, "invoice_id" => $order->code, "sub_id" => $order->subscription_transaction_id));
		}
		
		// save the user meta for invoices
		update_user_meta($user_id, "ifi_customer_invoices", $ifi_previous_invoices);
		
	}
	
	return $order;
}
add_filter('pmpro_added_order', 'ifi_pmpro_added_lid_to_order');

	
/*
	Add info on addon to notes section of order.
*/
function ifi_pmpro_added_order($order)
{
	global $pmpro_pages;
		
	if(is_page($pmpro_pages['checkout']) &&	!empty($_REQUEST['lid']))
	{		
		global $wpdb;
		$level_lid = $_REQUEST['lid'];
		$level_lid_info = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_membership_levels WHERE id = $level_lid",ARRAY_A); // returns level info as array
			foreach($level_lid_info as $key=>$value) { 
						$$key = $value;
						}
		$order->notes .= "Access Purchased For:  " . $name . "  (Level #:  " . $level_lid . ")\n";
		$sqlQuery = "UPDATE $wpdb->pmpro_membership_orders SET notes = '" . esc_sql($order->notes) . "' WHERE id = '" . $order->id . "' LIMIT 1";
		$wpdb->query($sqlQuery);
	}
	
	return $order;
}
add_filter('pmpro_added_order', 'ifi_pmpro_added_order');

/*
	Show purchased addon levels on the account page
*/
function ifi_pmpro_member_links_top()
{
	global $current_user;
	$ifilevel_ids = get_user_meta($current_user->ID, "ifi_membership_levels", true);
	if(is_array($ifilevel_ids))
	{
		foreach($ifilevel_ids as $ifilevel_id)
		{
			foreach($ifilevel_id as $key=>$value)
			{
				$$key = $value;
			}
				//$ifiaccesslevel = get_post($post_id);
				global $wpdb;
				$ulevel = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_membership_levels WHERE id = $lid",ARRAY_A); // returns level info as array
				foreach($ulevel as $key=>$value)
				{ 
					$$key = $value;
				}
				// 	use the level $id to find the member page url
				$level_id = $id;
				$level_member_url = ifi_getMemberPageURL($level_id);
			
				if(strpos($level_member_url, 'http') !== false) {
					?>
					<li><a href="<?php echo esc_url($level_member_url); ?>"><?php echo $name; ?></a></li>
					<?php
				}
				else
				?><li><a href="<?php echo esc_url( home_url($level_member_url)); ?>"><?php echo $name; ?></a></li>
			<?php
		}
	}
}
add_action("pmpro_member_links_top", "ifi_pmpro_member_links_top");

/*
	Show the purchased addon levels for each user on the edit user/profile page of the admin panel
*/
function ifi_profile_fields($user_id)
{
	global $wpdb;
	if(is_object($user_id))
		$user_id = $user_id->ID;

	if(!current_user_can("administrator"))
		return false;
?>
<h3><?php _e("Purchased Addon Levels", "pmproifi"); ?></h3>
<table class="form-table">
	<?php
		$user_ifilevels = get_user_meta($user_id, "ifi_membership_levels", true);
		//var_dump($user_ifilevels);
		//die;
		if(!empty($user_ifilevels))
		{
			foreach($user_ifilevels as $user_ifilevel)
			{
				foreach($user_ifilevel as $key=>$value)
					{
					$$key = $value;
					}
			?>
			<tr>
				<th></th>
				<td>
					<?php
					$ulevel = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_membership_levels WHERE id = $lid",ARRAY_A); // returns level info as array
					// break the array into variables I can use
					foreach($ulevel as $key=>$value) { 
						$$key = $value;
						}
				//var_dump($id);
				//die;
						?>
							<span id="ifi_remove_span_<?php echo $id;?>">
							<p><b><?php echo $name;?></b></p>
							&nbsp; <a style="color: red;" id="ifi_remove_<?php echo $id;?>" class="ifi_remove" href="javascript:void(0);">remove</a>
							</span>
						<?php
					?>
				</td>
			</tr>
			<?php
			}
		}
	?>
	<tr>
		<th>Give This User FREE Access To a Membership Level</th>
		<td>
			<input type="text" id="new_ifi_levels_1" name="new_ifi_levels[]" size="10" value="" /> <small>Enter a Membership Level ID</small>
		</td>
	</tr>
	<tr id="ifi_add_tr">
		<th></th>
		<td>
			<a id="ifi_add" href="javascript:void(0);">+ Add Another</a>
		</td>
	</tr>
</table>
<input type="hidden" id="remove_ifi_levels" name="remove_ifi_levels" value="" />
<script>
	var nifi_adds = 1;
	jQuery(function() {
		//to add another text input for a new membership level
		jQuery('#ifi_add').click(function() {
			nifi_adds++;
			jQuery('#ifi_add_tr').before('<tr><th></th><td><input type="text" id="new_ifi_levels_' + nifi_adds + '" name="new_ifi_levels[]" size="10" value="" /> <small>Enter a Membership Level ID</small></td></tr>');
		});

		//removing a package
		jQuery('.ifi_remove').click(function() {
			var thispost = jQuery(this);
			var thisid = thispost.attr('id').replace('ifi_remove_', '');

			//strike through the post
			jQuery('#ifi_remove_span_'+thisid).css('text-decoration', 'line-through');

			//add id to remove list
			jQuery('#remove_ifi_levels').val(jQuery('#remove_ifi_levels').val() + thisid + ',');
		});
	});
</script>
<?php
}
function ifi_profile_fields_update()
{
	if(isset($_REQUEST['new_ifi_levels']) || isset($_REQUEST['remove_ifi_levels']))
	{
		//get the user id
		global $wpdb, $current_user, $user_ID;
		get_currentuserinfo();

		if(!empty($_REQUEST['user_id']))
			$user_ID = $_REQUEST['user_id'];

		if(!current_user_can( 'edit_user', $user_ID))
			return false;

		//adding
		if(is_array($_REQUEST['new_ifi_levels']))
		{
			foreach($_REQUEST['new_ifi_levels'] as $lid)
			{
			//var_dump($lid);
			//var_dump($user_ID);
			//die;
				//$lid = intval($lid);
				if(!empty($lid))
				// add level to the array of membership levels in this member's metadata
				ifi_pmpro_add_addon_level_to_member($user_ID, $lid);
			}
		}

		//remove
		if(!empty($_REQUEST['remove_ifi_levels']))
		{
			//convert to array
			$remove_ifi_level_ids = explode(",", $_REQUEST['remove_ifi_levels']);
			foreach($remove_ifi_level_ids as $lid)
			{
				$lid = intval($lid);
				if(!empty($lid))
					ifi_pmpro_remove_addon_level_from_member($user_ID, $lid);
			}
		}
	}
}
add_action( 'show_user_profile', 'ifi_profile_fields' );
add_action( 'edit_user_profile', 'ifi_profile_fields' );
add_action( 'profile_update', 'ifi_profile_fields_update' );

// search function
function search($array, $key, $value)
{
    $results = array();

    if (is_array($array)) {
        if (isset($array[$key]) && $array[$key] == $value) {
            $results[] = $array;
        }

        foreach ($array as $subarray) {
            $results = array_merge($results, search($subarray, $key, $value));
        }
    }

    return $results;
}

// =========================================
// Adding Sales Page and Download Page URL
// fields to Membership Levels admin page
// =========================================

//add member download page url field to level settings admin page
function ifi_pmpro_membership_level_after_other_settings()
{
	$level_id = intval($_REQUEST['edit']);
	if($level_id > 0)
		{
		$level_member_url = ifi_getMemberPageURL($level_id);	
		$level_sales_url = ifi_getSalesPageURL($level_id);	
		}
	else
		{
		$level_member_url = "";
		$level_sales_url = "";
		}
?>
<h3 class="topborder">Download / Members Only Page URL</h3>
<p>Enter the URL of the download page (or members page) for this level.  A link to this URL will appear on your customer's Membership Account page so they have an easy way to access this level's protected content.</p>
<table>
<tbody class="form-table">
	<tr>
		<td>
			<tr>
				<th scope="row" valign="top"><label for="pmpro_level_member_url">Download Page / Members Only Page URL:</label></th>
				<td>
					<input name="pmpro_level_member_url" type="text" size="50" value="<?php echo esc_url($level_member_url);?>" />
					<br /><small>Accepts both absolute path URLs (that begin with "http://" or "https://")... OR relative path URLs (such as "/download-page/" or "/members-area/").  Absolute path URLs MUST begin with "http://" or "https://"... and relative path URLs MUST begin with a "/".</small>
					<br /><small>If left completely blank, the Member Links section of the Member Account page won't be working, clickable links.</small>
				</td>
			</tr>
		</td>
	</tr> 
</tbody>
</table>
<h3 class="topborder">Sales Letter / Offer Page URL</h3>
<p>Enter the URL of the sales letter or offer page for this level.  This is where people will be taken if they try to access this level's protected content.</p>
<table>
<tbody class="form-table">
	<tr>
		<td>
			<tr>
				<th scope="row" valign="top"><label for="level_sales_url">Sales Page URL:</label></th>
				<td>
					<input name="pmpro_level_sales_url" type="text" size="50" value="<?php echo esc_url($level_sales_url);?>">
					<br /><small>If completely blank, the default Levels page generated by PMPro will be used.</small>
				</td>
			</tr>
		</td>
	</tr> 
</tbody>
</table>
<?php
}
add_action("pmpro_membership_level_after_other_settings", "ifi_pmpro_membership_level_after_other_settings");

//save level URLs when the level is saved/added
function ifi_mm_pmpro_save_membership_level($level_id)
{
	ifi_saveLevelMemberPageURL($level_id, $_REQUEST['pmpro_level_member_url']);			//add level member page url for this level	
	ifi_saveLevelSalesPageURL($level_id, $_REQUEST['pmpro_level_sales_url']);			//add level sales page url for this level				
}
add_action("pmpro_save_membership_level", "ifi_mm_pmpro_save_membership_level");

/*	
	This function will save a level's member url into an array stored in pmpro_level_member_url
*/
function ifi_saveLevelMemberPageURL($level_id, $level_member_url)
{	
	$all_level_member_urls = get_option("pmpro_level_member_url", array());
		
	$all_level_member_urls[$level_id] = $level_member_url;
	
	update_option('pmpro_level_member_url', $all_level_member_urls);
}

/*	
	This function will save a level's sales page url into an array stored in pmpro_level_sales_url
*/
function ifi_saveLevelSalesPageURL($level_id, $level_sales_url)
{	
	$all_level_sales_urls = get_option("pmpro_level_sales_url", array());
		
	$all_level_sales_urls[$level_id] = $level_sales_url;
	
	update_option('pmpro_level_sales_url', $all_level_sales_urls);
}



/*
	This function will return the download page url for a level
*/
function ifi_getMemberPageURL($level_id)
{
	$all_member_page_url = get_option("pmpro_level_member_url", array());  // returns as array of all member urls
	
	if(!empty($all_member_page_url[$level_id]))
	{
		return $all_member_page_url[$level_id];
	}

	//didn't find it
	return "";
}

/*
	This function will return the sales page url for a level
*/
function ifi_getSalesPageURL($level_id)
{
	$all_sales_page_url = get_option("pmpro_level_sales_url", array());  // returns as array of all sales page urls
	
	if(!empty($all_sales_page_url[$level_id]))
	{
		return $all_sales_page_url[$level_id];
	}
	
	//didn't find it
	return "";
}



// ====================================
// hide My Subscriptions section of Account Page
// if the user doesn't have any subscriptions
// ====================================
function ifi_displaySubscriptions($ifi_user_id)
{
	global $wpdb; 
	$all_ifi_lids = get_user_meta($ifi_user_id, "ifi_membership_levels", true);
	if(is_array($all_ifi_lids))
		{
		foreach($all_ifi_lids as $ifi_lid)
			{
			foreach($ifi_lid as $key=>$value)
				{
				$$key = $value;
				}
			$ifi_level_info = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_membership_levels WHERE id = $lid",ARRAY_A); // returns level info as array
			foreach($ifi_level_info as $key=>$value)
				{ 
				$$key = $value;  // gives me the actual LEVEL id, name, description, $billing_amount, etc for this lid
								//var_dump($billing_amount);
				//echo "=== subs ===";
				if($billing_amount > 0 && billing_limit < 1)
					{
					return true;
					break;
					}
				
				}
				
			}
			
		}
	}


// ====================================
// hide My Payment Plans section of Account Page
// if the user doesn't have any psyment plans
// ====================================
function ifi_displayPaymentPlans($ifi_user_id)
{
	global $wpdb; 
	$all_ifi_lids2 = get_user_meta($ifi_user_id, "ifi_membership_levels", true);
	if(is_array($all_ifi_lids2))
		{
		foreach($all_ifi_lids2 as $ifi_lid2)
			{
			foreach($ifi_lid2 as $key=>$value)
			{
				$$key = $value;
			}
			$ifi_level_info2 = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_membership_levels WHERE id = $lid",ARRAY_A); // returns level info as array
			foreach($ifi_level_info2 as $key=>$value)
				{ 
				$$key = $value;  // gives me the actual LEVEL id, name, description, $billing_amount, etc for this lid
								//var_dump($billing_amount);
				//echo "=== payment plans ---";
				if($billing_amount > 0 && $billing_limit > 0)
					{
					return true;
					break;
					}
				}
			}
			
		}
	}
