<?php

namespace Automattic\WooCommerce\Pinterest;

class Pinterest_Test_Feed extends \WC_Unit_Test_Case {
	/**
	 * @group feed
	 */
	public function testHeader() {
		$actual_header = ProductsXmlFeed::get_xml_header();
		$this->assertEquals(
		"<?xml version=\"1.0\"?>
<rss version=\"2.0\" xmlns:g=\"http://base.google.com/ns/1.0\">
	<channel>
",
		$actual_header
		);
	}

	/**
	 * @group feed
	 */
	public function testFooter() {
		$actual_footer = ProductsXmlFeed::get_xml_footer();
		$this->assertEquals(
		"	</channel>
</rss>",
		$actual_footer
		);
	}
}

