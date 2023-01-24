<?php
/**
 * Covering tests related to Tasks.
 */

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use \WP_UnitTestCase;
use Automattic\WooCommerce\Admin\Features\OnboardingTasks\TaskLists;
use Automattic\WooCommerce\Pinterest\Admin\Tasks\Onboarding;

class TasksTest extends WP_UnitTestCase {

    /**
     * Pinterest WooCommerce admin object.
     *
     * @var \Pinterest_For_Woocommerce_Admin
     */
    protected $pinterest_wc_admin;

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
     * Check if an object of a given class name is not present in the array.
     *
     * @param string   $class_name The name of the class to test the object against.
     * @param iterable $hay_stack  Array of objects.
     * @param string   $message    Message to display on failed assertion.
     * @return void
     */
    private function assertNotContainsInstanceOf( string $class_name, iterable $haystack, string $message = '' ): void {
        $contains = false;
        foreach ( $haystack as $hay ) {
            if ( get_class( $hay ) === $class_name ) {
                $contains = true;
                break;
            }
        }
        $this->assertFalse( $contains, $message );
    }

    /**
     * Run before each test.
     */
    public function setUp(): void {
        parent::setUp();
        require_once 'includes/admin/class-pinterest-for-woocommerce-admin.php';
        $this->pinterest_wc_admin = new \Pinterest_For_Woocommerce_Admin();
    }

    public function test_add_onboarding_task_added() {
        // Assert that the task is not present in the default tasklist.
        $task_lists     = TaskLists::get_lists();
        $extended_tasks = $task_lists['extended']->tasks;
        $this->assertNotContainsInstanceOf( Onboarding::class, $extended_tasks, 'Cannot assert onboarding task not added to tasklist.' );

        // Add the task.
        $this->pinterest_wc_admin->add_onboarding_task();

        // Assert that the task is added to the tasklist.
        $task = TaskLists::get_task( 'setup-pinterest' );
        $this->assertNotNull( $task );
        $this->assertInstanceOf( Onboarding::class, $task, 'Cannot assert onboarding task added to tasklist.' );

        // Assert that the task is added to extended tasklist.
        $task_lists     = TaskLists::get_lists();
        $extended_tasks = $task_lists['extended']->tasks;
        $this->assertContainsInstanceOf( Onboarding::class, $extended_tasks, 'Cannot assert onboarding task added to extended tasklist.' );
    }

    public function test_onboarding_task_completed() {
        // Add the task.
        $this->pinterest_wc_admin->add_onboarding_task();

        // Assert that the task is not completed.
        $task        = TaskLists::get_task( 'setup-pinterest' );
        $is_complete = $task->is_complete();
        
        $this->assertFalse( $is_complete, 'Cannot assert task not completed.' );

        // Setup complete data. 
        $account_data = array(
            'is_any_website_verified' => true,
            'is_partner'              => true,
        );
        \Pinterest_For_Woocommerce::save_setting( 'account_data', $account_data );
        \Pinterest_For_Woocommerce::save_token(
			array(
				'access_token' => 'some-fake-access-token',
			)
		);
        \Pinterest_For_Woocommerce::save_setting( 'tracking_tag', true );

        // Assert that the task is completed.
        $is_complete = $task->is_complete();
        $this->assertTrue( $is_complete, 'Cannot assert task completion.' );
    }
}