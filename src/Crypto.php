<?php
/**
 * Pinterest For WooCommerce Crypto Wrapper
 *
 * @class       WP_Salesforce_Crypto
 * @version     1.0.0
 * @package     Pinterest_For_WooCommerce/Classes/
 * @category    Class
 * @author      WooCommerce
 */

namespace Automattic\WooCommerce\Pinterest;

use Defuse\Crypto\KeyProtectedByPassword;
use Defuse\Crypto\Crypto as DefuseCrypto;
use Defuse\Crypto\Key;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Crypto {

	private static $key;

	public function __construct() {
		self::$key = \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_' . md5( is_multisite() ? \AUTH_KEY . get_current_blog_id() : \AUTH_KEY );
	}

	private static function create_key() {

		$protected_key         = KeyProtectedByPassword::createRandomPasswordProtectedKey( self::$key );
		$protected_key_encoded = $protected_key->saveToAsciiSafeString();

		if ( ! empty( $protected_key_encoded ) ) {
			Pinterest_For_Woocommerce()::save_setting( 'crypto_encoded_key', $protected_key_encoded );
			return $protected_key_encoded;
		}

		return false;

	}

	private static function get_key() {

		static $user_key_encoded;

		if ( ! is_null( $user_key_encoded ) ) {
			return $user_key_encoded;
		}

		if ( empty( self::$key ) ) {
			new self();
		}

		$protected_key_encoded = Pinterest_For_Woocommerce()::get_setting( 'crypto_encoded_key' );

		if ( empty( $protected_key_encoded ) ) {
			$protected_key_encoded = self::create_key();
		}

		try {
			$protected_key    = KeyProtectedByPassword::loadFromAsciiSafeString( $protected_key_encoded );
			$user_key         = $protected_key->unlockKey( self::$key );
			$user_key_encoded = $user_key->saveToAsciiSafeString();
		} catch ( Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex ) {
			API\Base::log( 'error', esc_html__( 'Could not decrypt Key value. Try reconnecting to Pinterest.' ) );
			Pinterest_For_Woocommerce()::save_setting( 'crypto_encoded_key', false ); // Reset base key
			return false;
		}

		return $user_key_encoded;
	}

	public static function encrypt( $value ) {

		$user_key_encoded = self::get_key();
		$user_key         = Key::loadFromAsciiSafeString( $user_key_encoded );

		return DefuseCrypto::encrypt( $value, $user_key );
	}

	public static function decrypt( $encrypted_value ) {

		$user_key_encoded = self::get_key();
		$user_key         = Key::loadFromAsciiSafeString( $user_key_encoded );

		try {
			$value = DefuseCrypto::decrypt( $encrypted_value, $user_key );
		} catch ( Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex ) {
			// Either there's a bug in our code, we're trying to decrypt with the
			// wrong key, or the encrypted credit card number was corrupted in the
			// database.
			API\Base::log( 'error', esc_html__( 'Could not decrypt Key value. Try reconnecting to Pinterest.' ) );
		}

		return $value;
	}
}
