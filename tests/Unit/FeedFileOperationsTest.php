<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\FeedFileOperations;
use Automattic\WooCommerce\Pinterest\LocalFeedConfigs;
use Automattic\WooCommerce\Pinterest\ProductFeedStatus;
use Automattic\WooCommerce\Pinterest\ProductsXmlFeed;
use Exception;

class FeedFileOperationsTest extends \WP_UnitTestCase {

	/** @var LocalFeedConfigs */
	private $local_feed_configs;

	public function setUp() {
		parent::setUp();
		$this->local_feed_configs = $this->createMock( LocalFeedConfigs::class );
		ProductFeedStatus::set( ProductFeedStatus::STATE_PROPS );
	}

	public function test_prepare_temporary_files_creates_files_with_xml_headers() {
		$US_temp_file = tempnam( sys_get_temp_dir(), 'USTestXMLHeader' );
		$UA_temp_file = tempnam( sys_get_temp_dir(), 'UATestXMLHeader' );
		$this->local_feed_configs
			->method( 'get_configurations' )
			->willReturn(
				array(
					'US' => array(
						'tmp_file' => $US_temp_file,
					),
					'UA' => array(
						'tmp_file' => $UA_temp_file,
					),
				)
			);

		$feed_file_operations = new FeedFileOperations( $this->local_feed_configs );
		$feed_file_operations->prepare_temporary_files();

		$this->assertEquals( ProductsXmlFeed::get_xml_header(), file_get_contents( $US_temp_file ) );
		$this->assertEquals( ProductsXmlFeed::get_xml_header(), file_get_contents( $UA_temp_file ) );
	}

	public function test_prepare_temporary_files_creates_files_with_xml_footers() {
		$US_temp_file = tempnam( sys_get_temp_dir(), 'USTestXMLFooter' );
		$UA_temp_file = tempnam( sys_get_temp_dir(), 'UATestXMLFooter' );
		$this->local_feed_configs
			->method( 'get_configurations' )
			->willReturn(
				array(
					'US' => array(
						'tmp_file' => $US_temp_file,
					),
					'UA' => array(
						'tmp_file' => $UA_temp_file,
					),
				)
			);

		$feed_file_operations = new FeedFileOperations( $this->local_feed_configs );
		$feed_file_operations->add_footer_to_temporary_feed_files();

		$this->assertEquals( ProductsXmlFeed::get_xml_footer(), file_get_contents( $US_temp_file ) );
		$this->assertEquals( ProductsXmlFeed::get_xml_footer(), file_get_contents( $UA_temp_file ) );
	}

	public function test_if_feed_file_exists_returns_true_if_file_is_there() {
		$US_temp_file = tempnam( sys_get_temp_dir(), 'USTestFeedFile' );
		$this->local_feed_configs
			->method( 'get_configurations' )
			->willReturn(
				array(
					'US' => array(
						'feed_file' => $US_temp_file,
					),
				)
			);

		$feed_file_operations = new FeedFileOperations( $this->local_feed_configs );
		$file_exists          = $feed_file_operations->check_if_feed_file_exists();

		$this->assertTrue( $file_exists );
	}

	public function test_if_feed_file_exists_returns_false_if_config_is_empty() {
		$this->local_feed_configs
			->method( 'get_configurations' )
			->willReturn( array() );

		$feed_file_operations = new FeedFileOperations( $this->local_feed_configs );
		$file_exists          = $feed_file_operations->check_if_feed_file_exists();

		$this->assertFalse( $file_exists );
	}

	public function test_if_feed_file_exists_returns_false_if_there_is_no_file() {
		$US_temp_file = sys_get_temp_dir() . uniqid( 'USTestFeedFile' );
		$this->local_feed_configs
			->method( 'get_configurations' )
			->willReturn(
				array(
					'US' => array(
						'feed_file' => $US_temp_file,
					),
				)
			);

		$feed_file_operations = new FeedFileOperations( $this->local_feed_configs );
		$file_exists          = $feed_file_operations->check_if_feed_file_exists();

		$this->assertFalse( $file_exists );
	}

	public function test_rename_temporary_feed_files_to_final_renames_successfully() {
		$US_temp_file = tempnam( sys_get_temp_dir(), 'USFeedFile' );
		$US_feed_file = sys_get_temp_dir() . uniqid( '/USFeedFile' );
		$UA_temp_file = tempnam( sys_get_temp_dir(), 'UAFeedFile' );
		$UA_feed_file = sys_get_temp_dir() . uniqid( '/UAFeedFile' );
		$this->local_feed_configs
			->method( 'get_configurations' )
			->willReturn(
				array(
					'US' => array(
						'tmp_file'  => $US_temp_file,
						'feed_file' => $US_feed_file,
					),
					'UA' => array(
						'tmp_file'  => $UA_temp_file,
						'feed_file' => $UA_feed_file,
					),
				)
			);

		$feed_file_operations = new FeedFileOperations( $this->local_feed_configs );
		$feed_file_operations->rename_temporary_feed_files_to_final();

		$this->assertTrue( file_exists( $US_feed_file ) );
		$this->assertTrue( file_exists( $UA_feed_file ) );
	}

	public function test_rename_temporary_feed_files_to_final_throws_exception_if_rename_failed() {
		// Silent the warning which rename() will produce if destination is a folder.
		error_reporting(E_ERROR | E_PARSE);

		// Set destination to be a folder to make rename() return false result.
		$feed_file_name = '/';
		$US_temp_file   = tempnam( sys_get_temp_dir(), 'USFeedFile' );
		$UA_temp_file   = tempnam( sys_get_temp_dir(), 'UAFeedFile' );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( "Could not rename {$US_temp_file} to {$feed_file_name}" );

		$this->local_feed_configs
			->method( 'get_configurations' )
			->willReturn(
				array(
					'US' => array(
						'tmp_file'  => $US_temp_file,
						'feed_file' => $feed_file_name,
					),
					'UA' => array(
						'tmp_file'  => $UA_temp_file,
						'feed_file' => $feed_file_name,
					),
				)
			);

		$feed_file_operations = new FeedFileOperations( $this->local_feed_configs );
		$feed_file_operations->rename_temporary_feed_files_to_final();
	}

	public function test_write_buffers_to_temp_files_writes_into_file() {
		$US_temp_file = tempnam( sys_get_temp_dir(), 'USFeedFile' );
		$UA_temp_file = tempnam( sys_get_temp_dir(), 'UAFeedFile' );
		$this->local_feed_configs
			->method( 'get_configurations' )
			->willReturn(
				array(
					'US' => array(
						'tmp_file'  => $US_temp_file,
					),
					'UA' => array(
						'tmp_file'  => $UA_temp_file,
					),
				)
			);

		$buffers = array(
			'US' => 'Some US buffer content...',
			'UA' => 'Some UA buffer content...',
		);

		$feed_file_operations = new FeedFileOperations( $this->local_feed_configs );
		$feed_file_operations->write_buffers_to_temp_files( $buffers );

		$this->assertEquals( 'Some US buffer content...', file_get_contents( $US_temp_file ) );
		$this->assertEquals( 'Some UA buffer content...', file_get_contents( $UA_temp_file ) );
	}
}
