<?php
/**
 * Represents a single Pinterest shipping zone
 *
 * @since   x.x.x
 */
namespace Automattic\WooCommerce\Pinterest;

defined( 'ABSPATH' ) || exit;

use \WC_Shipping_Zone;

/**
 * WC_Shipping_Zone class.
 */
class ShippingZone extends WC_Shipping_Zone {

	private $zone_countries_with_states = null;
	private $supported_shipping_methods = null;

	private function filter_out_not_allowed_countries( $location_objects ) {
		$allowed_countries = \Pinterest_For_Woocommerce_Admin::get_ads_supported_countries();
		return array_filter(
			$location_objects,
			function ( $location ) use ( $allowed_countries ) {
				return in_array( $location->country, $allowed_countries );
			}
		);
	}

	public function get_countries_with_states( $context = 'view' ) {
		if ( null !== $this->zone_countries_with_states ) {
			return $this->zone_countries_with_states;
		}

		$all_continents = WC()->countries->get_continents();
		$locations      = $this->get_zone_locations( $context );
		$continents     = array_filter( $locations, array( $this, 'location_is_continent' ) );
		$countries      = array_filter( $locations, array( $this, 'location_is_country' ) );
		$states         = array_filter( $locations, array( $this, 'location_is_state' ) );
		$postcodes      = array_filter( $locations, array( $this, 'location_is_postcode' ) );
		$raw_countries  = array();

		if ( ! empty( $postcodes ) ) {
			// W don't process zones with postcodes, assuming empty;
			$this->zone_countries_with_states = array();
			return $this->zone_countries_with_states;
		}

		foreach ( $continents as $location ) {
			$raw_countries += array_map(
				array( $this, "map_country_to_location_object" ),
				$all_continents[ $location->code ]['countries'],
			);
		}

		foreach ( $countries as $location ) {
			$raw_countries[] = $this->map_country_to_location_object( $location->code );
		}

		foreach ( $states as $location ) {
			$location_codes         = explode( ':', $location->code );
			$raw_countries[] = $this->map_country_to_location_object(
				$location_codes[0],
				$location_codes[1]
			);
		}

		$raw_countries = $this->filter_out_not_allowed_countries( $raw_countries );
		$raw_countries = array_unique( $raw_countries, SORT_REGULAR);
		return $this->zone_countries_with_states = $raw_countries;
	}

	private function map_country_to_location_object( $country, $state = '' ) {
		$obj = new class {
			public function __toString() { // For SORT_REGULAR when removing duplications.
				return $this->country;
			}
		};
		$obj->country = $country;
		$obj->state   = $state;
		return $obj;
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
		if ( 'free_shipping' === $shipping_method->id and $shipping_method->requires !== '' ) {
			return false;
		}

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
