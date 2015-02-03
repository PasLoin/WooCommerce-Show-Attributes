<?php
/*
Plugin Name: WooCommerce Show Attributes
Plugin URI: http://isabelcastillo.com/docs/category/woocommerce-show-attributes
Description: Show WooCommerce custom product attributes on the Product, Shop and Cart pages, admin Order Details page and emails.
Version: 1.4.0-beta-1.16
Author: Isabel Castillo
Author URI: http://isabelcastillo.com
License: GPL2
Text Domain: woocommerce-show-attributes
Domain Path: languages

Copyright 2014 - 2015 Isabel Castillo

WooCommerce Show Attributes is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

WooCommerce Show Attributes is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNSES FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with WooCommerce Show Attributes; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*/

// @todo doc in my docs and in readme and in installation steps and faq:
// settings have moved to     WC Show Attributes .
// @todo note in readme

// if visibility setting for a custom attribute is not checked, then it will now be shown by this plugin. Not on the product pages, nor on the emails, nor in the admin order page.

// I realize that many of you need more granular control over where to show the attributes....

class WooCommerce_Show_Attributes {

	private static $instance = null;

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'show_atts_on_product_page' ), 25);
		add_filter( 'woocommerce_product_tabs', array( $this, 'additional_info_tab' ), 98 );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'show_atts_on_cart' ), 10, 2 );
		add_filter( 'woocommerce_order_item_name', array( $this, 'show_atts_on_customer_order' ), 99, 2 );
		add_filter( 'woocommerce_order_item_name', array( $this, 'show_admin_new_order_email' ), 99, 2 );
		add_action( 'woocommerce_admin_order_item_values', array( $this, 'show_atts_in_admin_order'), 10, 3 );
		add_action( 'woocommerce_admin_order_item_headers', array( $this, 'admin_order_item_header' ) );
		add_filter( 'woocommerce_get_settings_products', array( $this, 'all_settings' ), 10, 2 );
		add_filter( 'woocommerce_get_sections_products', array( $this, 'add_section' ) );
		add_action( 'init', array( $this, 'if_show_atts_on_shop' ) );
		add_action( 'woocommerce_grouped_product_list_before_price', array( $this, 'show_atts_grouped_product' ) );
	
	}

	/**
	* Load plugin's textdomain
	*/
	public function load_textdomain() {
		load_plugin_textdomain( 'woocommerce-show-attributes', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
	
	/**
	* Show the attributes.
	* 
	* Returns the HTML string for the custom product attributes.
	* This does not affect nor include attributes which are used for Variations.
	* 
	* @param object $product The product object.
	* @param string $element HTML element to wrap each attribute with, accepts span or li.
	*/
	public function the_attributes( $product, $element ) {
	   
		$attributes = $product->get_attributes();
	   
		if ( ! $attributes ) {
			return;
		}

		$hide_labels = get_option( 'woocommerce_show_attributes_hide_labels' );
		
		// check if they choose span element over li
		if ( get_option( 'woocommerce_show_attributes_span' ) == 'yes' ) {
			$element = 'span';
		}

		$out = ('li' == $element) ? '<ul ' : '<span ';
		
		$out .= 'class="custom-attributes">';
		
		foreach ( $attributes as $attribute ) {
		
			// skip variations
			if ( $attribute['is_variation'] ) {
				continue;
			}
				
			// honor the visibility setting
			if ( ! $attribute['is_visible'] ) {
				continue;
			}

			if ( $attribute['is_taxonomy'] ) {
				
				$terms = wp_get_post_terms( $product->id, $attribute['name'], 'all' );
				if ( ! empty( $terms ) ) {
					if ( ! is_wp_error( $terms ) ) {
	
						// get the taxonomy
						$tax = $terms[0]->taxonomy;
						 
						// get the tax object
						$tax_object = get_taxonomy($tax);
						 
						// get tax label
						if ( isset ($tax_object->labels->name) ) {
							$tax_label = $tax_object->labels->name;
						} elseif ( isset( $tax_object->label ) ) {
							$tax_label = $tax_object->label;
						}
						 
						foreach ( $terms as $term ) {
						
							$out .= '<' . $element . ' class="' . esc_attr( $attribute['name'] ) . ' ' . esc_attr( $term->slug ) . '">';
							// Hide labels if they want to
							if ( $hide_labels != 'yes' ) {
								$out .= '<span class="attribute-label">' . sprintf( __( '%s', 'woocommerce-show-attributes' ), $tax_label ) . ': </span> ';
							}
							$out .= '<span class="attribute-value">' . $term->name . '</span></' . $element . '>';
							if ('span' == $element) {
								$out .= '<br />';
							}
						  
						}
					}
				}
				   
			} else {
			
				$out .= '<' . $element. ' class="' . sanitize_title($attribute['name']) . ' ' . sanitize_title($attribute['value']) . '">';
				
				// Hide labels if they want to
				if ( $hide_labels != 'yes' ) {
					$out .= '<span class="attribute-label">' . $attribute['name'] . ': </span> ';
				}
				$out .= '<span class="attribute-value">' . $attribute['value'] . '</span></' . $element. '>';
				if ('span' == $element) {
					$out .= '<br />';
				}

			}
		}
		$out .= ('li' == $element) ? '</ul>' : '</span>';
		
		if ('span' == $element) {
			$out .= '<br />';
		}

		return $out;
	}

	/**
	* Show product attributes on the product page.
	*
	* Show product attributes above the Add to Cart button on the single product page
	* and on the Parent of Grouped products.
	*/

	public function show_atts_on_product_page() {
		if ( get_option( 'wcsa_product' ) != 'no') {
			global $product;
			echo $this->the_attributes( $product, 'li' );
		}
	}

	/**
	* Show attributes on Customer emails and View Order page.
	* 
	* Show product attributes below the item on the View Order page, 
	* and on Order emails that go to customer.
	*
	* @param string $item_name the product title
	* @param object $item the product object
	* @since 1.4.0
	*/
	public function show_atts_on_customer_order( $item_name, $item ) {

		$out = $item_name;

		if ( get_option( 'wcsa_customer_order_emails' ) != 'no' ) {

			// get the name of templates used for this email call
			$templates_used = array();
			foreach ( debug_backtrace() as $called_file ) {
				foreach ( $called_file as $index ) {
					if ( is_array( $index ) ) { // avoid errors
						if ( ! empty( $index[0] ) ) {
							if ( is_string( $index[0] ) ) { // eliminate long arrays
								$templates_used[] = $index[0];
							}
						}
					}
				}
			}

			
			// Do not add atts to admin emails

			$is_customer_email = true;

			foreach ( $templates_used as $template_name ) {

				// check each file name for '/emails/admin-'
				if ( strpos( $template_name, '/emails/admin-' ) !== false ) {

					// admin email so do not add atts 
					$is_customer_email = false;
				}
			}

			// Only add atts to customer emails, and included here is the customer View Order page.

			if ( $is_customer_email ) {
				$product = get_product( $item['product_id'] );
				$out = $item_name . '<br />' . $this->the_attributes( $product, 'span' ) . '<br />';
			}

		}
		return $out;
	}

	/**
	* Show product attributes on the Cart page.
	*/
	public function show_atts_on_cart( $cart_item, $cart_item_key ) {

		$out = $cart_item;
		if ( get_option( 'wcsa_cart' ) != 'no' ) {
			$product = $cart_item_key['data'];
			$out = $cart_item . '<br />' . $this->the_attributes( $product, 'span' );
		}
		return $out;
	
	}

	/**
	* Show product attributes on the admin Order Details page.
	* 
	* @param object, the product object
	* @param $item
	* @param integer, product id
	*/
	public function show_atts_in_admin_order( $product, $item, $item_id ) {

		if ( get_option( 'wcsa_admin_order_details' ) != 'no' ) {
			if ( is_object($product) ) {
				echo '<td><div class="view">' . $this->the_attributes( $product, 'span' ) . '</div></td>';
			}
		}
	}

	/**
	* Add Attributes to the Order Items header on the admin Order Details page.
	*/
	public function admin_order_item_header() {

		if ( get_option( 'wcsa_admin_order_details' ) != 'no' ) {

			echo '<th class="wsa-custom-attributes">' . __( 'Attributes', 'woocommerce-show-attributes' ) . '</th>';
		}
	}
	
	/**
	* Show product attributes on the child products of a Grouped Product page.
	*
	* @param object, the product object
	* @since 1.2.4
	*/
	public function show_atts_grouped_product( $product ) {
		if ( get_option( 'wcsa_product' ) != 'no') {
			echo '<td class="grouped-product-custom-attributes">' . $this->the_attributes( $product, 'span' ) . '</td>';
		}
	}

	/**
	 * Show the attributes on the main shop page.
	 * @since 1.2.3
	 */
	public function show_atts_on_shop() {
			global $product;
			echo $this->the_attributes( $product, 'li' );
	}

	/**
	 * Check if option to show attributes on main shop is enabled.
 	 * @since 1.2.3
	 */
	public function if_show_atts_on_shop() {

		$show = get_option( 'woocommerce_show_attributes_on_shop' );

		// if option to show on shop page is enabled, do it
		if ( 'above_price' == $show ) {
			add_action ( 'woocommerce_after_shop_loop_item_title', array( $this, 'show_atts_on_shop' ), 4 );

		} elseif ( 'above_add2cart' == $show ) {
			add_action ( 'woocommerce_after_shop_loop_item', array( $this, 'show_atts_on_shop' ), 4 );

		}
	}
 
 	/**
	* Customize the Additional Information tab to NOT show our custom attributes
	*/
	public function additional_info_tab( $tabs ) {
		global $product;
		// if has attributes
		if( $product->has_attributes() ) {
		
			// check if any of the attributes are variations
			$attributes = $product->get_attributes();
			$need_tab = array();
			foreach ( $attributes as $attribute ) {
				$need_tab[] = empty($attribute['is_variation']) ? '' : 1;
				
				
			}

			// if all $need_tab array values are empty, none of the attributes are variations
			// so we would not need the tab except for dimensions or weight
			
			if ( count(array_filter($need_tab)) == 0 ) {
				// if no dimensions & no weight, unset the tab
				if ( ! $product->has_dimensions() && ! $product->has_weight() ) {
					unset( $tabs['additional_information'] );

				} else {
					$tabs['additional_information']['callback'] = 'additional_info_tab_content';
				}
			} else {
				// we have variations so do tab Callback
				$tabs['additional_information']['callback'] = 'additional_info_tab_content';
			}
		} else {	// we have no attributes
			if ( $product->has_dimensions() || $product->has_weight() ) {
				$tabs['additional_information']['callback'] = 'additional_info_tab_content';
			}
		}
		return $tabs;
	}
	
	/** 
	 * Add settings to the WC Show Attributes section.
	 * @since 1.4.0
	 */
	
	public function all_settings( $settings, $current_section ) {

		/**
		 * Check the current section is what we want
		 **/

		if ( $current_section == 'wc_show_attributes' ) {

			$settings_wsa = array();

			// Add Title to the Settings
			$settings_wsa[] = array( 'name' => __( 'WooCommerce Show Attributes Settings', 'woocommerce-show-attributes' ), 'type' => 'title', 'desc' => __( 'The following options are used to configure WooCommerce Show Attributes', 'woocommerce-show-attributes' ), 'id' => 'wc_show_attributes' );
			$settings_wsa[] = array(
				'name'		=> __( 'Show Attributes on Product Page', 'woocommerce-show-attributes' ),
				'id'		=> 'wcsa_product',
				'default'	=> 'yes',
				'type'		=> 'checkbox',
				'desc'		=> __( 'Show attributes on the single product above Add To Cart, and on Grouped products.', 'woocommerce-show-attributes' )
			);
			$settings_wsa[] = array(
				'name'		=> __( 'Show Attributes on Shop Pages', 'woocommerce-show-attributes' ),
				'desc'		=> __( 'Whether to show attributes on the main shop page and shop category pages.', 'woocommerce-show-attributes' ),
				'id'		=> 'woocommerce_show_attributes_on_shop',
				'css'		=> '',
				'default'	=> 'no',
				'type'		=> 'select',
				'options'	=> array(
						''					=> __( 'No', 'woocommerce-show-attributes' ),
						'above_price'		=> __( 'Show them above the price', 'woocommerce-show-attributes' ),
						'above_add2cart'	=> __( 'Show them above "Add to Cart"', 'woocommerce-show-attributes' ),
				),
				'desc_tip'	=> true,
			);

			$settings_wsa[] = array(
				'name'		=> __( 'Show Attributes on Cart Page', 'woocommerce-show-attributes' ),
				'id'		=> 'wcsa_cart',
				'default'	=> 'yes',
				'type'		=> 'checkbox',
				'desc'		=> __( 'Show attributes on the cart and checkout pages.', 'woocommerce-show-attributes' )
			);

			$settings_wsa[] = array(
				'name'		=> __( 'Show Attributes on Customer Order Emails', 'woocommerce-show-attributes' ),
				'id'		=> 'wcsa_customer_order_emails',
				'default'	=> 'yes',
				'type'		=> 'checkbox',
				'desc'		=> __( 'Show attributes on customer order, invoice, and receipt emails, and on the customer\'s View Order page.', 'woocommerce-show-attributes' )
			);

			$settings_wsa[] = array(
				'name'		=> __( 'Show Attributes on Admin New Order Email', 'woocommerce-show-attributes' ),
				'id'		=> 'wcsa_admin_email',
				'default'	=> 'yes',
				'type'		=> 'checkbox',
				'desc'		=> __( 'Show attributes on the New Order email which goes to the Administrator.', 'woocommerce-show-attributes' )
			);

			$settings_wsa[] = array(
				'name'		=> __( 'Show Attributes on Admin Order Details Page', 'woocommerce-show-attributes' ),
				'id'		=> 'wcsa_admin_order_details',
				'default'	=> 'yes',
				'type'		=> 'checkbox',
				'desc'		=> __( 'Show attributes on the Order Details page on the back end, listed under "Order Items".', 'woocommerce-show-attributes' )
			);
			$settings_wsa[] = array(
				'name'		=> __( 'Hide the Labels When Showing Product Attributes', 'woocommerce-show-attributes' ),
				'id'		=> 'woocommerce_show_attributes_hide_labels',
				'default'	=> 'no',
				'type'		=> 'checkbox',
				'desc'		=> __( 'Check this box to hide the attribute labels and only show the attribute values.', 'woocommerce-show-attributes' )
			);
			$settings_wsa[] = array(
				'name'		=> __( 'Show Attributes in a span Element', 'woocommerce-show-attributes' ),
				'id'		=> 'woocommerce_show_attributes_span',
				'default'	=> 'no',
				'type'		=> 'checkbox',
				'desc'		=> __( 'Check this box to use a span element instead of list bullets when showing product attributes on the single product page.', 'woocommerce-show-attributes' )
			);
			
			$settings_wsa[] = array( 'type' => 'sectionend', 'id' => 'wc_show_attributes' );

			return $settings_wsa;
		

			// If not, return the standard settings

		} else {

			return $settings;

		}

	}

	/**
	 * Add our settings section under the Products tab.
	 * @since 1.4.0
	 */
	public function add_section( $sections ) {
		$sections['wc_show_attributes'] = __( 'WC Show Attributes', 'woocommerce-show-attributes' );
		return $sections;
	} 


	/**
	 * Show attributes on the Admin New Order email.
	 *
	 * Add the custom product attributes to the New Order email
	 * which goes to the Admin.
	 *
	 * @param string $item_name the product title
	 * @param object $item the product object
	 * @since 1.4.0
	 */

	public function show_admin_new_order_email( $item_name, $item ) {

		$out = $item_name;

		if ( get_option( 'wcsa_admin_email' ) != 'no' ) {

			// get the name of templates used for this email call
			$templates_used = array();
			foreach ( debug_backtrace() as $called_file ) {
				foreach ( $called_file as $index ) {
					if ( is_array( $index ) ) { // avoid errors
						if ( ! empty( $index[0] ) ) {
							if ( is_string( $index[0] ) ) { // eliminate long arrays
								$templates_used[] = $index[0];
							}
						}
					}
				}
			}

			// Only add atts to admin emails.

			$is_admin_email = false;

			foreach ( $templates_used as $template_name ) {
				// check each file name for '/emails/admin-'
				if ( strpos( $template_name, '/emails/admin-' ) !== false ) {

					// Eureka! We have an admin email
					$is_admin_email = true;
				}
			}

			if ( $is_admin_email ) {

				$product = get_product( $item['product_id'] );
				$out = $item_name . '<br />' . $this->the_attributes( $product, 'span' ) . '<br />';
			}
		}
		
		return $out;
	
	}



	
} // end class

// only if WooCommerce is active
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	/**
	* The custom output for the Additional Information tab which now excludes our custom attributes.
	*/
	function additional_info_tab_content() { ?>
		<h2><?php _e( 'Additional Information', 'woocommerce-show-attributes' ); ?></h2>
		<table class="shop_attributes">
		<?php 
		global $product;
		$has_row = false;
		$alt = 1;
		$attributes = $product->get_attributes();

		if ( $product->enable_dimensions_display() ) :
			if ( $product->has_weight() ) : $has_row = true; ?>
				<tr class="<?php if ( ( $alt = $alt * -1 ) == 1 ) echo 'alt'; ?>">
				<th><?php _e( 'Weight', 'woocommerce-show-attributes' ) ?></th>
				<td class="product_weight"><?php echo $product->get_weight() . ' ' . esc_attr( get_option( 'woocommerce_weight_unit' ) ); ?></td>
				</tr>
			<?php endif;

			if ( $product->has_dimensions() ) : $has_row = true; ?>
				<tr class="<?php if ( ( $alt = $alt * -1 ) == 1 ) echo 'alt'; ?>">
				<th><?php _e( 'Dimensions', 'woocommerce-show-attributes' ) ?></th>
				<td class="product_dimensions"><?php echo $product->get_dimensions(); ?></td>
				</tr>
			<?php endif;

		endif; ?>
		
		<?php foreach ( $attributes as $attribute ) :
			if ( empty( $attribute['is_visible'] ) || ( $attribute['is_taxonomy'] && ! taxonomy_exists( $attribute['name'] ) ) || empty( $attribute['is_variation'] ) ) {
				continue;
			} else {
				$has_row = true;
			}
			?>
			<tr class="<?php if ( ( $alt = $alt * -1 ) == 1 ) echo 'alt'; ?>">
			<th><?php echo wc_attribute_label( $attribute['name'] ); ?></th>
			<td><?php
			if ( $attribute['is_taxonomy'] ) {

				$values = wc_get_product_terms( $product->id, $attribute['name'], array( 'fields' => 'names' ) );
				echo apply_filters( 'woocommerce_attribute', wpautop( wptexturize( implode( ', ', $values ) ) ), $attribute, $values );

			} else {

				// Convert pipes to commas and display values
				$values = array_map( 'trim', explode( WC_DELIMITER, $attribute['value'] ) );
				echo apply_filters( 'woocommerce_attribute', wpautop( wptexturize( implode( ', ', $values ) ) ), $attribute, $values );

			}
			?></td>
			</tr>
		<?php endforeach; ?>
		</table>
	<?php 
	} // end additional_info_tab_content()

	$WooCommerce_Show_Attributes = WooCommerce_Show_Attributes::get_instance();

}


/** @test remove
 * Log my own debug messages
 */
function isa_log( $message ) {
    if (WP_DEBUG === true) {
        if ( is_array( $message) || is_object( $message ) ) {
            error_log( print_r( $message, true ) );
        } else {
            error_log( $message );
        }
    }
}