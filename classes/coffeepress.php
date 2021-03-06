<?php

namespace Coffeepress;

use Config;

use Assetic\AssetManager;
use Assetic\FilterManager;
use Assetic\Asset\FileAsset;
use Assetic\Asset\StringAsset;
use Assetic\Filter\GoogleClosure;
use Assetic\Filter\CoffeeScriptFilter;
use Assetic\Filter\UglifyJsFilter;

class CoffeepressException extends \FuelException{}

/**
 * Helper class for generating Coffeescript from
 * other coffeescript files...
 */
class Coffeepress extends \Fuel\Core\View
{
	/**
	 * Location of relative assets & template
	 */
	public $base_dir = null;

	/**
	 * Where the item is to be saved (from config)
	 */
	public $output_dir = null;

	/**
	 * Name of the output to be saved
	 */
	public $output_file = null;

	/**
	 * Template name ('full name')
	 */
	public $template = null;

	/**
	 * Whether to use Google-Closure compiler in output
	 */
	public $compiled = false;

	/**
	 * Whether to use the top-level function wrapper
	 */
	public $bare = true;

	/**
	 * Depth
	 */
	public $depth = 3;

	/**
	 * Final rendered output
	 */
	protected $rendered;

	/**
	 * Directory path to the current template
	 */
	protected static $stack = array();

	/**
	 * Scripts which are being compiled
	 */
	protected static $scripts = array();

	/**
	 * The type of compression used, either uglify or closure
	 */
	public $compression = 'uglify';

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
	public static function forge($item = false, $stub = null, $null = null)
	{
		if ( ! is_array($item))
		{
			$item = Config::get('coffeepress.output.'.$item, $item);
		}

		if (is_array($item))
		{
			return new static($item, $stub, $null);
		}
		else
		{
			throw new CoffeepressException('Error initializing ' . $item);
		}
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
		return $this;
	}

	/**
	 * Creates a new active contect for execution
	 * @param string - absolute path to the current directory
	 * @param string - file we're using for execution
	 */
	public function __construct($item, $stub, $null)
	{
		$this->base_dir = Config::get('coffeepress.base_dir');
		
		foreach ($item as $k => $v)
		{
				$this->$k = $v;
		}

		array_push(static::$stack, $this);
	}

	public function set_var($key, $value = null)
	{
		if (is_array($key))
		{
			foreach ($key as $k => $v)
			{
				call_user_func(array($this, 'set_var'), $k, $v);
			}
		}
		else
		{
			$this->{$key} = $value;
		}
		return $this;
	}

	/**
	 * Load items from adjacent directories to the template
	 * 
	 */
	public static function __callStatic($name, $args)
  {
  	$current = static::current();

  	$ext = isset($args[1]) ? $args[1] : '.coffee';

  	try
  	{
  		if ( ! is_array($args[0]))
  		{
  			if (substr($args[0], -1) === '*')
				{
					static::resolve_star($args[0], $name);
				}
				else
				{
  				echo static::resolve_path($args[0], $name, $ext) . PHP_EOL;
  			}
  		}
  		else
  		{
  			foreach ($args[0] as $arg)
  			{
  				echo PHP_EOL . static::resolve_path($arg, $name, $ext) . PHP_EOL;
  			}
  		}
  	}
  	catch (Exception $e)
  	{
  		throw new CoffeepressException('Error finding method/directory ' . $name);
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
		$output = static::process_file($this->base_dir . $this->template);
		
		if ($this->depth === 0)
		{
			return $output;
		}

		$coffeescript = new StringAsset($output, array(
			new CoffeeScriptFilter(Config::get('coffeepress.coffee_bin'), Config::get('coffeepress.node_bin'), true)
		));	
	
		try
		{
			$coffeescript = $coffeescript->dump();
		}
		catch (\Exception $e)
		{
			if (\Fuel::$is_cli)
			{
				\Cli::write(\Cli::color($e->getMessage(), 'red'));
				die;
			}
			else
			{
				echo PHP_EOL . '<pre><code>' . PHP_EOL;
				echo $e->getMessage();
				echo PHP_EOL . '</code></pre>' . PHP_EOL;

				echo PHP_EOL . '<pre><code>' . PHP_EOL;
				echo $output;
				echo PHP_EOL . '</code></pre>' . PHP_EOL;
				die;
			}
		}

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
			if ($this->compression === 'closure')
			{
				$compiled = new StringAsset($final, array(
					new GoogleClosure\CompilerJarFilter(\Config::get('coffeepress.closure_jar'), Config::get('coffeepress.java_bin'))
				));
			
			}
			elseif ($this->compression === 'uglify')
			{
				$compiled = new StringAsset($final, array(
					new UglifyJsFilter(\Config::get('coffeepress.uglify_bin'), Config::get('coffeepress.node_bin'))
				));
			}
			
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

		if (stristr($file, '.') !== false)
		{
			$ext = null;
		}

		if ( ! is_null($dir))
		{
			if (file_exists($f = $that->base_dir.$dir.'/'.$file.$ext) 
				or file_exists($f = \Config::get('coffeepress.base_dir').$dir.'/'.$file.$ext))
			{
				return $that->process_file($f);
			}
		}
		
		if (file_exists($file))
		{
			return $that->process_file($file);
		}
		else
		{
			throw new CoffeepressException('Path not resolved: ' .  $file . ' ' . $dir);
		}
	}

	/**
	 * Resolve Star (directory paths)
	 * note: star directories only work on relative paths to the root
	 */
	protected static function resolve_star($path, $type)
	{
		$that = static::current();
    
		if (is_dir($f = $that->base_dir . $type . '/' . substr($path, 0, -1)))
		{
			$fs = \File::read_dir($f);

			if ( ! empty($fs))
			{
				call_user_func(array('static', $type), static::key_collapse($fs));
			}
    }
	}

	/**
	 * Checks if the array's keys/sub-keys aren't (int)...
	 * if so it adds them to a stack of collapsed keys
	 * @param array
	 */
	public static function key_collapse($array)
	{
		$stack = array();
		$depth = array();
		$collapse = function($val, $key, $func) use(&$stack, &$depth)
		{
			if ( ! is_int($key))
			{
				array_push($depth, $key);
			}
			else
			{
				array_push($depth, $val);
			}
			if (is_array($val))
			{
				array_walk($val, $func, $func);
			}
			else
			{
				$stack[] = implode($depth);
			}
			array_pop($depth);
		};
		
		array_walk($array, $collapse, $collapse);
		
		return $stack;
	}


	/**
	 * Process the file, replacing the tabs with double spaces for consistency
	 */
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
			if (substr($mixins, -1) === '*')
			{
				static::resolve_star($tmpl, 'mixins');
			}
			else
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
			if (substr($tmpl, -1) === '*')
			{
				static::resolve_star($tmpl, 'templates');
			}
			else
			{
				$tmpl = str_replace('.jst', '', $tmpl);
				$html = static::resolve_path($tmpl, 'templates', '.jst');
				echo 'Templates[\''.$tmpl.'\'] = _.template("""'.$html.'""")' . PHP_EOL;
			}
		}
		else
		{
			foreach ($tmpl as $tmp)
			{
				static::templates($tmp);
			}	
		}
	}

	/*
	 * Because templates should be very modular,
	 * they use the module pattern
	 * @param string|array
	 * @return string
	 
	public static function pages($page)
	{
		if ( ! is_array($page))
		{
			if (substr($page, -1) === '*')
			{
				static::resolve_star($page, 'pages');
			}
			else
			{
				$page = str_replace('.coffee', '', $page);
				$coffee = static::resolve_path($page, 'pages', '.coffee');
				$coffee = preg_replace("/\n/","\n  ", $coffee);
				echo "Pages['$page'] = " . $coffee . PHP_EOL;
			}
		}
		else
		{
			foreach ($page as $pg)
			{
				static::pages($pg);
			}	
		}
	}
	*/

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
	public function save($path = false, $file = false)
	{
		$this->output_dir = $path ? : $this->output_dir;
		$this->output_file = $file ? : $this->output_file;
		
		if (is_null($this->output_dir) or is_null($this->output_file))
		{
			\Cli::write('Error finding the output directory');
			\Cli::write(print_r($this, true));
			die;
		}

		$action = ! file_exists($this->output_dir . $this->output_file) ? 'create' : 'update';
		$output = $this->render();

		\File::$action(
			$this->output_dir,
			$this->output_file,
			$output
		);

		return 'Saved: ' . $this->output_dir . $this->output_file;
	}
}

/**
 * Short syntax for the Coffeepress extension
 */
class Coffee extends Coffeepress {}
