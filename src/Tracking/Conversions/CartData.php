<?php
/**
 * @package Pinterest_For_Woocommerce/API/Conversions
 */
namespace Automattic\WooCommerce\Pinterest\API\Conversions;

/**
 * Custom data for events which require cart data e.g. add to cart, checkout, purchase, etc.
 *
 * @link https://developers.pinterest.com/docs/conversions/best/#Required,%20recommended,%20and%20optional%20fields
 *
 * @since x.x.x
 */
class CartData implements CustomData {

	/**
	 * @var string ISO-4217 currency code.
	 */
	private string $currency;

	/**
	 * @var string A total value of the event. E.g. if there are multiple items in a checkout event, value should be \
	 *             the total price of all items. Accepted as a string, parsed into a double. Strongly recommended for \
	 *             add-to-cart or checkout events.
	 */
	private string $value;

	/**
	 * @var string[] Product IDs as an array of strings. Strongly recommended for page_visit, add-to-cart and \
	 *               checkout events.
	 */
	private array $content_ids;

	/**
	 * @var array An array of objects, each containing item price and quantity for an individual product. \
	 *            Strongly recommended for add-to-cart or checkout events.
	 */
	private array $contents;

	/**
	 * @var int Total number of products in the event. Strongly recommended for add-to-cart or checkout events.
	 */
	private int $num_items;

	/**
	 * @var string A unique ID representing the order. Strongly recommended for add-to-cart or checkout events.
	 */
	private string $order_id;

	public function __construct( string $currency, string $value, array $content_ids, array $contents, int $num_items, string $order_id ) {
		$this->currency    = $currency;
		$this->value       = $value;
		$this->content_ids = $content_ids;
		$this->contents    = $contents;
		$this->num_items   = $num_items;
		$this->order_id    = $order_id;
	}

	/**
	 * @return string
	 */
	public function get_currency(): string {
		return $this->currency;
	}

	/**
	 * @return string
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * @return array
	 */
	public function get_content_ids(): array {
		return $this->content_ids;
	}

	/**
	 * @return array
	 */
	public function get_contents(): array {
		return $this->contents;
	}

	/**
	 * @return int
	 */
	public function get_num_items(): int {
		return $this->num_items;
	}

	/**
	 * @return string
	 */
	public function get_order_id(): string {
		return $this->order_id;
	}
}
