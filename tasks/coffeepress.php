<?php

namespace Fuel\Tasks;

/**
 * Part of the Coffeepress package for Fuel
 *
 * @package   Coffeepress
 * @version   1.1
 * @author    Tim Griesser <tim@tgriesser.com>
 * @license   MIT License
 * @copyright 2012 Tim Griesser
 */
class Coffeepress
{
	public static function run($item = null)
	{
		if (is_null($item))
		{
			\Cli::write('Please specify a file to build');
		}
		else
		{
			\Cli::write(\Coffee::forge($item)->save());
		}
	}

	public static function check($template_name)
	{
		
	}

	public static function deploy($template_name)
	{
		
	}
}