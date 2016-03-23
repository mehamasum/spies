<?php

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @runTestsInSeparateProcesses
 */
class SpyTest extends PHPUnit_Framework_TestCase {
	public function test_spy_was_called_returns_true_if_called() {
		$spy = new \Spies\Spy();
		$spy();
		$this->assertTrue( $spy->was_called() );
	}

	public function test_make_spy_was_called_returns_true_if_called() {
		$spy = \Spies\make_spy();
		$spy();
		$this->assertTrue( $spy->was_called() );
	}

	public function test_spy_was_called_returns_false_if_not_called() {
		$spy = \Spies\make_spy();
		$this->assertFalse( $spy->was_called() );
	}

	public function test_get_spy_for_creates_a_global_function() {
		\Spies\get_spy_for( 'test_spy' );
		$this->assertTrue( function_exists( 'test_spy' ) );
	}

	public function test_get_spy_for_returns_a_spy() {
		$spy = \Spies\get_spy_for( 'test_spy' );
		$this->assertTrue( $spy instanceof \Spies\Spy );
	}

	public function test_get_spy_for_creates_a_spy_that_is_called_when_the_global_function_is_called() {
		$spy = \Spies\get_spy_for( 'test_spy' );
		test_spy();
		$this->assertTrue( $spy->was_called() );
	}

	public function test_if_get_spy_for_is_called_twice_it_returns_the_same_spy() {
		$spy_1 = \Spies\get_spy_for( 'test_spy' );
		\Spies\Spy::clear_all_spies();
		$spy_2 = \Spies\get_spy_for( 'test_spy' );
		$this->assertEquals( $spy_1, $spy_2 );
	}

	public function test_clear_call_record_clears_the_call_record_for_a_spy() {
		$spy = \Spies\get_spy_for( 'test_spy' );
		test_spy();
		$spy->clear_call_record();
		$this->assertFalse( $spy->was_called() );
	}

	public function test_if_get_spy_for_is_called_again_after_clearing_spies_it_resets_the_call_record_for_the_spy() {
		\Spies\get_spy_for( 'test_spy' );
		test_spy();
		\Spies\Spy::clear_all_spies();
		$spy = \Spies\get_spy_for( 'test_spy' );
		$this->assertFalse( $spy->was_called() );
	}

	public function test_arguments_passed_to_a_spy_are_saved_in_the_call_record() {
		$spy = \Spies\make_spy();
		$spy( 'foo', 'bar' );
		$spy( 'baz', 'bar' );
		$call_record_arguments = array_map( function( $call ) {
			return $call['args'];
		}, $spy->get_called_functions() );
		$this->assertContains( [ 'foo', 'bar' ], $call_record_arguments );
		$this->assertContains( [ 'baz', 'bar' ], $call_record_arguments );
	}

	public function test_spy_was_called_times_returns_true_for_the_number_of_times_the_spy_was_called() {
		$spy = \Spies\make_spy();
		$spy();
		$spy();
		$spy();
		$this->assertTrue( $spy->was_called_times( 3 ) );
	}

	public function test_spy_was_called_times_returns_false_if_the_argument_does_not_match_the_number_of_times_the_spy_was_called() {
		$spy = \Spies\make_spy();
		$spy();
		$spy();
		$spy();
		$this->assertFalse( $spy->was_called_times( 6 ) );
	}

	public function test_spy_was_called_with_returns_true_if_the_spy_was_called_with_the_arguments_provided() {
		$spy = \Spies\make_spy();
		$spy( 'foo', 'bar', 'baz' );
		$this->assertTrue( $spy->was_called_with( 'foo', 'bar', 'baz' ) );
	}

	public function test_spy_was_called_with_returns_false_if_the_spy_was_not_called_with_the_arguments_provided() {
		$spy = \Spies\make_spy();
		$spy( 'foo' );
		$this->assertFalse( $spy->was_called_with( 'foo', 'bar', 'baz' ) );
	}
}
