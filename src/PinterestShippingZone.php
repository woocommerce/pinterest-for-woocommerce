<?php
/**
 * Represents a single Pinterest shipping zone
 *
 * @since   x.x.x
 * @package Automattic\WooCommerce\Pinterest
 */

namespace Automattic\WooCommerce\Pinterest;

defined( 'ABSPATH' ) || exit;

use \WC_Shipping_Zone;

/**
 * WC_Shipping_Zone class.
 */
class PinterestShippingZone extends WC_Shipping_Zone {


	/**
	 * Caching for internal structure of locations.
	 *
	 * @var $zone_countries_with_states
	 */
	private $zone_countries_with_states = null;

	/**
	 * Caching for supported shipping methods.
	 *
	 * @var $zone_countries_with_states
	 */
	private $supported_shipping_methods = null;


	/**
	 * From the list of countries filter out those which are not supported right now.
	 *
	 * @since   x.x.x
	 *
	 * @param  array $locations List of countries to filter.
	 * @return array            List of filtered countries.
	 */
	private function filter_out_not_allowed_countries( $locations ) {
		$allowed_countries = \Pinterest_For_Woocommerce_Admin::get_ads_supported_countries();
		return array_filter(
			$locations,
			function ( $location ) use ( $allowed_countries ) {
				return in_array( $location['country'], $allowed_countries, true );
			}
		);
	}

	public function get_countries_with_states( $context = 'view' ) {
		if ( null !== $this->zone_countries_with_states ) {
			return $this->zone_countries_with_states;
		}

		$all_continents = WC()->countries->get_continents();
		$zone_locations = $this->get_zone_locations( $context );
		$continents     = array_filter( $zone_locations, array( $this, 'location_is_continent' ) );
		$countries      = array_filter( $zone_locations, array( $this, 'location_is_country' ) );
		$states         = array_filter( $zone_locations, array( $this, 'location_is_state' ) );
		$postcodes      = array_filter( $zone_locations, array( $this, 'location_is_postcode' ) );
		$locations      = array();

		if ( ! empty( $postcodes ) ) {
			// W don't process zones with postcodes, assuming empty.
			$this->zone_countries_with_states = array();
			return $this->zone_countries_with_states;
		}

		foreach ( $continents as $location ) {
			$locations += array_map(
				array( $this, 'map_to_location' ),
				$all_continents[ $location->code ]['countries'],
			);
		}

		foreach ( $countries as $location ) {
			$locations[] = $this->map_to_location( $location->code );
		}

		foreach ( $states as $location ) {
			$location_codes = explode( ':', $location->code );
			$locations[]    = $this->map_to_location(
				$location_codes[0],
				$location_codes[1]
			);
		}

		$locations = $this->filter_out_not_allowed_countries( $locations );
		$locations = $this->remove_duplicate_locations( $locations );

		// Cache the locations.
		$this->zone_countries_with_states = $locations;
		return $this->zone_countries_with_states;
	}

	/**
	 * Remove duplicated locations. Encapsulated into separate function
	 * in case we will need to do more complicated filtering as this is
	 * a multidimensional filtering. It will also allow unit testing.
	 *
	 * @param array $locations Array of locations.
	 * @return array
	 */
	public function remove_duplicate_locations( $locations ) {
		return array_unique( $locations, SORT_REGULAR );
	}

	private function map_to_location( $country, $state = '' ) {
		return array(
			'country' => $country,
			'state'   => $state,
		);
	}

	public function get_locations_with_shipping() {
		$countries_with_states = $this->get_countries_with_states();
		$shipping_methods      = $this->get_supported_shipping_methods();

		if ( empty( $countries_with_states ) || empty( $shipping_methods ) ) {
			return null;
		}

		return array(
			'locations'        => $countries_with_states,
			'shipping_methods' => $shipping_methods,
		);
	}

	private function get_supported_shipping_methods() {
		if ( null !== $this->supported_shipping_methods ) {
			return $this->supported_shipping_methods;
		}
		$active_shipping_methods = $this->get_shipping_methods( true );
		$this->supported_shipping_methods = array_filter(
			$active_shipping_methods,
			array( $this, 'is_shipping_method_supported' )
		);

		return $this->supported_shipping_methods;
	}

	private function is_shipping_method_supported( $shipping_method ) {
		if ( ! in_array( $shipping_method->id, [ 'free_shipping', 'flat_rate' ] ) ) {
			return false;
		}

		// We don't support for now free shipping with additional requirements options.
		// if ( 'free_shipping' === $shipping_method->id and $shipping_method->requires !== '' ) {
		// 	return false;
		// }

		return true;
	}

	/**
	 * Location type detection.
	 *
	 * @param  object $location Location to check.
	 * @return boolean
	 */
	private function location_is_continent( $location ) {
		return 'continent' === $location->type;
	}

	/**
	 * Location type detection.
	 *
	 * @param  object $location Location to check.
	 * @return boolean
	 */
	private function location_is_country( $location ) {
		return 'country' === $location->type;
	}

	/**
	 * Location type detection.
	 *
	 * @param  object $location Location to check.
	 * @return boolean
	 */
	private function location_is_state( $location ) {
		return 'state' === $location->type;
	}

	/**
	 * Location type detection.
	 *
	 * @param  object $location Location to check.
	 * @return boolean
	 */
	private function location_is_postcode( $location ) {
		return 'postcode' === $location->type;
	}

}
