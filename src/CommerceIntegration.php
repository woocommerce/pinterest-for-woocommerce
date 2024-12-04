<?php //phpcs:disable WordPress.WP.AlternativeFunctions --- Uses FS read/write in order to reliably append to an existing file.
/**
 * Pinterest for WooCommerce Commerce Integration controller class.
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @since       1.4.11
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Pinterest\API\APIV5;
use Pinterest_For_Woocommerce;

/**
 * Class Handling Commerce Integration operations.
 */
class CommerceIntegration {

	public const MAX_RETRIES = 3;

	/**
	 * Registers a retry hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action(
			'pinterest-for-woocommerce-create-commerce-integration-retry',
			array( self::class, 'handle_create' ),
			10,
			1
		);

		if ( ! has_action( Heartbeat::WEEKLY, array( self::class, 'handle_sync' ) ) ) {
			add_action( Heartbeat::WEEKLY, array( self::class, 'handle_sync' ) );
		}
	}

	/**
	 * Removes all scheduled actions.
	 * Called during disconnect procedure.
	 *
	 * @return void
	 */
	public static function maybe_unregister_retries() {
		as_unschedule_all_actions( 'pinterest-for-woocommerce-create-commerce-integration-retry' );
		as_unschedule_all_actions( 'pinterest-for-woocommerce-sync-commerce-integration' );
	}

	/**
	 * Attempts to create Commerce Integration with Pinterest and schedules a retry in case of a failure.
	 *
	 * @param int $attempt Create Commerce Integration attempt number.
	 * @return array
	 */
	public static function handle_create( $attempt = 0 ): array {
		try {
			$integration_data = self::get_integration_data();

			$response = APIV5::create_commerce_integration( $integration_data );

			/*
			 * In case of successful response we save our integration data into a database.
			 * Data we save includes but not limited to:
			 *  external business id,
			 *  id,
			 *  connected_user_id,
			 *  etc.
			 */
			Pinterest_For_Woocommerce::save_integration_data( $response );

			return $response;
		} catch ( PinterestApiException $e ) {
			if ( self::MAX_RETRIES === $attempt ) {
				Logger::log(
					sprintf(
						/* translators: 1: Pinterest internal code, 2: Pinterest response message. */
						__(
							'Create Pinterest Commerce Integration retries has stopped. No further attempts to be scheduled.',
							'pinterest-for-woocommerce'
						),
						esc_html( $e->get_pinterest_code() ),
						esc_html( $e->getMessage() ),
					),
					'error'
				);
				return array();
			}

			$has_retry_scheduled = as_has_scheduled_action(
				'pinterest-for-woocommerce-create-commerce-integration-retry',
				array( 'attempt' => $attempt + 1 ),
				'pinterest-for-woocommerce'
			);
			if ( false === $has_retry_scheduled ) {
				Logger::log(
					sprintf(
						/* translators: 1: Pinterest internal code, 2: Pinterest response message. */
						__(
							'Create Pinterest Commerce Integration has failed due to Pinterest API code %1$s with message %2$s. Scheduling a retry attempt.',
							'pinterest-for-woocommerce'
						),
						esc_html( $e->get_pinterest_code() ),
						esc_html( $e->getMessage() ),
					),
					'error'
				);
				$frames = array( MINUTE_IN_SECONDS, HOUR_IN_SECONDS, DAY_IN_SECONDS );
				as_schedule_single_action(
					time() + ( $frames[ $attempt ] ?? DAY_IN_SECONDS ),
					'pinterest-for-woocommerce-create-commerce-integration-retry',
					array(
						'attempt' => $attempt + 1,
					),
					'pinterest-for-woocommerce'
				);
			}

			return array();
		}
	}

	/**
	 * Handles Commerce Integration partner_metadata updates.
	 *
	 * @since 1.4.11
	 * @throws PinterestApiException Pinterest API exception.
	 * @return void
	 */
	public static function handle_sync() {
		if ( ! Pinterest_For_Woocommerce::is_connected() ) {
			return;
		}

		/*
		 * If there is a commerce integration retry action, we know not to run the sync yet,
		 * since it will also try to create the commerce integration as well.
		 */
		if ( as_has_scheduled_action( 'pinterest-for-woocommerce-create-commerce-integration-retry' ) ) {
			return;
		}

		$integration_data     = Pinterest_For_Woocommerce::get_data( 'integration_data' );
		$external_business_id = $integration_data['external_business_id'] ?? '';

		if ( ! $external_business_id ) {
			self::handle_create();
			return;
		}

		try {
			$new_integration_data = self::get_integration_data( $external_business_id );

			if ( $integration_data['partner_metadata'] !== $new_integration_data['partner_metadata'] ) {
				$response = APIV5::update_commerce_integration( $external_business_id, $new_integration_data );
				Pinterest_For_Woocommerce::save_integration_data( $response );
			}
		} catch ( PinterestApiException $e ) {
			Logger::log(
				sprintf(
					/* translators: 1: Pinterest internal code, 2: Pinterest response message. */
					__(
						'Commerce Integration Sync has failed with Pinterest code %1$s and the message %2$s. Next attempt in a week.',
						'pinterest-for-woocommerce'
					),
					esc_html( $e->get_pinterest_code() ),
					esc_html( $e->getMessage() )
				),
				'error'
			);
			throw $e;
		}
	}

	/**
	 * Prepares Commerce Integration data.
	 *
	 * @since 1.4.11
	 * @param string $external_business_id External Business ID.
	 * @return array
	 */
	public static function get_integration_data( $external_business_id = '' ): array {
		global $wp_version;

		if ( empty( $external_business_id ) ) {
			$external_business_id = self::generate_external_business_id();
		}

		$connection_data = Pinterest_For_Woocommerce::get_data( 'connection_info_data', true );

		$integration_data = array(
			'external_business_id'    => $external_business_id,
			'connected_merchant_id'   => $connection_data['merchant_id'] ?? '',
			'connected_advertiser_id' => $connection_data['advertiser_id'] ?? '',
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

		return $integration_data;
	}

	/**
	 * Generates External Business ID for Pinterest Commerce Integration.
	 *
	 * @NOTE: ID generation logic is as requested by Pinterest.
	 *
	 * @since 1.4.11
	 * @return string
	 */
	private static function generate_external_business_id(): string {
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
