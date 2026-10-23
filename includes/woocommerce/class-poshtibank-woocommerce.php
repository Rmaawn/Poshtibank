<?php
/**
 * WooCommerce Deep Integration: Extracts product, cart, and customer history.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Poshtibank_WooCommerce
 */
class Poshtibank_WooCommerce {

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Get WooCommerce context for current page (Product or Checkout/Cart).
	 *
	 * @return array
	 */
	public static function get_page_context() {
		if ( ! self::is_active() ) {
			return array();
		}

		$context = array(
			'is_wc_page' => false,
			'type'       => '',
			'product'    => null,
			'cart'       => null,
		);

		// 1. Single Product Page
		if ( function_exists( 'is_product' ) && is_product() ) {
			$product_id = get_the_ID();
			$product    = wc_get_product( $product_id );

			if ( $product ) {
				$image_id = $product->get_image_id();
				$img_url  = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';

				$context['is_wc_page'] = true;
				$context['type']       = 'product';
				$context['product']    = array(
					'id'              => $product->get_id(),
					'title'           => $product->get_name(),
					'price_formatted' => wp_strip_all_tags( wc_price( $product->get_price() ) ),
					'raw_price'       => $product->get_price(),
					'in_stock'        => $product->is_in_stock(),
					'stock_status'    => $product->is_in_stock() ? __( 'موجود', 'poshtibank' ) : __( 'ناموجود', 'poshtibank' ),
					'url'             => get_permalink( $product->get_id() ),
					'image'           => $img_url,
					'sku'             => $product->get_sku(),
				);
			}
		}

		// 2. Checkout or Cart Page
		if ( ( function_exists( 'is_checkout' ) && is_checkout() ) || ( function_exists( 'is_cart' ) && is_cart() ) ) {
			if ( function_exists( 'WC' ) && WC()->cart ) {
				$context['is_wc_page'] = true;
				$context['type']       = is_checkout() ? 'checkout' : 'cart';
				$context['cart']       = array(
					'count' => WC()->cart->get_cart_contents_count(),
					'total' => wp_strip_all_tags( WC()->cart->get_cart_total() ),
				);
			}
		}

		return $context;
	}

	/**
	 * Get WooCommerce Customer Order History for logged-in users.
	 *
	 * @param int $user_id User ID.
	 * @return array|null
	 */
	public static function get_customer_summary( $user_id ) {
		if ( ! self::is_active() || ! $user_id ) {
			return null;
		}

		if ( ! function_exists( 'wc_get_customer_order_count' ) ) {
			return null;
		}

		$order_count = wc_get_customer_order_count( $user_id );
		$total_spent = function_exists( 'wc_get_customer_total_spent' ) ? wc_get_customer_total_spent( $user_id ) : 0;
		$last_order  = function_exists( 'wc_get_customer_last_order' ) ? wc_get_customer_last_order( $user_id ) : null;

		$last_order_data = null;
		if ( $last_order ) {
			$last_order_data = array(
				'id'     => $last_order->get_id(),
				'status' => wc_get_order_status_name( $last_order->get_status() ),
				'date'   => $last_order->get_date_created() ? $last_order->get_date_created()->date_i18n( 'Y/m/d' ) : '',
				'total'  => wp_strip_all_tags( wc_price( $last_order->get_total(), array( 'currency' => $last_order->get_currency() ) ) ),
			);
		}

		return array(
			'order_count' => $order_count,
			'total_spent' => wp_strip_all_tags( wc_price( $total_spent ) ),
			'last_order'  => $last_order_data,
		);
	}

	/**
	 * Search product by ID, SKU, or Title (for /product command).
	 *
	 * @param string $query Product ID, SKU, or Keyword.
	 * @return array|null
	 */
	public static function find_product( $query ) {
		if ( ! self::is_active() ) {
			return null;
		}

		$query   = trim( $query );
		$product = null;

		// 1. By ID
		if ( is_numeric( $query ) ) {
			$product = wc_get_product( absint( $query ) );
		}

		// 2. By SKU
		if ( ! $product && function_exists( 'wc_get_product_id_by_sku' ) ) {
			$sku_id = wc_get_product_id_by_sku( $query );
			if ( $sku_id ) {
				$product = wc_get_product( $sku_id );
			}
		}

		// 3. By Search Title
		if ( ! $product && function_exists( 'wc_get_products' ) ) {
			$products = wc_get_products(
				array(
					's'     => $query,
					'limit' => 1,
				)
			);
			if ( ! empty( $products ) ) {
				$product = reset( $products );
			}
		}

		if ( ! $product ) {
			return null;
		}

		$image_id = $product->get_image_id();
		$img_url  = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';

		return array(
			'id'              => $product->get_id(),
			'title'           => $product->get_name(),
			'price_formatted' => wp_strip_all_tags( wc_price( $product->get_price() ) ),
			'url'             => get_permalink( $product->get_id() ),
			'image'           => $img_url,
			'in_stock'        => $product->is_in_stock(),
		);
	}

	/**
	 * Find coupon details by coupon code (for /coupon command).
	 *
	 * @param string $code Coupon code.
	 * @return array|null
	 */
	public static function find_coupon( $code ) {
		if ( ! self::is_active() || ! class_exists( 'WC_Coupon' ) ) {
			return null;
		}

		$code   = trim( $code );
		$coupon = new WC_Coupon( $code );

		if ( ! $coupon->get_id() ) {
			return null;
		}

		$amount = $coupon->get_amount();
		$type   = $coupon->get_discount_type();
		$label  = ( 'percent' === $type ) ? $amount . '٪ تخفیف' : wp_strip_all_tags( wc_price( $amount ) ) . ' تخفیف';

		return array(
			'code'        => $coupon->get_code(),
			'amount'      => $amount,
			'type'        => $type,
			'label'       => $label,
			'description' => $coupon->get_description() ?: __( 'کوپن تخفیف اختصاصی برای شما', 'poshtibank' ),
		);
	}
}
