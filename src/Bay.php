<?php namespace Myth\Bay;

/**
 * Class Bay
 *
 * @package Myth\Bay
 */
class Bay {

	/**
	 * Instance of our customized class loader.
	 *
	 * @var LibraryFinderInterface|null
	 */
	protected $finder = null;

	/**
	 * Instance of our cache access library.
	 *
	 * @var CacheInterface|null
	 */
	protected $cache = null;

	//--------------------------------------------------------------------

	public function __construct(LibraryFinderInterface $finder=null, CacheInterface $cache=null)
	{
	    if (is_object($finder))
	    {
		    $this->finder = $finder;
	    }

		if (is_object($cache))
		{
			$this->cache = $cache;
		}
	}

	//--------------------------------------------------------------------


	/**
	 * The primary method used. Will attempt to locate the library, run
	 * the requested method, and return the rendered HTML.
	 *
	 * @param string        $library
	 * @param string|array  $params
	 * @param string        $cache_name
	 * @param int           $cache_ttl      // Time in _minutes_
	 *
	 * @return null|string
	 */
	public function display($library, $params=null, $cache_name=null, $cache_ttl=0)
	{
		list($class, $method) = $this->determineClass($library);

		// Is it cached?
		$cache_name = ! empty($cache_name) ? $cache_name : $class . $method . md5(serialize($params));

		if (! empty($this->cache) && $output = $this->cache->get($cache_name))
		{
			return $output;
		}

		// Not cached - so grab it...
		$instance = new $class();

		if (! method_exists($instance, $method))
		{
			throw new \InvalidArgumentException("{$class}::{$method} is not a valid method.");
		}

		if ($this->isStaticMethod($instance, $method))
		{
			$output = $instance::{$method}( $this->prepareParams($params) );
		}
		else
		{
			$output = $instance->{$method}($this->prepareParams($params));
		}

		// Can we cache it?
		if (! empty($this->cache))
		{
			$this->cache->set($cache_name, $output, $cache_ttl);
		}

		return $output;
	}

	//--------------------------------------------------------------------

	//--------------------------------------------------------------------
	// Utility Methods
	//--------------------------------------------------------------------

	/**
	 * Attempts to locate and load the class passed in the library
	 * portion of the display() method.
	 *
	 * First, we will try to autoload the file. If it cannot be found there,
	 * we will try to run a framework-specific loader, if the user provided
	 * one in __construct();
	 *
	 * @param $library
	 * @return array
	 */
	public function determineClass($library)
	{
		$found = false;

		// We don't want to actually call static methods
		// by default, so convert any double colons.
		$library = str_replace('::', ':', $library);

		list($class, $method) = explode(':', $library);

		if (empty($class))
		{
			throw new \InvalidArgumentException('No class provided to Bay::display().');
		}

		if (! class_exists($class, true))
		{
			// Try the Finder to see if it can find it...
			if (! is_null($this->finder))
			{
				if ($this->finder->find($class))
				{
					$found = true;
				}
			}
		}
		else
		{
			$found = true;
		}

		if (! $found)
		{
			throw new \InvalidArgumentException('Unable to locate class '. $class .', provided to Bay::display().');
		}

		if (empty($method))
		{
			$method = 'index';
		}

		return [$class, $method];
	}

	//--------------------------------------------------------------------

	/**
	 * Parses the params attribute. If an array, returns untouched.
	 * If a string, it should be in the format "key1=value key2=value".
	 * It will be split and returned as an array.
	 *
	 * @param $params
	 * @return array|null
	 */
	public function prepareParams($params)
	{
		if (! is_string($params) && ! is_array($params))
		{
			return null;
		}

	    if (is_string($params))
	    {
		    if (empty($params))
		    {
			    return null;
		    }

		    $new_params = [];

		    $separator = ' ';
		    if (strpos($params, ',') !== false)
		    {
			    $separator = ',';
		    }

		    $params = explode($separator, $params);
		    unset($separator);

		    foreach ($params as $p)
		    {
			    list($key, $val) = explode('=', $p);

			    $new_params[ trim($key) ] = trim($val, ', ');
		    }

		    $params = $new_params;
		    unset($new_params);
	    }

		if (is_array($params) && ! count($params))
		{
			return null;
		}

		return $params;
	}

	//--------------------------------------------------------------------

	/**
	 * Quick check for if a class method is static.
	 *
	 * @param $class
	 * @param $method
	 *
	 * @return mixed
	 */
	protected function isStaticMethod($class, $method)
	{
		$mirror = new \ReflectionMethod($class, $method);

		return $mirror->isStatic();
	}

	//--------------------------------------------------------------------


}