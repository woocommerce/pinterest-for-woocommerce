<?php
/**
 * Covering tests related to Tasks.
 */

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Pinterest_For_Woocommerce;
use \WP_UnitTestCase;
use Automattic\WooCommerce\Admin\Features\OnboardingTasks\TaskLists;
use Automattic\WooCommerce\Pinterest\Admin\Tasks\Onboarding;

class TasksTest extends WP_UnitTestCase {

	/**
	 * Check if an object of a given class name is present in the array.
	 *
	 * @param string   $class_name The name of the class to test the object against.
	 * @param iterable $hay_stack  Array of objects.
	 * @param string   $message    Message to display on failed assertion.
	 * @return void
	 */
	private function assertContainsInstanceOf( string $class_name, iterable $haystack, string $message = '' ): void {
		$contains = false;
		foreach ( $haystack as $hay ) {
			if ( get_class( $hay ) === $class_name ) {
				$contains = true;
				break;
			}
		}
		$this->assertTrue( $contains, $message );
	}

	/**
	 * Tests to assert the addition of the Onboarding task to the task list.
	 */
	public function test_add_onboarding_task_added() {
		// Assert that the task is added to the tasklist.
		$task = TaskLists::get_task( 'setup-pinterest' );
		$this->assertNotNull( $task );
		$this->assertInstanceOf( Onboarding::class, $task, 'Cannot assert onboarding task added to tasklist.' );

		// Assert that the task is added to extended tasklist.
		$task_lists     = TaskLists::get_lists();
		$extended_tasks = $task_lists['extended']->tasks;
		$this->assertContainsInstanceOf( Onboarding::class, $extended_tasks, 'Cannot assert onboarding task added to extended tasklist.' );
	}

	/**
	 * Tasks to assert the completion of the Onboarding task.
	 */
	public function test_onboarding_task_completed() {
		add_filter(
			'home_url',
			function() {
				return 'https://somedomain.com/';
			}
		);

		// Assert that the task is not completed.
		$task        = TaskLists::get_task( 'setup-pinterest' );
		$is_complete = $task->is_complete();

		$this->assertFalse( $is_complete, 'Cannot assert task not completed.' );

		// Setup complete data.
		$account_data = array(
			'is_any_website_verified' => true,
			'verified_user_websites'  => array( 'somedomain.com' ),
			'is_partner'              => true,
		);
		Pinterest_For_Woocommerce::save_setting( 'account_data', $account_data );
		Pinterest_For_Woocommerce::save_integration_data(
			array(
				'id'                           => '438304186899501392',
				'external_business_id'         => 'wordpresspinterest-65a04926ed482',
				'connected_merchant_id'        => '1479839719476',
				'connected_user_id'            => '1144266355231574943',
				'connected_advertiser_id'      => '549765662491',
				'connected_tag_id'             => '2613286171854',
				'connected_lba_id'             => '0',
				'partner_access_token_expiry'  => 0,
				'partner_refresh_token_expiry' => 0,
				'scopes'                       => '',
				'created_timestamp'            => 1705003303000,
				'updated_timestamp'            => 1705003303000,
				'additional_id_1'              => '',
				'partner_metadata'             => '',
			)
		);
		Pinterest_For_Woocommerce::save_token_data(
			array(
				'access_token' => 'some-fake-access-token',
			)
		);
		Pinterest_For_Woocommerce::save_setting( 'tracking_tag', true );

		// Assert that the task is completed.
		$is_complete = $task->is_complete();
		$this->assertTrue( $is_complete, 'Cannot assert task completion.' );
	}
}
