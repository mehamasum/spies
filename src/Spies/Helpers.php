<?php
namespace Spies;

class Helpers {
	/**
	 * Compare argument lists
	 *
	 * You should not need to call this directly.
	 */
	public static function do_args_match( $a, $b ) {
		if ( $a === $b ) {
			return true;
		}
		if ( count( $a ) !== count( $b ) ) {
			return false;
		}
		$index = 0;
		foreach ( $a as $arg ) {
			if ( ! self::do_vals_match( $arg, $b[ $index ] ) ) {
				return false;
			}
			$index ++;
		}
		return true;
	}

	private static function do_vals_match( $a, $b ) {
		if ( $a === $b ) {
			return true;
		}
		if ( is_object( $a ) || is_object( $b ) ) {
			$array_a = is_object( $a ) ? (array) $a : $a;
			$array_b = is_object( $b ) ? (array) $b : $b;
			if ( $array_a === $array_b ) {
				return true;
			}
		}
		if ( $a instanceof \Spies\AnyValue || $b instanceof \Spies\AnyValue ) {
			return true;
		}
		return false;
	}

	public static function array_clone( $array ) {
		return array_map( function( $element ) {
			return ( ( is_array( $element ) )
				? Helpers::array_clone( $element )
				: ( ( is_object( $element ) )
				? clone $element
				: $element
			)
		);
		}, $array );
	}
}
