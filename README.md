# Spies

A library to make testing in PHP so much easier. You can install it in a PHP project by following [the instructions below](#installation).

What is it? If you've ever used [sinon](http://sinonjs.org/) in JavaScript testing, you know about the concept of *Test Spies*, and in many ways this library is just implementing those concepts in PHP. It also includes *Expectations* to simplify spy assertions, inspired by [sinon-chai](https://github.com/domenic/sinon-chai).

If you want to just skip to the details, you can [read the API here](API.md).

If you are not familiar with Test Spies, here's a brief primer: Basically they are objects that behave like functions and keep a record of how they are called. You can inject them into objects when writing tests and monitor the spies to determine if the objects are behaving as you expect.

```php
$spy = new \Spies\Spy();
$spy( 'hello', 'world' );

$spy->was_called(); // Returns true
$spy->was_called_times( 1 ); // Returns true
$spy->was_called_times( 2 ); // Returns false
$spy->get_times_called(); // Returns 1
$spy->was_called_with( 'hello', 'world' ); // Returns true
$spy->was_called_with( 'goodbye', 'world' ); // Returns false
```

Spies can also be programmed to behave in certain ways (in which case they are more properly called "stubs" or "mocks"), forcing your code down certain paths in order to test specific behavior.

```php
\Spies\stub_function( 'add_one' )->when_called->with( 5 )->will_return( 6 );
\Spies\stub_function( 'add_one' )->when_called->with( 1 )->will_return( 2 );

add_one( 5 ); // Returns 6
add_one( 1 ); // Returns 2
```

In PHP, we often need to spy on whole objects with instance methods, so Spies provides a mechanism to do that as well:

```php
class Greeter {
    public function say_hello() {
        return 'hello';
    }

    public function say_goodbye() {
        return 'goodbye';
    }
}

function test_greeter() {
    $mock = \Spies\mock_object_of( 'Greeter' );
    $mock->add_method( 'say_hello' )->that_returns( 'greetings' );
    $greet = $mock->spy_on_method( 'greet' );
    $this->assertEquals( 'greetings', $mock->say_hello() );
    $this->assertEquals( null, $mock->say_goodbye() );
    $mock->greet();
    $this->assertTrue( $greet->was_called() );
}
```

The final piece, Expectations add a layer of syntax to test assertions that should be easier to read as well as providing better failure messages:

```php
function test_spy_is_called_correctly() {
    $spy = \Spies\make_spy();
    $spy( 'hello', 'world', 7 );
    $spy( 'hello', 'world', 8 );
    $expectation = \Spies\expect_spy( $spy )->to_have_been_called->with( 'hello', 'world', \Spies\any() )->twice();
    $expectation->verify();
}
```

Spies was designed as an optional replacement for the very excellent [WP_Mock](https://github.com/10up/wp_mock) and [Mockery](http://docs.mockery.io/en/latest/), both of which are powerful but have many aspects and quirks that I don't find intuitive.

Suggestions, bug reports, and feature requests all welcome!

# Installation

A library to make testing in PHP so much easier. You can install it in a PHP project by running:

`composer require --dev sirbrillig/spies`.

Then just make sure you include the autoloader somewhere in your code:

If using in PHPUnit, you can add autoload to your `phpunit.xml` file:
```
<phpunit bootstrap="vendor/autoload.php">
...
```

Otherwise you can include the autoloader manually:
```
require( './vendor/autoload.php' );
```

However, please see [the note on mocking and spying on existing functions](#spying-and-mocking-existing-functions). If you need to do this, you will need to add an explicit bootstrap file.

# The Details

## Global Functions

If you  want to create a Spy for a global function (a function in the global namespace, like WordPress's `wp_insert_post`), you can pass the name of the global function to `\Spies\get_spy_for()`:

```php
function test_calculation() {
	$add_one = \Spies\get_spy_for( 'add_together' );

	add_together( 2, 3 );

	$expectation = \Spies\expect_spy( $add_one )->to_have_been_called->with( 2, 3 ); // Passes
	$expectation->verify();
}
```

You can Spy on functions defined within a namespace in the same way:

```php
function test_calculation() {
	$add_one = \Spies\get_spy_for( '\Calculator\add_together' );

	\Calculator\add_together( 2, 3 );

	$expectation = \Spies\expect_spy( $add_one )->to_have_been_called->with( 2, 3 ); // Passes
	$expectation->verify();
}
```

## Stubs and Mocks

You can create stubs with the `\Spies\stub_function()` method. A stub is a fake function that can be called like a real function except that you control its behavior.

Stubs can also be used to mock a global function or a namespaced function, just like a Spy. In fact, a stub is also a Spy, which means you can query it for any information you like.

There are a few basic behaviors you can program into a stub:

1. You can simply use one to replace a global function (it will return null).
2. You can use one to return a specific value when called.
3. You can use one to return a specific value when called with specific arguments.
4. You can use one to return one of the arguments it was passed.
5. You can use one to call a substitute function.

Here's just setting a return value:
```php
\Spies\stub_function( 'get_color' )->and_return( 'green' );

get_color(); // Returns 'green'
```

Here's returning a value with certain arguments:
```php
\Spies\stub_function( 'add_one' )->when_called->with( 5 )->will_return( 6 );
\Spies\stub_function( 'add_one' )->when_called->with( 1 )->will_return( 2 );

add_one( 5 ); // Returns 6
add_one( 1 ); // Returns 2
```

Here's one returning one of its arguments:
```php
\Spies\stub_function( 'get_first' )->when_called->will_return( \Spies\passed_arg( 0 ) );

get_first( 5, 6, 7 ); // Returns 5
get_first( 1, 2, 3 ); // Returns 1
```

Here's one returning the result of a substitute function:
```php
\Spies\stub_function( 'add_one' )->and_return( function( $a ) {
	return $a + 1;
} );

add_one( 5 ); // Returns 6
add_one( 1 ); // Returns 2
```

## Objects

Sometimes you need to create a whole object with stubs as functions. In that case you can use `\Spies\mock_object()` which will return an object that can be passed around. The object by default has no methods, but you can use `add_method()` to add some.

`add_method()`, or its alias, `spy_on_method()`, when called without a second argument, returns a stub (which, remember, is also a Spy), so you can program its behavior or query it for expectations. You can also use the second argument to pass a function (or Spy) explicitly, in which case whatever you pass is what will be returned.

```php
function test_calculation() {
	$adder = \Spies\mock_object();
	$adder->add_method( 'add_one' )->when_called->with( 6 )->will_return( 7 );
	$add_one = $adder->spy_on_method( 'add_one' );

	$calculator = new Calculator( $adder );
	$calculator->add_one( 4 ); // Returns null
	$calculator->add_one( 6 ); // Returns 7

	\Spies\expect_spy( $add_one )->to_have_been_called(); // Passes
	\Spies\expect_spy( $add_one )->to_have_been_called->with( 2 ); // Fails
	\Spies\finish_spying(); // Verifies all Expectations
}
```

It can be tedious to call `add_method()` for every public method of an existing class that you are trying to mock. For that reason you can use `\Spies\mock_object_of( $class_name )` which will create a `MockObject` and automatically add a Spy for each public method on the original class. These Spies will all return null by default, but you can replace any of them with your own Spy by using `add_method()` as above.

```php
class Greeter {
	public function say_hello() {
		return 'hello';
	}

	public function say_goodbye() {
		return 'goodbye';
	}
}

function test_greeter() {
	$mock = \Spies\mock_object_of( 'Greeter' );
	$mock->add_method( 'say_hello' )->that_returns( 'greetings' );
	$this->assertEquals( 'greetings', $mock->say_hello() );
	$this->assertEquals( null, $mock->say_goodbye() );
}
```

If you'd rather not call `add_method()` and you don't have an original class to copy, you can also just ignore all method calls on the object using `and_ignore_missing()`:

```php
function test_greeter() {
	$mock = \Spies\mock_object()->and_ignore_missing();
	$this->assertEquals( null, $mock->say_goodbye() );
}
```

## Object Method Delegation

Sometimes it's helpful to be able to be able to spy on actual methods of an object, or to replace some methods on an object, but not others. This involves creating a delegate object, which can be done by passing a class instance to `\Spies\mock_object()`.

The resulting `MockObject` will forward all method calls to the original class instance, except those overridden by using `add_method()`. It's possible to use `spy_on_method()` to spy on any method call of the object, just as you would do with a regular MockObject.

```php
class Greeter {
	public function say_hello() {
		return 'hello';
	}

	public function say_goodbye() {
		return 'goodbye';
	}
}

function test_greeter() {
	$mock = \Spies\mock_object( new Greeter() );
	$say_goodbye = $mock->spy_on_method( 'say_goodbye' );
	$mock->add_method( 'say_hello' )->that_returns( 'greetings' );
	$this->assertEquals( 'greetings', $mock->say_hello() );
	$this->assertEquals( 'goodbye', $mock->say_goodbye() );
	$this->assertSpyWasCalled( $say_goodbye );
}
```

## Expectations

Spies can be useful all by themselves, but Spies also provides the `Expectation` class to make writing your test expectations easier.

Let's say we have a Spy and want to verify if it has been called in a PHPUnit test:
```php
function test_spy_is_called() {
	$spy = \Spies\make_spy();
	$spy();
	$this->assertTrue( $spy->was_called() );
}
```

That works, but here's another way to write it:
```php
function test_spy_is_called() {
	$spy = \Spies\make_spy();
	$spy();
	$expectation = \Spies\expect_spy( $spy )->to_have_been_called();
	$expectation->verify();
}
```

They're both totally valid. Expectations just add some more syntactic sugar to your tests and speed the debugging process by improving failure messages. Particularly, they allow building up a set of expected behaviors and then validating all of them at once. Let's use a more complex example. Here it is with just a Spy:

```php
function test_spy_is_called_correctly() {
	$spy = \Spies\make_spy();
	$spy( 'hello', 'world', 7 );
	$spy( 'hello', 'world', 8 );
	$this->assertTrue( $spy->was_called_with( 'hello', 'world', \Spies\any() ) );
	$this->assertTrue( $spy->was_called_times( 2 ) );
}
```

And here with Expectations:

```php
function test_spy_is_called_correctly() {
	$spy = \Spies\make_spy();
	$spy( 'hello', 'world', 7 );
	$spy( 'hello', 'world', 8 );
	$expectation = \Spies\expect_spy( $spy )->to_have_been_called->with( 'hello', 'world', \Spies\any() )->twice();
	$expectation->verify();
}
```

That last part, `$expectation->verify()` is what actually tests all the expected behaviors. You can also call the function `\Spies\finish_spying()` which will do the same thing, and can be put in a `tearDown` method.

### Better failures

Perhaps the most useful thing about Expectations is that they provide better failure messages. Whereas `$this->assertTrue( $spy->was_called_with( 'hello' ) )` and `\Spies\expect_spy( $spy )->to_have_been_called->with( 'hello' )` both assert the same thing, the former will only tell you "false is not true", and the Expectation will fail with something like this message:

```
Expected "anonymous function" to be called with ['hello'] but instead it was called with ['goodbye']
```

### finish_spying

To complete an expectation during a test, and to keep functions in the global scope from interfering with one another, it's **very important** to call `\Spies\finish_spying()` after each test.

`finish_spying()` does three things:

1. Calls `verify()` on each Expectation. `expect_spy()` only prepares the expectation. It is not tested until `verify()` is called.
2. Clears all current Spies and mocked functions.
3. Clears all current Expectations.

Because Expectations are only evaluated when we call `verify()` or `finish_spying()`, you can use expectations before or after the code that is being tested. There's syntactic sugar to make it sound right either way. The following two are the same:

```php
function tearDown() {
	\Spies\finish_spying();
}

function test_calculation() {
	$add_one = \Spies\get_spy_for( 'add_together' );

	add_together( 2, 3 );

	\Spies\expect_spy( $add_one )->to_have_been_called->with( 2, 3 ); // Passes
}
```

```php
function tearDown() {
	\Spies\finish_spying();
}

function test_calculation() {
	$add_one = \Spies\get_spy_for( 'add_together' );

	\Spies\expect_spy( $add_one )->to_be_called->with( 2, 3 ); // Passes

	add_together( 2, 3 );
}
```

### Argument lists

If you use `with()` to test an Expectation, sometimes you don't care about the value of an argument. In this case you can use `\Spies\Expectation::any()` in place of that argument:

```php
function tearDown() {
	\Spies\finish_spying();
}

function test_calculation() {
	$add_one = \Spies\get_spy_for( 'add_together' );

	\Spies\expect_spy( $add_one )->to_be_called->with( \Spies\Expectation::any(), \Spies\Expectation::any() ); // Passes

	add_together( 2, 3 );
}
```

If you need to match just part of a string, you can use `\Spies\match_pattern()`.

```php
$spy = \Spies\get_spy_for( 'run_experiment' );
run_experiment( 'slartibartfast' );
\Spies\expect_spy( $spy )->to_have_been_called->with( \Spies\match_pattern( '/bart/' ) );
\Spies\finish_spying();
```

You can also use `\Spies\match_array()` to match elements of an array while ignoring other parts:

```php
function tearDown() {
	\Spies\finish_spying();
}

function test_name() {
	$say_hello = \Spies\get_spy_for( 'say_hello' );

	\Spies\expect_spy( $say_hello )->to_be_called->with( \Spies\match_array( [ 'name' => 'Raistlin' ] ) ) ); // Passes

	say_hello( [ 'name' => 'Raistlin', 'job' => 'wizard', 'robes' => 'black' ] );
}
```


## PHPUnit Custom Assertions

If you prefer to use PHPUnit custom assertions rather than Expectations, those are also available (although you must base your test class on `\Spies\TestCase`):

```php
class MyTest extends \Spies\TestCase {
    function test_spy_is_called_correctly() {
        $spy = \Spies\make_spy();
        $spy( 'hello', 'world', 7 );
        $spy( 'hello', 'world', 8 );
        $this->assertSpyWasCalledWith( $spy, [ 'hello', 'world', \Spies\any() ] );
    }
}
```

Custom assertions will provide detailed information about why your test failed, which is much better than "false is not true".

```
Failed asserting that a spy is called with arguments: ( "a", "b", "c" ).
a spy was actually called with:
 1. arguments: ( "b", "b", "c" ),
 2. arguments: ( "m", "b", "c" )
```

See the [API document](API.md) for the full list of custom assertions available.

## Assertion Helpers

For any assertion, even those not involving Spies or Stubs, it can be helpful to compare partial arrays in the same manner as `match_array()`. You can use the helper function `do_arrays_match()` to do this:

```php
$array = [ 'baz' => 'boo', 'foo' => 'bar' ];
$this->assertTrue( \Spies\do_arrays_match( $array, \Spies\match_array( [ 'foo' => 'bar' ] ) ) );
```

# Spying and Mocking existing functions

PHP does not allow mocking existing functions. However, there is a library called [Patchwork](http://patchwork2.org/) which allows this. If that library is loaded, it will be used by Spies. The library must be loaded *before* Spies. One way to do this is to use a test bootstrap file.

If using in PHPUnit, you can require the bootstrap from your `phpunit.xml` file:
```
<phpunit bootstrap="tests/bootstrap.php">
...
```

Here is an example bootstrap file that also loads the autoloader:

```php
<?php
$autoload  = 'vendor/autoload.php';
$patchwork = 'vendor/antecedent/patchwork/Patchwork.php';

# require patchwork first
if ( file_exists( $patchwork ) ) {
	require_once $patchwork;
}

if ( file_exists( $autoload ) ) {
	require_once $autoload;
}
```

If Patchwork is loaded, you will be able to use `mock_function()` and `get_spy_for()` on existing functions:

``` php
function sayHello() {
  return 'hello';
}
//...
\Spies\mock_function( 'sayHello' )->and_return( 'bye' );
$this->assertEquals( 'bye', sayHello() );
```

``` php
function sayHello() {
  return 'hello';
}
//...
$spy = \Spies\get_spy_for( 'sayHello' );
sayHello();
$this->assertTrue( $spy->was_called() );
```

## Contributing

Please submit an issue or PR!

[![CircleCI](https://circleci.com/gh/sirbrillig/spies.svg?style=svg)](https://circleci.com/gh/sirbrillig/spies)
