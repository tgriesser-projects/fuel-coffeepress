<?php

namespace Coffeepress;

use Config;
use Assetic\AssetManager;
use Assetic\FilterManager;
use Assetic\Asset\FileAsset;
use Assetic\Asset\StringAsset;
use Assetic\Filter\GoogleClosure;
use Assetic\Filter\CoffeeScriptFilter;

class CoffeepressException extends \FuelException{}

/**
 * Helper class for generating Coffeescript from
 * other coffeescript files...
 */
class Coffeepress extends \Fuel\Core\View
{
	/**
	 * Template
	 */
	public $template = null;

	/**
	 * Compiled
	 */
	public $compiled = false;

	/**
	 * Compiled
	 */
	public $bare = true;

	/**
	 * Depth
	 */
	public $depth = 3;

	/**
	 * Directory path to the current template
	 */
	protected static $stack = array();

	/**
	 * Scripts which are being compiled
	 */
	protected static $scripts = array();

	/**
	 * Require the Assetic library
	 */
	public static function _init()
	{
		\Package::loaded('assetic') or \Package::load('assetic');
		Config::load('coffeepress', true);
	}

	/**
	 * Forge a new Coffee Compiler
	 * @param string - absolute path to the current directory
	 * @param string - file we're using for execution
	 */
	public static function forge($template = false, $base_dir = false, $null = null)
	{
		$base_dir = $base_dir ? : Config::get('coffeepress.base_dir');
		return new static($template, $base_dir);
	}

	/**
	 * Bare Coffeescript Output
	 */
	public function bare($bare = false)
	{
		$this->bare = $bare;
		return $this;
	}

	/**
	 * Google Closure-Compiled
	 */
	public function compiled($compiled = false)
	{
		$this->compiled = $compiled;
		return $this;
	}

	/**
	 * Sets the depth of the processing 
	 *  -  0  : Bare Coffeescript
	 *  -  1  : Compiled Coffeescript
	 *  -  2  : Compiled w/ raw scripts
	 *  -  3+ : Return Normally
	 */
	public function depth($set)
	{
		$this->depth = $set;
	}

	/**
	 * Creates a new active contect for execution
	 * @param string - absolute path to the current directory
	 * @param string - file we're using for execution
	 */
	public function __construct($template, $path)
	{
		$this->template = $template;
		$this->active_path = $path;
		$this->compiled = Config::get('coffeepress.compiled');
		$this->bare = Config::get('coffeepress.bare');
		array_push(static::$stack, $this);
	}

	/**
	 * Load items from adjacent directories to the template
	 * 
	 */
	public static function __callStatic($name, $args)
  {
  	$current = static::current();

  	$ext = isset($args[1]) ? $args[1] : '.coffee';

  	if (is_dir($current->active_path . $name))
  	{
  		if ( ! is_array($args[0]))
  		{
  			echo PHP_EOL . static::resolve_path($args, $name, $ext) . PHP_EOL;
  		}
  		else
  		{
  			foreach ($args[0] as $arg)
  			{
  				echo PHP_EOL . static::resolve_path($arg, $name, $ext) . PHP_EOL;
  			}
  		}
  	}
  	else
  	{
  		throw new CoffeeException('Error finding method/directory ' . $name);
  	}
	}

	/**
	 * Returns the current depth we're working
	 * with for the coffee stack
	 */
	public static function current()
	{
		return end(static::$stack);
	}

	/**
	 * Returns all HTML rendered via the Coffee compiler
	 * @return string
	 */
	public function render($run_checks = false)
	{
		$output = static::process_file($this->active_path . $this->template);
		
		if ($this->depth === 0)
		{
			return $output;
		}

		$coffeescript = new StringAsset($output, array(
			new CoffeeScriptFilter(Config::get('coffeepress.coffee_bin'), Config::get('coffeepress.node_bin'), true)
		));

		$coffeescript = $coffeescript->dump();

		if ($this->depth === 1)
		{
			return $coffeescript;
		}

		// Set to a temp file to re-render as view
		$temp = tempnam(sys_get_temp_dir(), 'temp');
		
		file_put_contents($temp, $coffeescript);
		
		$final = static::resolve_path($temp);
		
		unlink($temp);

		if ($this->depth === 2)
		{
			return $final;
		}

		if ($this->compiled === true)
		{
			$compiled = new StringAsset($final, array(
				new GoogleClosure\CompilerJarFilter(APPPATH . 'vendor/google-closure/compiler.jar')
			));
			$final = $compiled->dump();
		}

		array_pop(static::$stack);

		return $final;
	}

	/**
	 * Resolves the path to the current request
	 * @param string
	 * @return string
	 */
	protected static function resolve_path($file, $dir = null, $ext = '.coffee')
	{
		$that = self::current();

		if ( ! is_null($dir) and file_exists($f = $that->active_path.$dir.'/'.$file.$ext))
		{
			return $that->process_file($f);
		}
		elseif (file_exists($file))
		{
			return $that->process_file($file);
		}
		else
		{
			throw new CoffeeException('Path not resolved: ' .  $file . ' ' . $dir);
		}
	}

	protected function process_file($file_override = false)
	{
		return str_replace("\t", "  ", parent::process_file($file_override));

	}
	/**
	 * Creates underscore.js "_.mixins"
	 * allowing for a simple/consistent
	 * way to including specific script items
	 * @param array
	 */
	public static function mixins($mixins, $key = null)
	{
		$html = '';
		
		if ( ! is_array($mixins))
		{
			$html = static::resolve_path($mixins, 'mixins');
			
			if (is_null($key))
			{
				echo $html;
			}
			elseif ($key > 0)
			{
				return PHP_EOL . preg_replace("#_.mixin#", '', $html) . PHP_EOL;
			}
			else
			{
				return PHP_EOL . $html . PHP_EOL;
			}
		}
		else
		{
			foreach ($mixins as $k => $mixin)
			{
				$html .= static::mixins($mixin, $k);
			}
			echo $html . PHP_EOL;
		}
	}

	/**
	 * Because templates should be very modular,
	 * they use the module pattern
	 * @param string|array
	 * @return string
	 */
	public static function templates($tmpl)
	{
		if ( ! is_array($tmpl))
		{
			$html = static::resolve_path($tmpl, 'templates', '.jst');
			$template = <<<COFFEE
Templates = do (tmpl = Templates or {}) ->
	tmpl['$tmpl'] = _.template("""$html""")
	tmpl
COFFEE;
			$template .= PHP_EOL;
			echo $template;
		}
		else
		{
			foreach ($tmpl as $tmp)
			{
				static::templates($tmp);
			}	
		}
	}

	/**
	 * Raw dump the script after the coffeescript is compiled
	 * @param string
	 */
	public static function raw_dump($script)
	{
		echo static::$scripts[$script];
	}

	/**
	 * Adds raw javascript to the coffeescript output
	 * @param string
	 */
	public static function raw($script)
	{
		$that = self::current();

		if ( ! is_array($script))
		{
			static::$scripts[$script] = static::resolve_path($script, 'raw', '.js');
			echo '`<?php Coffee::raw_dump("' . $script . '") ?>`' . PHP_EOL;
		}
		else
		{
			foreach ($script as $path)
			{
				static::raw($path);
			}
		}
	}

	/**
	 * Gets the script output
	 * @param string
	 * @param string
	 * @return null
	 */
	protected function save($dir = 'output', $output_dir = false)
	{
		$output_dir = $output_dir?:DOCROOT .'scripts/';
		$action = ! file_exists($output_dir . $app . '.js') ? 'create' : 'update';
		$source = \View::forge('js/'.$app.'.js', array(
			'scripts'   => $this->dev_scripts(),
		), false);

		\File::$action(
			$output_dir,
			$app . '.js',
			$source
		);

		return 'Generated Scripts';
	}
}

class Coffee extends Coffeepress {}
