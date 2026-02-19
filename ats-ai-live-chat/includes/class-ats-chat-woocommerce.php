<?php
/**
 * WooCommerce integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATS_Chat_WooCommerce {

	/**
	 * Initialize WooCommerce hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'capture_cart_context' ) );
		add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'capture_cart_context' ) );
		add_action( 'woocommerce_cart_updated', array( __CLASS__, 'capture_cart_context' ) );
	}

	/**
	 * Capture cart context mapped to visitor ID cookie.
	 *
	 * @return void
	 */
	public static function capture_cart_context() {
		$visitor_id = self::get_visitor_cookie_id();
		if ( empty( $visitor_id ) ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$visitor = ATS_Chat_DB::get_visitor( $visitor_id );
		if ( ! $visitor ) {
			ATS_Chat_DB::upsert_visitor_presence(
				array(
					'visitor_id'    => $visitor_id,
					'current_url'   => home_url( '/' ),
					'current_title' => 'Cart update',
					'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
					'referrer'      => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
				)
			);
		}

		ATS_Chat_DB::save_cart_context( $visitor_id, self::get_current_cart_items() );
	}

	/**
	 * Get current visitor ID from cookie.
	 *
	 * @return string
	 */
	private static function get_visitor_cookie_id() {
		if ( empty( $_COOKIE['ats_chat_visitor_id'] ) ) {
			return '';
		}
		return ATS_Chat_DB::sanitize_visitor_id( wp_unslash( $_COOKIE['ats_chat_visitor_id'] ) );
	}

	/**
	 * Return current cart items normalized for admin display and AI context.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_current_cart_items() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array();
		}

		$items = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['product_id'] ) ) {
				continue;
			}

			$product_id = absint( $cart_item['product_id'] );
			$product    = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$image_id  = $product->get_image_id();
			$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

			$items[] = array(
				'product_id' => $product_id,
				'title'      => $product->get_name(),
				'qty'        => isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1,
				'price'      => wp_strip_all_tags( wc_price( $product->get_price() ) ),
				'url'        => get_permalink( $product_id ),
				'image'      => $image_url,
			);
		}

		return $items;
	}

	/**
	 * Search products by title/SKU.
	 *
	 * @param string $query Search query.
	 * @return array<int,array<string,mixed>>
	 */
	public static function search_products( $query ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$query = sanitize_text_field( $query );
		if ( '' === $query ) {
			return array();
		}

		$product_ids = array();

		$title_query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				's'              => $query,
				'fields'         => 'ids',
			)
		);
		if ( ! empty( $title_query->posts ) ) {
			$product_ids = array_merge( $product_ids, $title_query->posts );
		}

		$sku_query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_sku',
						'value'   => $query,
						'compare' => 'LIKE',
					),
				),
			)
		);
		if ( ! empty( $sku_query->posts ) ) {
			$product_ids = array_merge( $product_ids, $sku_query->posts );
		}

		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
		$product_ids = array_slice( $product_ids, 0, 10 );

		$results = array();
		foreach ( $product_ids as $product_id ) {
			$card = self::build_product_card( $product_id );
			if ( ! empty( $card ) ) {
				$results[] = $card;
			}
		}

		return $results;
	}

	/**
	 * Build product card payload.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string,mixed>
	 */
	public static function build_product_card( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
			return array();
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}

		$image_id  = $product->get_image_id();
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : wc_placeholder_img_src( 'medium' );

		return array(
			'product_id' => $product_id,
			'title'      => $product->get_name(),
			'price'      => wp_strip_all_tags( wc_price( $product->get_price() ) ),
			'excerpt'    => wp_strip_all_tags( $product->get_short_description() ),
			'url'        => get_permalink( $product_id ),
			'image'      => $image_url,
			'sku'        => $product->get_sku(),
		);
	}
}
