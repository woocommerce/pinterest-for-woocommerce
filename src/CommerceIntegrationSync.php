<?php //phpcs:disable WordPress.WP.AlternativeFunctions --- Uses FS read/write in order to reliable append to an existing file.
/**
 * Pinterest for WooCommerce Commerce Integration Sync
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Pinterest\API\APIV5;
use Exception;
use Pinterest_For_Woocommerce;
use WC_Log_Levels;

/**
 * Handling Pinterest Commerce Integration synchronisation.
 * Pinterest is mostly interested in the `partner_metadata` part of it.
 */
class CommerceIntegrationSync {

	/**
	 * Check and schedule a weekly event.
	 *
	 * @return void
	 */
	public static function schedule_event() {
		if ( ! Pinterest_For_Woocommerce::is_connected() ) {
			return;
		}

		if ( ! has_action( Heartbeat::WEEKLY, array( self::class, 'handle_sync' ) ) ) {
			add_action( Heartbeat::WEEKLY, array( self::class, 'handle_sync' ) );
		}
	}

	/**
	 * Handle Pinterest Commerce Integration weekly sync.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	public static function handle_sync(): bool {
		try {
			$external_business_id = Pinterest_For_Woocommerce::get_data( 'integration_data' )['external_business_id'] ?? '';
			if ( empty( $external_business_id ) ) {
				Pinterest_For_Woocommerce::create_commerce_integration();
				return true;
			}

			$integration = APIV5::get_commerce_integration( $external_business_id );
			$data        = self::prepare_commerce_integration_data( $integration['external_business_id'] );
			if ( $integration['partner_metadata'] === $data['partner_metadata'] ) {
				return true;
			}

			$response = APIV5::update_commerce_integration( $integration['external_business_id'], $data );
			Pinterest_For_Woocommerce::save_integration_data( $response );
			return true;
		} catch ( PinterestApiException $e ) {
			Logger::log(
				$e->getMessage(),
				WC_Log_Levels::ERROR,
				'pinterest-for-woocommerce-commerce-integration-sync'
			);
			return false;
		} catch ( Exception $e ) {
			/*
			 * As thrown from create_commerce_integration call in case Advertiser ID is missing.
			 * Extremely unlikely at this stage.
			 */
			Logger::log(
				$e->getMessage(),
				WC_Log_Levels::ERROR,
				'pinterest-for-woocommerce-commerce-integration-sync'
			);
			return false;
		}
	}

	/**
	 * Prepares Commerce Integration Data.
	 *
	 * @param string $external_business_id Auto-generated if empty External Business ID to pass to Pinterest.
	 *
	 * @since x.x.x
	 * @return array
	 * @throws Exception In case of Advertiser ID is missing.
	 */
	public static function prepare_commerce_integration_data( string $external_business_id = '' ): array {
		global $wp_version;

		if ( empty( $external_business_id ) ) {
			$external_business_id = self::generate_external_business_id();
		}
		$connection_data = Pinterest_For_Woocommerce::get_data( 'connection_info_data', true );

		// It does not make any sense to create integration without Advertiser ID.
		if ( empty( $connection_data['advertiser_id'] ) ) {
			throw new Exception(
				sprintf(
					esc_html__(
						'Commerce Integration cannot be created: Advertiser ID is missing.',
						'pinterest-for-woocommerce'
					)
				)
			);
		}

		$integration_data = array(
			'external_business_id'    => $external_business_id,
			'connected_merchant_id'   => $connection_data['merchant_id'] ?? '',
			'connected_advertiser_id' => $connection_data['advertiser_id'],
			'partner_metadata'        => json_encode(
				array(
					'plugin_version' => PINTEREST_FOR_WOOCOMMERCE_VERSION,
					'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
					'wp_version'     => $wp_version,
					'locale'         => get_locale(),
					'currency'       => get_woocommerce_currency(),
				)
			),
		);

		if ( ! empty( $connection_data['tag_id'] ) ) {
			$integration_data['connected_tag_id'] = $connection_data['tag_id'];
		}
		/**
		 * Allows modifications to commerce integration data when creating or updating Pinterest Commerce Integration.
		 *
		 * @since x.x.x
		 *
		 * @param array $integration_data {
		 *      An array of integration data as rquired by the Pinterest API endpoint documentation.
		 *      @link https://developers.pinterest.com/docs/api/v5/integrations_commerce-post
		 *
		 *      @type string $external_business_id    - Woo's external business ID.
		 *      @type string $connected_merchant_id   - Connected merchant ID for the integration.
		 *      @type string $connected_advertiser_id - Connected advertiser ID for the integration.
		 *      @type string $connected_tag_id        - Connected Pinterest Tag ID for the integration.
		 *      @type string $partner_metadata        - Partner metadata for the integration.
		 * }
		 */
		return apply_filters( 'pinterest_for_woocommerce_commerce_integration_data', $integration_data );
	}

	/**
	 * Used to generate external business id to pass it Pinterest when creating a connection between WC and Pinterest.
	 *
	 * @since x.x.x
	 *
	 * @return string
	 */
	public static function generate_external_business_id(): string {
		$name = (string) parse_url( esc_url( get_site_url() ), PHP_URL_HOST );
		if ( empty( $name ) ) {
			$name = sanitize_title( get_bloginfo( 'name' ) );
		}
		$id = uniqid( sprintf( 'woo-%s-', $name ), false );

		/**
		 * Filters the shop's external business id.
		 *
		 * This is passed to Pinterest when connecting.
		 * Should be non-empty and without special characters,
		 * otherwise the ID will be obtained from the site's name as fallback.
		 *
		 * @since 1.4.0
		 *
		 * @param string $id the shop's external business id.
		 */
		return (string) apply_filters( 'wc_pinterest_external_business_id', $id );
	}
}
