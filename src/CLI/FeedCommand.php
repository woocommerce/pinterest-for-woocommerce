<?php
/**
 * Pinterest for WooCommerce WP-CLI command for the catalog feeds of the connected ad account.
 *
 * @package     Pinterest_For_WooCommerce/CLI
 * @version     x.x.x
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\CLI;

use Automattic\WooCommerce\Pinterest\API\APIV5;
use Automattic\WooCommerce\Pinterest\FeedOwnership;
use Automattic\WooCommerce\Pinterest\FeedRegistration;
use Automattic\WooCommerce\Pinterest\Feeds;
use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Pinterest_For_Woocommerce;
use WP_CLI;
use WP_CLI\Formatter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists and deletes the Pinterest catalog feeds (data sources) of the connected ad account.
 *
 * Pinterest blocks deleting app-managed data sources from its dashboard, and the plugin's own
 * cleanup only removes feeds it can match by location. This command gives support a way to
 * inspect every feed on the ad account and delete one by ID without touching the others.
 *
 * @class   FeedCommand
 * @version x.x.x
 */
class FeedCommand {

	/**
	 * Fields of a feed row, in display order.
	 */
	const FIELDS = array( 'id', 'status', 'registered', 'recorded', 'plugin_location', 'name', 'location' );

	/**
	 * Lists the catalog feeds registered on the connected Pinterest ad account.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * * id: Pinterest feed ID.
	 * * status: Pinterest feed status (ACTIVE, INACTIVE, DELETED).
	 * * registered: "yes" for the feed this site is currently registered to.
	 * * recorded: "yes" when the plugin recorded creating or registering to the feed.
	 * * plugin_location: "yes" when the feed location points to this site's plugin feed file path.
	 * * name: Feed name on Pinterest.
	 * * location: Feed file URL Pinterest fetches.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show every feed on the connected ad account.
	 *     $ wp pinterest feed list
	 *
	 *     # Only the IDs and locations, as JSON.
	 *     $ wp pinterest feed list --fields=id,location --format=json
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function list_( array $args, array $assoc_args ): void {
		$ad_account_id = self::get_connected_ad_account_id();
		$feeds         = array();

		Feeds::invalidate_feeds_cache();
		try {
			$feeds = APIV5::get_feeds( $ad_account_id )['items'] ?? array();
		} catch ( PinterestApiException $e ) {
			WP_CLI::error( self::describe_exception( $e ) );
		}

		$formatter = new Formatter( $assoc_args, self::FIELDS );
		$formatter->display_items( self::get_rows( $feeds ) );
	}

	/**
	 * Deletes one catalog feed from the connected Pinterest ad account.
	 *
	 * The feed this site is registered to is refused unless --force is passed. Deleting it
	 * makes the plugin register a new feed on its next feed registration run.
	 *
	 * ## OPTIONS
	 *
	 * <feed-id>
	 * : Pinterest feed ID, as shown by `wp pinterest feed list`.
	 *
	 * [--force]
	 * : Delete the feed even when it is the one this site is registered to.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete an orphaned feed after confirming the prompt.
	 *     $ wp pinterest feed delete 1558987740004
	 *
	 *     # Delete without prompting.
	 *     $ wp pinterest feed delete 1558987740004 --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function delete( array $args, array $assoc_args ): void {
		$feed_id       = (string) $args[0];
		$ad_account_id = self::get_connected_ad_account_id();
		$feed          = array();

		Feeds::invalidate_feeds_cache();
		try {
			$feed = Feeds::get_feed( $feed_id );
		} catch ( PinterestApiException $e ) {
			WP_CLI::error( self::describe_exception( $e ) );
		}

		if ( empty( $feed ) ) {
			WP_CLI::error( sprintf( 'Feed %s is not registered on the connected Pinterest ad account.', $feed_id ) );
		}

		$is_registered_feed = (string) FeedRegistration::get_locally_stored_registered_feed_id() === $feed_id;
		if ( $is_registered_feed && empty( $assoc_args['force'] ) ) {
			WP_CLI::error( sprintf( 'Feed %s is the feed this site is registered to. Pass --force to delete it anyway.', $feed_id ) );
		}

		WP_CLI::confirm(
			sprintf( 'Delete feed %s (%s, %s)?', $feed_id, $feed['status'] ?? 'unknown status', $feed['location'] ?? 'no location' ),
			$assoc_args
		);

		try {
			APIV5::delete_feed( $feed_id, $ad_account_id );
		} catch ( PinterestApiException $e ) {
			WP_CLI::error( self::describe_exception( $e ) );
		}

		FeedOwnership::forget( $feed_id, $ad_account_id );
		Feeds::invalidate_feeds_cache();
		if ( $is_registered_feed ) {
			Pinterest_For_Woocommerce::save_data( 'feed_registered', false );
		}

		WP_CLI::success( sprintf( 'Deleted feed %s.', $feed_id ) );
	}

	/**
	 * Turns Pinterest feed objects into list rows with the plugin's view of each feed.
	 *
	 * Public so that the row logic can be tested without a WP-CLI runtime.
	 *
	 * @since x.x.x
	 *
	 * @param array $feeds Feed objects as returned by the Pinterest feeds endpoint.
	 * @return array[] One row per feed, keyed by the FIELDS names.
	 */
	public static function get_rows( array $feeds ): array {
		$registered_feed_id = (string) FeedRegistration::get_locally_stored_registered_feed_id();
		$rows               = array();

		foreach ( $feeds as $feed ) {
			$feed_id  = (string) ( $feed['id'] ?? '' );
			$location = (string) ( $feed['location'] ?? '' );

			$rows[] = array(
				'id'              => $feed_id,
				'status'          => (string) ( $feed['status'] ?? '' ),
				'registered'      => self::yes_no( '' !== $feed_id && $feed_id === $registered_feed_id ),
				'recorded'        => self::yes_no( FeedOwnership::is_recorded( $feed_id ) ),
				'plugin_location' => self::yes_no( Feeds::is_plugin_generated_feed_location( $location ) ),
				'name'            => (string) ( $feed['name'] ?? '' ),
				'location'        => $location,
			);
		}

		return $rows;
	}

	/**
	 * Returns the connected ad account ID or stops the command when there is none.
	 *
	 * @return string
	 */
	private static function get_connected_ad_account_id(): string {
		$ad_account_id = (string) Pinterest_For_Woocommerce::get_setting( 'tracking_advertiser' );
		if ( '' === $ad_account_id ) {
			WP_CLI::error( 'No Pinterest ad account is connected. Connect one under Marketing > Pinterest first.' );
		}

		return $ad_account_id;
	}

	/**
	 * Formats a Pinterest API exception for the terminal.
	 *
	 * @param PinterestApiException $e The exception.
	 * @return string
	 */
	private static function describe_exception( PinterestApiException $e ): string {
		return sprintf( 'Pinterest API error %d (code %d): %s', $e->getCode(), $e->get_pinterest_code(), $e->getMessage() );
	}

	/**
	 * Renders a boolean as a table cell.
	 *
	 * @param bool $value The value.
	 * @return string
	 */
	private static function yes_no( bool $value ): string {
		return $value ? 'yes' : 'no';
	}
}
