<?php
/**
 * Ad-credit redemption must keep billing and connection changes made during the request.
 *
 * @package Pinterest_For_WooCommerce
 */

namespace Automattic\WooCommerce\Pinterest\Tests\Integration;

use Automattic\WooCommerce\Pinterest\AdCredits;
use Automattic\WooCommerce\Pinterest\AdCreditsCoupons;
use Automattic\WooCommerce\Pinterest\API\Base;
use Pinterest_For_Woocommerce as Plugin;

/**
 * Exercise saved merchant state through the native redemption path.
 *
 * @version x.x.x
 */
class BillingAndCreditsTest extends \WP_UnitTestCase {

	/** @var array Redemption HTTP responses. */
	private $redemptions = array();
	/** @var array Sent redemption bodies. */
	private $redeem_requests = array();
	/** @var array Undeclared requests. */
	private $unexpected = array();
	/** @var \ReflectionProperty Token cache. */
	private $token_property;
	/** @var mixed Original token cache. */
	private $token;
	/** @var string Original offer. */
	private $coupon;

	/** Prepare a connected advertiser and a local-only HTTP boundary. */
	public function setUp(): void {
		parent::setUp();
		add_filter( 'pre_http_request', array( $this, 'http' ), 10, 3 );
		$this->token_property = new \ReflectionProperty( Base::class, 'token' );
		if ( PHP_VERSION_ID < 80100 ) {
			$this->token_property->setAccessible( true );
		}
		$this->token = $this->token_property->getValue();
		$this->token_property->setValue( null, null );
		$this->coupon                      = AdCreditsCoupons::$coupon_for_2024;
		AdCreditsCoupons::$coupon_for_2024 = 'test-offer';
		update_option( 'woocommerce_default_country', 'US:CA' );
		update_option( 'woocommerce_currency', 'USD' );
		update_option( 'woocommerce_price_num_decimals', 2 );
		update_option( 'woocommerce_currency_pos', 'left' );
		update_option( 'woocommerce_price_decimal_sep', '.' );
		Plugin::save_token_data(
			array(
				'access_token' => 'billing-test-token',
				'expires_in'   => 3600,
			)
		);
		Plugin::save_settings(
			array(
				'tracking_advertiser' => 'billing-test',
				'account_data'        => array(
					'id'               => 'merchant-one',
					'username'         => 'merchant',
					'is_billing_setup' => true,
					'merchant_note'    => array( 'keep' => 'unchanged' ),
				),
			)
		);
		delete_transient( AdCredits::ADS_CREDIT_CAMPAIGN_TRANSIENT );
	}

	/** Restore static state as well as the native transaction. */
	public function tearDown(): void {
		try {
			$this->assertSame( array(), $this->unexpected, 'Undeclared HTTP is forbidden even when production catches its error.' );
		} finally {
			AdCreditsCoupons::$coupon_for_2024 = $this->coupon;
			$this->token_property->setValue( null, $this->token );
			parent::tearDown();
			Plugin::get_settings( true );
			Plugin::get_settings( true, PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );
		}
	}

	/**
	 * Keep real API routing, JSON parsing and error handling inside the test.
	 *
	 * @param mixed  $response Short circuit value.
	 * @param array  $args Request arguments.
	 * @param string $url Request URL.
	 * @return array|\WP_Error
	 */
	public function http( $response, $args, $url ) {
		if ( 'GET' === $args['method'] && 'https://api.pinterest.com/v5/ad_accounts/billing-test/ads_credit/discounts?page_size=25' === $url ) {
			return $this->response( 200, array( 'items' => array() ) );
		}
		if ( 'GET' === $args['method'] && 'https://api.pinterest.com/v5/resources/ad_account_countries' === $url ) {
			return $this->response( 200, array( 'items' => array( array( 'code' => 'US' ) ) ) );
		}
		if ( 'GET' === $args['method'] && 'https://woocommerce.com/wp-json/wccom/marketing-tab/1.2/recommendations.json' === $url ) {
			return $this->response(
				200,
				array(
					array(
						'product'                   => 'pinterest-for-woocommerce',
						'show_extension_promotions' => true,
					),
				)
			);
		}
		if ( 'POST' === $args['method'] && 'https://api.pinterest.com/v5/ad_accounts/billing-test/ads_credit/redeem' === $url && $this->redemptions ) {
			$this->redeem_requests[] = json_decode( $args['body'], true );
			list( $status, $body )   = array_shift( $this->redemptions );
			return $this->response( $status, $body );
		}
		$this->unexpected[] = $args['method'] . ' ' . $url;
		return new \WP_Error( 'unexpected_request', 'Request not declared by this case.' );
	}

	/**
	 * Native HTTP response envelope.
	 *
	 * @param int   $status HTTP status.
	 * @param array $body JSON body.
	 * @return array
	 */
	private function response( $status, $body ) {
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => $status,
				'message' => 'Fixture response',
			),
			'cookies'  => array(),
			'filename' => '',
		);
	}

	/** A merchant without a stored billing status is not eligible and sends no redemption. */
	public function test_missing_billing_status_does_not_redeem() {
		$account = Plugin::get_setting( 'account_data' );
		unset( $account['is_billing_setup'] );
		Plugin::save_setting( 'account_data', $account );
		$this->redemptions = array( array( 200, array( 'success' => true ) ) );

		$this->assertFalse( Plugin::get_billing_setup_info_from_account_data() );
		$this->assertTrue( AdCredits::handle_redeem_credit() );
		$this->assertSame( array(), $this->redeem_requests );
	}

	/** A billing rejection saved during redemption must not be overwritten by the outer save. */
	public function test_billing_rejection_survives_the_redemption_save() {
		$this->redemptions = array(
			array(
				400,
				array(
					'code'    => 4374,
					'message' => 'No billing profile',
				),
			),
		);

		AdCredits::handle_redeem_credit();

		$account = Plugin::get_setting( 'account_data', true );
		$this->assertCount( 1, $this->redeem_requests );
		$this->assertFalse( $account['is_billing_setup'] );
		$this->assertFalse( $account['coupon_redeem_info']['redeem_status'] );
		$this->assertSame( array( 'keep' => 'unchanged' ), $account['merchant_note'] );
	}

	/** A 401 disconnect during redemption must not be undone by the outer save. */
	public function test_rejected_token_does_not_restore_disconnected_account_data() {
		$this->redemptions = array(
			array(
				401,
				array(
					'code'    => 1,
					'message' => 'Rejected token',
				),
			),
		);

		Plugin::add_redeem_credits_info_to_account_data();

		$account = Plugin::get_setting( 'account_data', true );
		$this->assertCount( 1, $this->redeem_requests );
		$this->assertFalse( Plugin::is_connected() );
		$this->assertArrayNotHasKey( 'id', (array) $account );
		$this->assertArrayNotHasKey( 'coupon_redeem_info', (array) $account );
	}
}
