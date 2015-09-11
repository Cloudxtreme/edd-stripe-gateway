<?php
/*
Plugin Name: Easy Digital Downloads - Stripe Checkout Gateway
Plugin URL: http://halgatewood.com/downloads/stripe-checkout-gateway
Description: The simple Stripe gateway for Easy Digital Downloads
Version: 1.5
Author: Hal Gatewood
Author URI: http://halgatewood.com
Contributors: halgatewood

FILTERS AVAILABLE:
edd_stripe_checkout_button_label: Checkout Button Text
edd_stripe_checkout_image: Image found in the stripe popup. Default looks in your theme/images/stripe-logo.png
*/



// REGISTER THE GATEWAY
function hg_stripe_edd_register_gateway($gateways) {
	global $edd_options;
	
	$checkout_label = __('Stripe', 'hg_edd');
	if( isset( $edd_options['stripe_checkout_label'] ) AND $edd_options['stripe_checkout_label'] != "") 
	{
		$checkout_label = $edd_options['stripe_checkout_label'];
	}
	
	$gateways['stripe_checkout'] = array( 'admin_label' => 'Stripe Checkout', 'checkout_label' => $checkout_label );
	return $gateways;
}
add_filter('edd_payment_gateways', 'hg_stripe_edd_register_gateway');


// STRIPE CHECKOUT JS
function hg_stripe_edd_checkout_js() {
	global $edd_options;

	$gateways = edd_get_enabled_payment_gateways();

	if( isset($gateways['stripe_checkout']) AND get_the_ID() == $edd_options['purchase_page'] ) 
	{
		wp_enqueue_script( 'stripe', 'https://checkout.stripe.com/checkout.js');
	}

}
add_action('wp_enqueue_scripts', 'hg_stripe_edd_checkout_js' );


// CHECKOUT FORM
add_action( 'edd_stripe_checkout_cc_form', 'hg_stripe_checkout_cc_form' );
function hg_stripe_checkout_cc_form( ) {

	global $edd_options;

	ob_start();
	
	// GET THE STRIPE PUBLISHABLE KEY DEPENDING ON TEST MODE
	$stripe_publishable = edd_is_test_mode() ? $edd_options['test_publishable_key'] : $edd_options['live_publishable_key'];
	
	// CHECKOUT BUTTON LABEL
	$checkout_button_label = apply_filters('edd_stripe_checkout_button_label', __('Pay Now with Credit Card', 'hg_edd') );
 
 	// CHECK SETTINGS FOR AN OVERRIDE OF THE LABEL TEXT
	if( isset( $edd_options['stripe_checkout_button_label'] ) AND $edd_options['stripe_checkout_button_label'] != "") {
		$checkout_button_label = $edd_options['stripe_checkout_button_label'];
	}
	
	// HIDE REMEMBER ME BOX
	$remember_me_box = 'true';
	if( isset( $edd_options['hide_stripe_remember_me_box'] ) AND $edd_options['hide_stripe_remember_me_box'] == 1) {
		$remember_me_box = 'false';
	}
	
	// BILLING ADDRESS
	$use_billing_address = 'false';
	if( isset( $edd_options['require_billing_address'] ) AND $edd_options['require_billing_address'] == 1) {
		$use_billing_address = 'true';
	}
	
	// SHIPPING ADDRESS
	$use_shipping_address = 'false';
	if( isset( $edd_options['require_shipping_address'] ) AND $edd_options['require_shipping_address'] == 1) {
		$use_shipping_address = 'true';
	}

	// STRIPE POPUP IMAGE
	$stripe_popup_image = false;
	if( isset( $edd_options['stripe_checkout_popup_image'] ) AND $edd_options['stripe_checkout_popup_image'] != "") 
	{
		$stripe_popup_image = $edd_options['stripe_checkout_popup_image'];
	}
	
	// CHECK STYLESHEET
	if( !$stripe_popup_image AND file_exists( get_stylesheet_directory() . "/images/stripe-logo.png") )
	{
		$stripe_popup_image = get_stylesheet_directory_uri() . "/images/stripe-logo.png";
	}
	
	// USE GRAVATAR FROM ADMIN EMAIL
	if( !$stripe_popup_image )
	{
		$stripe_popup_image = "https://www.gravatar.com/avatar/" . md5(strtolower( get_option('admin_email') ) ) . "?s=256&d=blank"; 
	}
	
	// FILTER THE IMAGE AS A LAST RESORT
	$stripe_popup_image = apply_filters('edd_stripe_checkout_image', $stripe_popup_image);
	
	
	// GET REQUIRED FIELDS, CHECKS FOR REQUIRED LAST NAME
	$required_fields = edd_purchase_form_required_fields();



	$color = isset( $edd_options[ 'checkout_color' ] ) ? $edd_options[ 'checkout_color' ] : 'blue';
	$color = ( $color == 'inherit' ) ? '' : $color;
	$style = isset( $edd_options[ 'button_style' ] ) ? $edd_options[ 'button_style' ] : 'button';

	// HIDE STRIPE BUTTON 
	$show_stripe_button = true;
	if( isset( $edd_options['hide_stripe_checkout_button'] ) AND $edd_options['hide_stripe_checkout_button'] == 1) {
		$show_stripe_button = false;
	}
	
	// HIDE EDD BUTTON
	$hide_edd_button = false;
	if( isset( $edd_options['hide_edd_checkout_button'] ) AND $edd_options['hide_edd_checkout_button'] == 1) {
		$hide_edd_button = true;
	}
	

	// jQuery Selector
	$jQuery_selector = "#stripe-button, #edd-purchase-button";
	if( isset($edd_options['stripe_checkout_jquery_selector']) AND trim($edd_options['stripe_checkout_jquery_selector']) != "" )
	{
		$jQuery_selector = trim($edd_options['stripe_checkout_jquery_selector']);
	}

?>

	<style>
		#edd_cc_address,
		#edd-purchase-button,
		#edd_final_total_wrap { display: none; }
		#stripe-button { margin: 20px 0; }
		#edd_terms_agreement.error { border: solid 1px #c4554e; }
	</style>
	
	<input type="hidden" id="stripeToken" name="stripeToken" value="" />
	
	<?php if( $show_stripe_button ) { ?>
	<button id="stripe-button" class="button edd-submit <?php echo $color; ?> <?php echo $style; ?>"><?php echo $checkout_button_label; ?></button>
	<?php } ?>
	
	<script>
		
		<?php if( $hide_edd_button )  { ?>
		jQuery("#edd-purchase-button").hide();
		<?php } ?>
	
		var pop_checkout = true;
	
		// CLASS FUNCTIONS FROM
		// http://www.avoid.org/javascript-addclassremoveclass-functions/
		function edd_hg_stripe_hasClass(el, name) 
		{
		   return new RegExp('(\\s|^)'+name+'(\\s|$)').test(el.className);
		}
		
		function edd_hg_stripe_addClass(el, name)
		{
		   if (!edd_hg_stripe_hasClass(el, name)) { el.className += (el.className ? ' ' : '') +name; }
		}
	
		function edd_hg_stripe_removeClass(el, name)
		{
		   if (edd_hg_stripe_hasClass(el, name)) 
		   {
		      el.className=el.className.replace(new RegExp('(\\s|^)'+name+'(\\s|$)'),' ').replace(/^\s+|\s+$/g, '');
		   }
		}
		
		// SET ERROR MESSAGES AND IF WE CAN DO THE STRIPE POPUP
		function edd_hg_allow_popup( element_id )
		{
			var this_input = document.getElementById( element_id );
			if( !this_input ) return false;
			var this_value = this_input.value.trim();
			if(this_value == "")
			{
				pop_checkout = false;
				edd_hg_stripe_addClass( this_input, 'error' );
			}
			else
			{
				edd_hg_stripe_removeClass( this_input, 'error' );
			}
			return true;
		}
	
		var handler = StripeCheckout.configure(
		{
			key: '<?php echo $stripe_publishable; ?>',
			token: function(token, args) 
			{
		  		document.getElementById('stripeToken').value = token.id;
		  		document.getElementById('edd_purchase_form').submit();
			}
		});
		
		jQuery('<?php echo $jQuery_selector ?>').unbind('click');
		jQuery('<?php echo $jQuery_selector ?>').on('click', function(e) 
		{
			e.preventDefault();
			pop_checkout = true;
			
			// CHECK EMAIL
			var email_input = document.getElementById('edd-email');
			edd_hg_allow_popup('edd-email');
			
			// CHECK FIRST NAME
			edd_hg_allow_popup('edd-first');
			
			<?php if( isset($required_fields['edd_last'])) { ?>
			
				// CHECK LAST NAME
				edd_hg_allow_popup('edd-last');
			
			<?php } ?>
			

			<?php if( isset($edd_options['show_agree_to_terms']) AND $edd_options['show_agree_to_terms'] ) { ?>
				
				// TERMS OF SERVICE
				var terms_of_service 				= document.getElementById('edd_agree_to_terms');
				var terms_of_service_agreement 		= document.getElementById('edd_terms_agreement');
				if( terms_of_service && !terms_of_service.checked )
				{
					pop_checkout = false;
					edd_hg_stripe_addClass( terms_of_service_agreement, 'error' );
				}
				else
				{
					edd_hg_stripe_removeClass( terms_of_service_agreement, 'error' );
				}
				
			<?php } ?>
			
			<?php if( 
						isset($edd_options['show_register_form']) AND 
						isset($edd_options['logged_in_only']) AND
						$edd_options['show_register_form'] == 1 AND 
						$edd_options['logged_in_only'] == 1
					) 
				{ ?>
				
				// CHECK USER NAME
				edd_hg_allow_popup('edd_user_login');
				edd_hg_allow_popup('edd_user_pass');
				edd_hg_allow_popup('edd_user_pass_confirm');
				
		
			<?php } ?>
		
			if(pop_checkout)
			{
				// Open Checkout with further options
				handler.open({
				  image: '<?php echo $stripe_popup_image; ?>',
				  name: '<?php echo (isset($edd_options['stripe_checkout_popup_title']) AND $edd_options['stripe_checkout_popup_title'] != "") ? str_replace("'","\'", stripslashes($edd_options['stripe_checkout_popup_title'])) : str_replace("'","\'", stripslashes(get_bloginfo('name'))); ?>',
				  description: '<?php if(isset($edd_options['stripe_checkout_popup_description'])) echo str_replace("'","\'", stripslashes($edd_options['stripe_checkout_popup_description'])); ?>',
				  currency: '<?php echo $edd_options['currency']; ?>',
				  allowRememberMe: <?php echo $remember_me_box; ?>,
				  billingAddress: <?php echo $use_billing_address; ?>,
				  shippingAddress: <?php echo $use_shipping_address; ?>,
				  email: email_input.value
				});
			}
		});
	</script>

<?php
	echo ob_get_clean();
}
 
// PROCESS PAYMENT
add_action('edd_gateway_stripe_checkout', 'hg_stripe_edd_process_payment');
function hg_stripe_edd_process_payment( $purchase_data ) {

	global $edd_options;
 
	// STRIPE SECRET KEY
	$stripe_secret = edd_is_test_mode() ? $edd_options['test_secret_key'] : $edd_options['live_secret_key'];
 
 	// LOAD STRIPE API
 	require_once( dirname(__FILE__) . '/lib/Stripe.php' );
	Stripe::setApiKey( $stripe_secret );
 
 	// GET STRIPE TOKEN
 	$token = $_POST['stripeToken'];

	// ERROR IF NO TOKEN
	if(!$token) {
		edd_set_error( 'stripe_payment_error', __('Stripe error. Payment Token Not Found. Please try again', 'hg_edd') );
	}
 
	// IF NO ERRORS PROCEED
	$errors = edd_get_errors();
	if(!$errors) {
	
		$purchase_summary = edd_get_purchase_summary($purchase_data);
 
 		// PAYMENT DETAILS
		$payment = array( 
			'price' 				=> $purchase_data['price'], 
			'date' 					=> $purchase_data['date'], 
			'user_email' 			=> $purchase_data['user_email'],
			'purchase_key' 			=> $purchase_data['purchase_key'],
			'currency' 				=> $edd_options['currency'],
			'downloads' 			=> $purchase_data['downloads'],
			'cart_details' 			=> $purchase_data['cart_details'],
			'user_info' 			=> $purchase_data['user_info'],
			'status' 				=> 'pending'
		);
 
 
		// record the pending payment
		$payment = edd_insert_payment($payment);
		
		$merchant_payment_confirmed = false;
 
		$pennies = $purchase_data['price'] * 100;
		
		try 
		{
			
			// CREATE A NEW STRIPE CUSTOMER
			$customer = Stripe_Customer::create(array(
			  "email" => $purchase_data['user_email'],
			  "card" => $token
			));

			// CHARGE THIS CUSTOMER
			$charge = Stripe_Charge::create(array(
				"amount" => $pennies,
				"currency" => $edd_options['currency'],
				"customer" => $customer->id,
				"description" => __('Order ID: ', 'hg_edd') . $payment
			));
			
			$merchant_payment_confirmed = true;
		} 
		catch (Stripe_ApiConnectionError $e) 
		{
			$merchant_payment_confirmed = false;
			
			$e_json 	= $e->getJsonBody();
			$error 		= $e_json['error'];
			if( $error['message'] )
			{
				edd_set_error( 'stripe_payment_error', $error['message'] );
			}
			else
			{
				edd_set_error( 'stripe_payment_error', __('The payment service cannon be reached. Please try again.', 'hg_edd') );
			}
		}
		catch (Stripe_InvalidRequestError $e) 
		{
			$merchant_payment_confirmed = false;
			
			$e_json 	= $e->getJsonBody();
			$error 		= $e_json['error'];
			if( $error['message'] )
			{
				edd_set_error( 'stripe_payment_error', $error['message'] );
			}
			else
			{
				edd_set_error( 'stripe_payment_error', __('The Stripe payment system has not been setup properly. Please contact the website administrator.', 'hg_edd') );
			}
		}
		catch (Stripe_ApiError $e) 
		{
			$merchant_payment_confirmed = false;
			
			$e_json 	= $e->getJsonBody();
			$error 		= $e_json['error'];
			if( $error['message'] )
			{
				edd_set_error( 'stripe_payment_error', $error['message'] );
			}
			else
			{
				edd_set_error( 'stripe_payment_error', __('The payment service cannon be reached. Please try again.', 'hg_edd') );
			}
		}
		catch(Stripe_CardError $e) 
		{
			$merchant_payment_confirmed = false;
			
			$e_json 	= $e->getJsonBody();
			$error 		= $e_json['error'];
			
			if( $error['message'] )
			{
				edd_set_error( 'stripe_payment_error', $error['message'] );
			}
			else
			{
				edd_set_error( 'stripe_payment_error', __('Credit Card Error. Please try again or try another card.', 'hg_edd') );
			}
		}
 
 		// PAYMENT CONFIRMED
		if($merchant_payment_confirmed) 
		{
			// once a transaction is successful, set the purchase to complete
			edd_update_payment_status($payment, 'complete');
 
			// go to the success page			
			edd_send_to_success_page();
		} 
		else 
		{
			$fail = true; // payment wasn't recorded
		}
	} 
	else 
	{
		$fail = true; // errors were detected
	}
 
	if( $fail !== false ) 
	{
		// if errors are present, send the user back to the purchase page so they can be corrected
		edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
	}
}

 

// EDD STRIPE SETTINGS
function hg_stripe_edd_add_settings($settings) {

 	$hg_stripe_gateway_settings = array(
		array(
			'id' => 'stripe_gateway_settings',
			'name' => '<strong>' . __('Stripe Gateway Settings', 'hg_edd') . '</strong>',
			'desc' => __('Configure the Stripe settings', 'hg_edd'),
			'type' => 'header'
		),
		array(
			'id' => 'hg_updater_email_account',
			'name' => __('Register for Updates', 'hg_edd'),
			'desc' => __('This is used to update your plugin when new versions become available. Insert the email address you used to purchase this plugin.', 'hg_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'test_secret_key',
			'name' => __('Test Secret Key', 'hg_edd'),
			'desc' => __('Enter your test secret key, found in your <a href="https://manage.stripe.com/account/apikeys" target="_blank">Stripe Account Settings</a>', 'hg_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'test_publishable_key',
			'name' => __('Test Publishable Key', 'hg_edd'),
			'desc' => __('Enter your test publishable key, found in your <a href="https://manage.stripe.com/account/apikeys" target="_blank">Stripe Account Settings</a>', 'hg_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'live_secret_key',
			'name' => __('Live Secret Key', 'hg_edd'),
			'desc' => __('Enter your live secret key, found in your <a href="https://manage.stripe.com/account/apikeys" target="_blank">Stripe Account Settings</a>', 'hg_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'live_publishable_key',
			'name' => __('Live Publishable Key', 'hg_edd'),
			'desc' => __('Enter your live publishable key, found in your <a href="https://manage.stripe.com/account/apikeys" target="_blank">Stripe Account Settings</a>', 'hg_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'stripe_checkout_button_label',
			'name' => __('Checkout Button Label', 'hg_edd'),
			'desc' => __('Text found on the checkout button on the checkout page.', 'hg_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'stripe_checkout_popup_image',
			'name' => __('Stripe Checkout Popup Image', 'hg_edd'),
			'desc' => __('URL of the square image found in the checkout popup at the top. Minimum size of 128px.', 'hg_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'stripe_checkout_popup_title',
			'name' => __('Stripe Checkout Popup Title', 'hg_edd'),
			'desc' => __('Title text found in the checkout popup below the image.', 'hg_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'stripe_checkout_popup_description',
			'name' => __('Stripe Checkout Popup Description', 'hg_edd'),
			'desc' => __('Text found in the checkout popup below the title.', 'hg_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'stripe_checkout_label',
			'name' => __('Stripe Checkout Multiple Gateways Label', 'hg_edd'),
			'desc' => __('This is the text found next to the radio buttons on the checkout page when there are multiple gateways to choose from. The default is "Stripe" but you may want to change this to "Credit Card"', 'hg_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'require_billing_address',
			'name' => __('Require Billing Address', 'hg_edd'),
			'desc' => __('This will require the user to fill out their billing address in the Stripe Popup', 'hg_edd'),
			'type' => 'checkbox',
			'size' => 'regular'
		),
		array(
			'id' => 'require_shipping_address',
			'name' => __('Require Shipping Address', 'hg_edd'),
			'desc' => __('This will require the user to fill out their shipping address in the Stripe Popup. If this is required Stripe will automatically make the billing required too.', 'hg_edd'),
			'type' => 'checkbox',
			'size' => 'regular'
		),
		array(
			'id' => 'hide_stripe_remember_me_box',
			'name' => __('Hide the Stripe Remember Me Box', 'hg_edd'),
			'desc' => __('This will hide the Remember Me functionality inside the Stripe Popup', 'hg_edd'),
			'type' => 'checkbox',
			'size' => 'regular'
		),
		array(
			'id' => 'hide_stripe_checkout_button',
			'name' => __('Hide the Stripe Checkout Button', 'hg_edd'),
			'desc' => __('Some themes have there own checkout buttons. This allows you to hide the one included with this plugin and use the default one. The jQuery selector setting can help.', 'hg_edd'),
			'type' => 'checkbox',
			'size' => 'regular'
		),
		array(
			'id' => 'hide_edd_checkout_button',
			'name' => __('Hide the EDD Button', 'hg_edd'),
			'desc' => __('Some themes have there own checkout buttons. This allows you to hide the one included with EDD in favor of mine. The jQuery selector setting can help.', 'hg_edd'),
			'type' => 'checkbox',
			'size' => 'regular'
		),
		array(
			'id' => 'stripe_checkout_jquery_selector',
			'name' => __('Stripe Checkout Button Selector', 'hg_edd'),
			'desc' => __('This setting is used to determine what targets will initiate the onclick for the Stripe Checkout popup. Default is #stripe-button.', 'hg_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
	);
 
	return array_merge($settings, $hg_stripe_gateway_settings);	
}
add_filter('edd_settings_gateways', 'hg_stripe_edd_add_settings');
