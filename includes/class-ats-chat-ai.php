<?php
/**
 * AI integration helpers (server-side only).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATS_Chat_AI {

	/**
	 * Maybe auto-reply when no agents are online.
	 *
	 * @param string $conversation_id Conversation ID.
	 * @return array<string,mixed>|null
	 */
	public static function maybe_auto_reply( $conversation_id ) {
		$settings = ATS_Chat_DB::get_settings();
		if ( 'auto' !== $settings['ai_mode'] ) {
			return null;
		}
		if ( ATS_Chat_DB::has_online_agents() ) {
			return null;
		}

		$reply = self::generate_reply_text( $conversation_id );
		if ( is_wp_error( $reply ) || empty( $reply ) ) {
			return null;
		}

		return ATS_Chat_DB::add_message(
			$conversation_id,
			'ai',
			'text',
			$reply,
			array(
				'mode' => 'auto',
			)
		);
	}

	/**
	 * Generate AI reply text for a conversation.
	 *
	 * @param string $conversation_id Conversation ID.
	 * @return string|WP_Error
	 */
	public static function generate_reply_text( $conversation_id ) {
		$settings = ATS_Chat_DB::get_settings();
		$api_key  = trim( (string) $settings['ai_api_key'] );
		$model    = trim( (string) $settings['ai_model'] );
		$model    = $model ? $model : 'gpt-4o-mini';

		if ( empty( $api_key ) ) {
			return new WP_Error( 'ats_chat_ai_missing_key', 'OpenAI API key is not configured.' );
		}

		$conversation = ATS_Chat_DB::get_conversation( $conversation_id );
		if ( ! $conversation ) {
			return new WP_Error( 'ats_chat_ai_missing_conversation', 'Conversation not found.' );
		}

		$visitor = ATS_Chat_DB::get_visitor( $conversation['visitor_id'] );
		$history = ATS_Chat_DB::get_messages( $conversation_id, 0 );

		$system_prompt = sanitize_textarea_field( (string) $settings['ai_system_prompt'] );
		$context_text  = self::build_context_text( $conversation, $visitor, $history );

		$request_body = array(
			'model'       => $model,
			'temperature' => 0.2,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $context_text,
				),
			),
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 25,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $request_body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return new WP_Error( 'ats_chat_ai_http_error', 'AI service failed to return a valid response.' );
		}

		$content = '';
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			$content = (string) $data['choices'][0]['message']['content'];
		}
		$content = trim( wp_strip_all_tags( $content ) );

		if ( '' === $content ) {
			return new WP_Error( 'ats_chat_ai_empty', 'AI response was empty.' );
		}

		return $content;
	}

	/**
	 * Build context sent to AI model.
	 *
	 * @param array<string,mixed>            $conversation Conversation data.
	 * @param array<string,mixed>|null       $visitor Visitor data.
	 * @param array<int,array<string,mixed>> $messages Message list.
	 * @return string
	 */
	private static function build_context_text( $conversation, $visitor, $messages ) {
		$lines   = array();
		$lines[] = 'You are assisting a website visitor in live chat.';
		$lines[] = 'If information is unknown, say so and ask a clarifying question.';
		$lines[] = 'Do not invent policies, shipping costs, pricing, or availability.';
		$lines[] = '';

		$lines[] = 'Conversation ID: ' . sanitize_text_field( (string) $conversation['conversation_id'] );
		$lines[] = 'Visitor ID: ' . sanitize_text_field( (string) $conversation['visitor_id'] );

		if ( $visitor ) {
			$lines[] = 'Visitor Name: ' . sanitize_text_field( (string) $visitor['name'] );
			$lines[] = 'Visitor Email: ' . sanitize_email( (string) $visitor['email'] );
			$lines[] = 'Current Page URL: ' . esc_url_raw( (string) $visitor['current_url'] );
			$lines[] = 'Current Page Title: ' . sanitize_text_field( (string) $visitor['current_title'] );

			$page_history = isset( $visitor['page_history'] ) && is_array( $visitor['page_history'] ) ? $visitor['page_history'] : array();
			$lines[]      = 'Recent Page Views:';
			if ( empty( $page_history ) ) {
				$lines[] = '- none';
			} else {
				foreach ( array_slice( $page_history, -10 ) as $entry ) {
					$url   = isset( $entry['url'] ) ? esc_url_raw( (string) $entry['url'] ) : '';
					$title = isset( $entry['title'] ) ? sanitize_text_field( (string) $entry['title'] ) : '';
					$seen  = isset( $entry['seen_at'] ) ? sanitize_text_field( (string) $entry['seen_at'] ) : '';
					$lines[] = sprintf( '- %s | %s | %s', $title, $url, $seen );
				}
			}

			$cart = isset( $visitor['cart'] ) && is_array( $visitor['cart'] ) ? $visitor['cart'] : array();
			$lines[] = 'Cart Context:';
			if ( empty( $cart ) ) {
				$lines[] = '- cart is empty or unavailable';
			} else {
				foreach ( $cart as $item ) {
					$title = isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : '';
					$qty   = isset( $item['qty'] ) ? absint( $item['qty'] ) : 0;
					$price = isset( $item['price'] ) ? sanitize_text_field( (string) $item['price'] ) : '';
					$lines[] = sprintf( '- %s | qty: %d | price: %s', $title, $qty, $price );
				}
			}

			$product_context = self::collect_product_context( $visitor );
			if ( ! empty( $product_context ) ) {
				$lines[] = 'Related Product Context:';
				foreach ( $product_context as $row ) {
					$lines[] = '- ' . $row;
				}
			}
		}

		$lines[] = '';
		$lines[] = 'Transcript (oldest to newest):';
		$slice   = array_slice( $messages, -30 );
		foreach ( $slice as $message ) {
			$sender = isset( $message['sender_type'] ) ? sanitize_key( (string) $message['sender_type'] ) : 'system';
			$type   = isset( $message['message_type'] ) ? sanitize_key( (string) $message['message_type'] ) : 'text';
			$text   = isset( $message['content_text'] ) ? sanitize_textarea_field( (string) $message['content_text'] ) : '';
			if ( 'product_card' === $type && ! empty( $message['content'] ) && is_array( $message['content'] ) ) {
				$card_title = isset( $message['content']['title'] ) ? sanitize_text_field( (string) $message['content']['title'] ) : 'Product';
				$card_price = isset( $message['content']['price'] ) ? sanitize_text_field( (string) $message['content']['price'] ) : '';
				$text = sprintf( 'Shared product card: %s (%s)', $card_title, $card_price );
			}
			$lines[] = sprintf( '%s: %s', strtoupper( $sender ), $text );
		}

		$lines[] = '';
		$lines[] = 'Respond with one concise chat message only.';

		return implode( "\n", $lines );
	}

	/**
	 * Collect WooCommerce product context based on visitor signals.
	 *
	 * @param array<string,mixed> $visitor Visitor row.
	 * @return array<int,string>
	 */
	private static function collect_product_context( $visitor ) {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
			return array();
		}

		$product_ids = array();

		if ( ! empty( $visitor['cart'] ) && is_array( $visitor['cart'] ) ) {
			foreach ( $visitor['cart'] as $item ) {
				if ( ! empty( $item['product_id'] ) ) {
					$product_ids[] = absint( $item['product_id'] );
				}
			}
		}

		$urls = array();
		if ( ! empty( $visitor['current_url'] ) ) {
			$urls[] = (string) $visitor['current_url'];
		}
		if ( ! empty( $visitor['page_history'] ) && is_array( $visitor['page_history'] ) ) {
			foreach ( array_slice( $visitor['page_history'], -10 ) as $entry ) {
				if ( ! empty( $entry['url'] ) ) {
					$urls[] = (string) $entry['url'];
				}
			}
		}

		foreach ( $urls as $url ) {
			$post_id = url_to_postid( $url );
			if ( $post_id && 'product' === get_post_type( $post_id ) ) {
				$product_ids[] = absint( $post_id );
			}
		}

		$product_ids = array_values( array_unique( array_filter( $product_ids ) ) );
		$product_ids = array_slice( $product_ids, 0, 5 );

		$rows = array();
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}
			$rows[] = sprintf(
				'%s | price: %s | in stock: %s | url: %s',
				sanitize_text_field( $product->get_name() ),
				wp_strip_all_tags( wc_price( $product->get_price() ) ),
				$product->is_in_stock() ? 'yes' : 'no',
				esc_url_raw( get_permalink( $product_id ) )
			);
		}

		return $rows;
	}
}
