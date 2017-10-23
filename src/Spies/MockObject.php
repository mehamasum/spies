<?php
namespace Spies;

class MockObject {

	private $spies_on_methods = [];
	private $class_name = null;
	private $ignore_missing_methods = false;

	/**
	 * Create a new MockObject
	 *
	 * If passed nothing, the MockObject will have no methods. Methods can be
	 * added by using the `add_method()` method (hopefully you don't need to add
	 * a method called `add_method` or you're out of luck). That method allows
	 * passing a function to use as the method or it returns a Stub (which is a
	 * Spy) that can be used to program the method using the usual Stub API.
	 *
	 * If passed a class name, the MockObject will automatically have Stub
	 * methods added for each method of the class you specified. These methods
	 * will, by default, do nothing. It is still possible, then, to use
	 * `add_method()` to add new methods or to modify the behavior of existing
	 * methods.
	 *
	 * Normally, if a method is called on the MockObject which has not been
	 * defined by `add_method()`, an Exception will be thrown. However, if you
	 * want to allow and ignore any un-mocked methods, you can call
	 * `and_ignore_missing()` on the MockObject.
	 *
	 * In all of these cases, the methods of the MockObject can be used as Spies
	 * to determine their usage. To spy on a method in this class, call
	 * `spy_on_method()`.
	 *
	 * @param string $class_name optional. The class to mock.
	 */
	public function __construct( $class_name = null ) {
		$this->class_name = $class_name;
		if ( isset( $class_name ) ) {
			if ( ! class_exists( $class_name ) ) {
				throw new \Exception( 'The class "' . $class_name . '" does not exist and could not be used to create a MockObject' );
			}
			array_map( [ $this, 'add_method' ], get_class_methods( $class_name ) );
		}
	}

	public function __call( $function_name, $args ) {
		if ( ! isset( $this->$function_name ) || ! is_callable( $this->$function_name ) ) {
			if ( $this->ignore_missing_methods ) {
				return;
			}
			throw new UndefinedFunctionException( 'Attempted to call un-mocked method "' . $function_name . '" with ' . json_encode( $args ) );
		}
		return call_user_func_array( $this->$function_name, $args );
	}

	/**
	 * Spy on a method on this object
	 *
	 * If the method does not already exist, this is an alias for `add_method()`.
	 * If the method _does_ exist, this will install a Spy on the existing method
	 * and return that Spy.
	 *
	 * @param string $function_name The name of the function to add to this object
	 * @param Spy|callback $function optional A callable function or Spy to be used when the new method is called. Defaults to a new Spy.
	 * @return Spy|callback The Spy or callback
	 */
	public function spy_on_method( $function_name, $function = null ) {
		if ( isset( $this->spies_on_methods[ $function_name ] ) ) {
			return $this->spies_on_methods[ $function_name ];
		}
		return $this->add_method( $function_name, $function );
	}

	/**
	 * Add a public method to this object and return that method.
	 *
	 * Creates and returns a Spy if no function is provided.
	 *
	 * When called without a second argument, returns a stub (which, remember, is
	 * also a Spy), so you can program its behavior or query it for expectations.
	 * You can also use the second argument to pass a function (or Spy)
	 * explicitly, in which case whatever you pass is what will be returned.
	 *
	 * Since it returns a Spy by default, you can use this to stub behaviors:
	 *
	 * `$mock_object->add_method( 'do_something' )->that_returns( 'hello world' );`
	 *
	 * @param string $function_name The name of the function to add to this object
	 * @param Spy|callback $function optional A callable function or Spy to be used when the new method is called. Defaults to a new Spy.
	 * @return Spy|callback The Spy or callback
	 */
	public function add_method( $function_name, $function = null ) {
		if ( ! isset( $function ) ) {
			$function = isset( $this->$function_name ) ? $this->$function_name : new \Spies\Spy();
		}
		$reserved_method_names = [
			'add_method',
			'spy_on_method',
			'and_ignore_missing',
		];
		if ( in_array( $function_name, $reserved_method_names ) ) {
			throw new \Exception( 'The function "' . $function_name . '" added to this mock object could not be used because it conflicts with a built-in function' );
		}
		if ( ! is_callable( $function ) ) {
			throw new \Exception( 'The function "' . $function_name . '" added to this mock object was not a function' );
		}
		if ( $function instanceof Spy ) {
			$function->set_function_name( $function_name );
		}
		$function_spy = ( $function instanceof Spy ) ? $function : new \Spies\Spy();
		$this->$function_name = $function;
		$this->spies_on_methods[ $function_name ] = $function_spy;
		return $function;
	}

	/**
	 * Prevent throwing UndefinedFunctionException when an unmocked method is called
	 *
	 * @return MockObject This object.
	 */
	public function and_ignore_missing() {
		$this->ignore_missing_methods = true;
		return $this;
	}

	public static function mock_object( $class_name = null ) {
		return new MockObject( $class_name );
	}

	public static function mock_object_of( $class_name = null ) {
		return new MockObject( $class_name );
	}
}
