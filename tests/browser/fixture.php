<?php
/**
 * Local browser fixture: copy to mu-plugins only in a dedicated test store.
 *
 * @package Pinterest_For_WooCommerce
 */

if ( ! defined( 'PINTEREST_E2E' ) || ! PINTEREST_E2E ) {
	return;
}
/**
 * Model WP Consent API at its public boundary using the browser test cookie.
 *
 * @param string $category Consent category.
 * @return bool
 */
function wp_has_consent( $category ) {
	return 'marketing' !== $category || ( isset( $_COOKIE['pinterest_e2e_consent'] ) && 'allow' === $_COOKIE['pinterest_e2e_consent'] );
}
add_filter( 'pre_wp_mail', '__return_true' );
add_filter(
	'pre_http_request',
	function ( $response, $args, $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$key  = $args['method'] . ' ' . $path;
		$body = null;
		if ( preg_match( '#^/v5/ad_accounts/[^/]+/events$#', $path ) && 'POST' === $args['method'] ) {
			$events = get_option( 'pinterest_e2e_events', array() );
			$events = array_merge( $events, json_decode( $args['body'], true )['data'] );
			update_option( 'pinterest_e2e_events', $events );
			$body = array( 'events' => array( array( 'status' => 'processed' ) ) );
		} elseif ( 'GET /v5/catalogs/feeds' === $key ) {
			$body = array( 'items' => get_option( 'pinterest_e2e_remote_feeds', array() ) );
		} elseif ( 'DELETE /v5/catalogs/feeds/merchant-feed' === $key ) {
			update_option( 'pinterest_e2e_remote_feeds', array() );
			$body = array();
		} elseif ( 0 === strpos( $key, 'DELETE /v5/integrations/commerce/' ) ) {
			$body = array();
		} else {
			$responses = array(
				'GET /v5/resources/ad_account_countries' => array(
					'items' => array(
						array(
							'code'     => 'US',
							'currency' => 'USD',
							'name'     => 'United States',
						),
					),
				),
				'GET /v5/ad_accounts/merchant-advertiser/billing_profiles' => array( 'items' => array() ),
				'GET /v5/ad_accounts/merchant-advertiser/ads_credit/discounts' => array( 'items' => array() ),
				'GET /v5/ad_accounts/merchant-advertiser/conversion_tags/1234567890123' => array(
					'id'            => '1234567890123',
					'name'          => 'Local tag',
					'ad_account_id' => 'merchant-advertiser',
				),
				'GET /v5/user_account/websites/verification' => array(
					'verification_code' => 'local-verification',
					'dns_txt_record'    => 'pinterest-site-verification=local-verification',
					'metatag'           => '<meta name="p:domain_verify" content="local-verification"/>',
					'filename'          => 'pinterest-local.html',
					'file_content'      => 'local-verification',
				),
				'POST /v5/integrations/commerce'         => array(
					'id'                   => 'integration-123',
					'external_business_id' => 'local-integration',
				),
				'PATCH /v5/integrations/commerce/local-integration' => array( 'id' => 'integration-123' ),
				'GET /v5/user_account'                   => array(
					'id'            => 'merchant-123',
					'username'      => 'local-merchant',
					'account_type'  => 'BUSINESS',
					'profile_image' => '',
				),
				'POST /v5/user_account/websites'         => array(
					'website' => 'localhost',
					'status'  => 'success',
				),
				'GET /v5/user_account/websites'          => array( 'items' => array() ),
				'GET /v5/ad_accounts'                    => array(
					'items' => array(
						array(
							'id'       => 'merchant-advertiser',
							'name'     => 'Local advertiser',
							'currency' => 'EUR',
							'country'  => 'US',
							'owner'    => array( 'id' => 'merchant-123' ),
						),
					),
				),
				'GET /v5/ad_accounts/merchant-advertiser/conversion_tags' => array(
					'items' => array(
						array(
							'id'   => '1234567890123',
							'name' => 'Local tag',
						),
					),
				),
			);
			$body      = $responses[ $key ] ?? null;
		}
		if ( null === $body ) {
			if ( 'api.pinterest.com' === wp_parse_url( $url, PHP_URL_HOST ) ) {
				$unexpected   = get_option( 'pinterest_e2e_unexpected_http', array() );
				$unexpected[] = $key;
				update_option( 'pinterest_e2e_unexpected_http', array_unique( $unexpected ) );
			}
			return new WP_Error( 'pinterest_e2e_http', 'Undeclared external request blocked: ' . $key );
		}
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
		);
	},
	10,
	3
);
// Tag only orders containing this suite's products, including abandoned block drafts.
foreach ( array( 'woocommerce_checkout_order_created', 'woocommerce_store_api_checkout_update_order_meta' ) as $test_order_hook ) {
	add_action(
		$test_order_hook,
		function ( $order ) {
			$fixture = get_option( 'pinterest_e2e_fixture', array() );
			foreach ( $order->get_items() as $item ) {
				if ( $item->get_product_id() && in_array( $item->get_product_id(), array( $fixture['product'] ?? 0 ), true ) ) {
					$order->update_meta_data( '_pinterest_e2e', 'yes' );
					$order->save();
					break;
				}
			}
		}
	);
}
// Keep the browser's redirect chain local, including the connection proxy.
add_filter(
	'pinterest_for_woocommerce_connection_proxy_url',
	function () {
		return home_url( '/pinterest-e2e/' );
	}
);
add_action(
	'init',
	function () {
		if ( '/pinterest-e2e/integrations/connect/pinterest-v5' !== wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_PATH ) ) {
			return;
		}
		$callback = rest_url( 'pinterest/v1/oauth/callback' );
		// The real OAuth callback validates the nonce; this fixture only relays it.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$params = array( 'state' => sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) ) );
		// A failed proxy response omits token data; the plugin supplies its retry message.
		if ( ! get_option( 'pinterest_e2e_connect_fail' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Match the real proxy's JSON encoding.
			$params['token_data'] = base64_encode(
				wp_json_encode(
					array(
						'access_token'             => 'local-access',
						'refresh_token'            => 'local-refresh',
						'expires_in'               => 3600,
						'refresh_token_expires_in' => 7200,
						'scopes'                   => 'ads:read',
					)
				)
			);
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Match the real proxy's JSON encoding.
			$params['info'] = base64_encode(
				wp_json_encode(
					array(
						'advertiser_id' => 'merchant-advertiser',
						'tag_id'        => '1234567890123',
						'merchant_id'   => 'merchant-123',
						'feature_flags' => array(
							'tags'    => true,
							'CAPI'    => true,
							'catalog' => false,
						),
					)
				)
			);
		}
		wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', $params ), $callback ) );
		exit;
	}
);
