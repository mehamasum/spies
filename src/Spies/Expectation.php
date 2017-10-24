<?php
namespace Spies;

class Expectation {
	// Syntactic sugar; these just return the Expectation
	public $to_be_called;
	public $to_have_been_called;

	// If true, `verify()` will return true or false instead making a PHPUnit assertion
	public $silent_failures = false;

	// Can be used to prevent double-verification.
	public $was_verified = false;

	private $spy = null;
	private $negation = null;
	private $expected_args = null;
	private $delayed_expectations = [];
	private $return_failure_message = false;

	public function __construct( $spy ) {
		if ( is_string( $spy ) ) {
			throw new \InvalidArgumentException( 'Expectations require a Spy but I was passed a string: ' . $spy );
		}
		if ( ! $spy instanceof Spy ) {
			throw new \InvalidArgumentException( 'Expectations require a Spy' );
		}
		$this->spy = $spy;
		$this->to_be_called = $this;
		$this->to_have_been_called = $this;
		GlobalExpectations::add_expectation( $this );
	}

	public static function expect_spy( $spy ) {
		return new Expectation( $spy );
	}

	public static function any() {
		return new AnyValue();
	}

	/**
	 * Magic function so that the `not` property can be used to negate this
	 * Expectation.
	 */
	public function __get( $key ) {
		if ( $key === 'not' ) {
			$this->negation = true;
			return $this;
		}
		throw new InvalidExpectationException( 'Invalid property: "' . $key . '" does not exist on this Expectation' );
	}

	/**
 	 * Verify all behaviors in this Expectation
	 *
	 * By default it will use PHPUnit to create assertions for
	 * each behavior.
	 *
	 * If `silent_failures` is set to true, it will return true or false instead
	 * making a PHPUnit assertion
	 *
	 * @return string|null The first failure description if there is a failure
	 */
	public function verify() {
		$this->was_verified = true;
		foreach( $this->delayed_expectations as $behavior ) {
			$description = call_user_func( $behavior );
			if ( $description !== null ) {
				if ( $this->silent_failures ) {
					return false;
				}
				return $description;
			}
		}
	}

	/**
	 * Return true if all behaviors in this Expectation are met
	 *
	 * @return boolean True if the behaviors are all met
	 */
	public function met_expectations() {
		$message = $this->get_fail_message();
		return empty( $message );
	}

	/**
	 * Return the first failure message for the behaviors on this Expectation
	 *
	 * Returns null if no behaviors failed.
	 *
	 * @return string|null The first failure message for the behaviors on this Expectation or null
	 */
	public function get_fail_message() {
		$this->was_verified = true;
		$this->return_failure_message = true;
		foreach( $this->delayed_expectations as $behavior ) {
			$description = call_user_func( $behavior );
			if ( $description !== null ) {
				$this->return_failure_message = false;
				return 'Failed asserting that ' . $description;
			}
		}
		$this->return_failure_message = false;
		return null;
	}

	/**
	 * Set expected behavior
	 *
	 * Expectations will be evaluated when `verify()` is called.
	 *
	 * The passed function will be called each time the spy is called and
	 * passed the arguments of that call.
	 *
	 * @param callable $callable The function to run on every call
	 * @return Expectation This Expectation to allow chaining
	 */
	public function when( $callable ) {
		$this->expected_function = $callable;
		$this->delay_expectation( function() use ( $callable ) {
			if ( $this->negation ) {
				if ( $this->silent_failures ) {
					return ! ( new SpiesConstraintWasCalledWhen( $callable ) )->matches( $this->spy );
				}
				return \Spies\TestCase::assertSpyWasNotCalledWhen( $this->spy, $callable );
			}
			if ( $this->silent_failures ) {
					return ( new SpiesConstraintWasCalledWhen( $callable ) )->matches( $this->spy );
			}
			return \Spies\TestCase::assertSpyWasCalledWhen( $this->spy, $callable );
		} );
		return $this;
	}

	/**
	 * Set expected arguments
	 *
	 * Expectations will be evaluated when `verify()` is called.
	 *
	 * If passed a function, it will be called each time the spy is called and
	 * passed the arguments of that call.
	 *
	 * @param mixed $arg... The arguments we expect or a function
	 * @return Expectation This Expectation to allow chaining
	 */
	public function with() {
		$args = func_get_args();
		if ( is_callable( $args[0] ) ) {
			return $this->when( $args[0] );
		}
		$this->expected_args = $args;
		$this->delay_expectation( function() use ( $args ) {
			if ( $this->negation ) {
				if ( $this->silent_failures ) {
					return ! ( new SpiesConstraintWasCalledWith( $this->expected_args ) )->matches( $this->spy );
				}
				return \Spies\TestCase::assertSpyWasNotCalledWith( $this->spy, $this->expected_args );
			}
			if ( $this->silent_failures ) {
					return ( new SpiesConstraintWasCalledWith( $this->expected_args ) )->matches( $this->spy );
			}
			return \Spies\TestCase::assertSpyWasCalledWith( $this->spy, $this->expected_args );
		} );
		return $this;
	}

	/**
 	 * Set the expectation that the Spy was called
	 *
	 * Expectations will be evaluated when `verify()` is called.
	 *
	 * @return Expectation This Expectation to allow chaining
	 */
	public function to_be_called() {
		$this->delay_expectation( function() {
			$constraint = new SpiesConstraintWasCalled();
			if ( $this->negation ) {
				if ( $this->return_failure_message ) {
					if ( $constraint->matches( $this->spy ) ) {
						return $constraint->failureDescription( $this->spy );
					}
					return null;
				}
				if ( $this->silent_failures ) {
					return ! $constraint->matches( $this->spy );
				}
				return \Spies\TestCase::assertSpyWasNotCalled( $this->spy );
			}
			if ( $this->return_failure_message ) {
				if ( ! $constraint->matches( $this->spy ) ) {
					return $constraint->failureDescription( $this->spy );
				}
				return null;
			}
			if ( $this->silent_failures ) {
				return $constraint->matches( $this->spy );
			}
			return \Spies\TestCase::assertSpyWasCalled( $this->spy );
		} );
		return $this;
	}

	/**
 	 * Set the expectation that the Spy was called
	 *
	 * Alias for `to_be_called`
	 *
	 * Expectations will be evaluated when `verify()` is called.
	 *
	 * @return Expectation This Expectation to allow chaining
	 */
	public function to_have_been_called() {
		$args = func_get_args();
		return call_user_func_array( [ $this, 'to_be_called' ], $args );
	}

	/**
	 * Set the expectation that the Spy was called once
	 *
	 * Alias for `times(1)`
	 *
	 * Expectations will be evaluated when `verify()` is called.
	 *
	 * @return Expectation This Expectation to allow chaining
	 */
	public function once() {
		return $this->times( 1 );
	}

	/**
	 * Set the expectation that the Spy was called twice
	 *
	 * Alias for `times(2)`
	 *
	 * Expectations will be evaluated when `verify()` is called.
	 *
	 * @return Expectation This Expectation to allow chaining
	 */
	public function twice() {
		return $this->times( 2 );
	}

	/**
	 * Set the expectation that the Spy was called a number of times
	 *
	 * Expectations will be evaluated when `verify()` is called.
	 *
	 * @return Expectation This Expectation to allow chaining
	 */
	public function times( $count ) {
		$this->delay_expectation( function() use ( $count ) {
			if ( isset( $this->expected_args ) ) {
				if ( $this->negation ) {
					if ( $this->silent_failures ) {
						return ! ( new SpiesConstraintWasCalledTimesWith( $count, $this->expected_args ) )->matches( $this->spy );
					}
					return \Spies\TestCase::assertSpyWasNotCalledTimesWith( $this->spy, $count, $this->expected_args );
				}
				if ( $this->silent_failures ) {
					return ( new SpiesConstraintWasCalledTimesWith( $count, $this->expected_args ) )->matches( $this->spy );
				}
				return \Spies\TestCase::assertSpyWasCalledTimesWith( $this->spy, $count, $this->expected_args );
			}

			if ( $this->negation ) {
				if ( $this->silent_failures ) {
					return ! ( new SpiesConstraintWasCalledTimes( $count ) )->matches( $this->spy );
				}
				return \Spies\TestCase::assertSpyWasNotCalledTimes( $this->spy, $count );
			}
			if ( $this->silent_failures ) {
					return ( new SpiesConstraintWasCalledTimes( $count ) )->matches( $this->spy );
			}
			return \Spies\TestCase::assertSpyWasCalledTimes( $this->spy, $count );
		} );
		return $this;
	}

	public function before( $target_spy ) {
		$this->delay_expectation( function() use ( $target_spy ) {
			if ( $this->negation ) {
				if ( $this->silent_failures ) {
					return ! ( new SpiesConstraintWasCalledBefore( $target_spy ) )->matches( $this->spy );
				}
				return \Spies\TestCase::assertSpyWasNotCalledBefore( $this->spy, $target_spy );
			}
			if ( $this->silent_failures ) {
					return ( new SpiesConstraintWasCalledBefore( $target_spy ) )->matches( $this->spy );
			}
			return \Spies\TestCase::assertSpyWasCalledBefore( $this->spy, $target_spy );
		} );
		return $this;
	}

	/**
 	 * Delay an expected behavior
	 *
	 * This will store a function to be run when `verify` is called on this
	 * Expectation. You can delay as many behavior functions as you like. Each
	 * behavior function should throw an Exception if it fails.
	 *
	 * @param function $behavior A function that describes the expected behavior
	 */
	private function delay_expectation( $behavior ) {
		$this->delayed_expectations[] = $behavior;
	}
}
