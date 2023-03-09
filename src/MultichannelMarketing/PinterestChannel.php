<?php
/**
 * Pinterest for WooCommerce Marketing Channel
 *
 * @package     Automattic\WooCommerce\Pinterest\MultichannelMarketing
 * @version     x.x.x
 */

namespace Automattic\WooCommerce\Pinterest\MultichannelMarketing;

use Automattic\WooCommerce\Admin\Marketing\MarketingCampaign;
use Automattic\WooCommerce\Admin\Marketing\MarketingCampaignType;
use Automattic\WooCommerce\Admin\Marketing\MarketingChannelInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Class PinterestChannel
 */
class PinterestChannel implements MarketingChannelInterface {

	/**
	 * Returns the unique identifier string for the marketing channel extension, also known as the plugin slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'pinterest-for-woocommerce';
	}

	/**
	 * Returns the name of the marketing channel.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Pinterest for WooCommerce', 'pinterest-for-woocommerce' );
	}

	/**
	 * Returns the description of the marketing channel.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Grow your business on Pinterest! Use this official plugin to allow shoppers to Pin products while browsing your store, track conversions, and advertise on Pinterest.', 'pinterest-for-woocommerce' );
	}

	/**
	 * Returns the path to the channel icon.
	 *
	 * @return string
	 */
	public function get_icon_url(): string {
		return 'https://woocommerce.com/wp-content/plugins/wccom-plugins/marketing-tab-rest-api/icons/pinterest.svg';
	}

	/**
	 * Returns the setup status of the marketing channel.
	 *
	 * @return bool
	 */
	public function is_setup_completed(): bool {
		return Pinterest_For_Woocommerce()::is_setup_complete();
	}

	/**
	 * Returns the URL to the settings page, or the link to complete the setup/onboarding if the channel has not been set up yet.
	 *
	 * @return string
	 */
	public function get_setup_url(): string {
		// TODO: Implement get_setup_url() method.
		return '';
	}

	/**
	 * Returns the status of the marketing channel's product listings.
	 *
	 * @return string
	 */
	public function get_product_listings_status(): string {
		// TODO: Implement get_product_listings_status() method.
		return self::PRODUCT_LISTINGS_NOT_APPLICABLE;
	}

	/**
	 * Returns the number of channel issues/errors (e.g. account-related errors, product synchronization issues, etc.).
	 *
	 * @return int The number of issues to resolve, or 0 if there are no issues with the channel.
	 */
	public function get_errors_count(): int {
		// TODO: Implement get_errors_count() method.
		return 0;
	}

	/**
	 * Returns an array of marketing campaign types that the channel supports.
	 *
	 * @return MarketingCampaignType[] Array of marketing campaign type objects.
	 */
	public function get_supported_campaign_types(): array {
		// TODO: Implement get_supported_campaign_types() method.
		return array();
	}

	/**
	 * Returns an array of the channel's marketing campaigns.
	 *
	 * @return MarketingCampaign[]
	 */
	public function get_campaigns(): array {
		// TODO: Implement get_campaigns() method.
		return array();
	}
}
