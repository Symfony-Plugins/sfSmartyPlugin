<?php
/**
 * sfSmarty
 *
 * @package
 * @author jbadwal
 * @copyright Copyright (c) 2008
 * @version $Id$
 * @access public
 **/
class sfSmarty {

    protected static $smarty = null;
  	protected static $templateSecurity = false;
  	protected static $cache;
  	protected static $log;
  	
  	protected static $usedHelpers;
  	
	protected static $smartyHelperLoaded = false;
    protected static $knownFunctions;
    protected static $loadedHelpers;

    //const CACHENAMESPACE = 'Smarty';
	
    /**
     * sfSmarty constructor
     *
     **/
    final private function __construct()
    {
    }
        
    /**
     * Get the instance of sfSmarty
     * 
     * @return sfSmarty
     */
    public static function getInstance()
    {
        static $instance;
        
        if (is_null($instance)) {
        	$instance = new sfSmarty();
    		
       		if (!self::$log) {
            	self::$log = sfConfig::get('sf_logging_enabled')? sfContext::getInstance()->getLogger() : false;
    		}        	
    		if (!self::$cache) {
            	self::$cache = new sfFileCache(array("cache_dir" => sfConfig::get('sf_cache_dir')));
    		}
    		if (!self::$smarty) {
    			self::$smarty = $instance->getSmarty();
    		}
        }
        return $instance;
    }
    
    /**
    * sfSmarty::getSmarty()
    *
    * @return Smarty object
	* @access public
    **/
    public function getSmarty()
    {           
		if (!self::$smarty) {
			// get the path and instantiate the smarty object
			$smartyClassPath = sfConfig::get('app_sfSmarty_class_path', 'Smarty');
			if (substr($smartyClassPath, -1) != DIRECTORY_SEPARATOR) {
				$smartyClassPath .= DIRECTORY_SEPARATOR;
			}
			require_once($smartyClassPath . 'Smarty.class.php');
			self::$smarty = new Smarty();

			// set the smarty cache directory
			$smartyDirs = sfConfig::get('app_sfSmarty_cache_dir' , sfConfig::get('sf_cache_dir') . DIRECTORY_SEPARATOR . 'Smarty');
			if (substr($smartyDirs, -1) != DIRECTORY_SEPARATOR) {
				$smartyDirs .= DIRECTORY_SEPARATOR;
			}
			self::$smarty->compile_dir = $smartyDirs . 'templates_c';
			self::$smarty->cache_dir = $smartyDirs . 'cache';
			self::$templateSecurity = sfConfig::get('app_sfSmarty_template_security', false);
			self::$smarty->security = self::$templateSecurity;
			if (!file_exists(self::$smarty->compile_dir)) {
				if (!mkdir(self::$smarty->compile_dir, 0777, true)) {
					throw new sfCacheException('Unable to create cache directory "' . self::$smarty->compile_dir . '"');
				}
				if (self::$log) self::$log->info(sprintf('{sfSmarty} creating compile directory: %s', self::$smarty->compile_dir));				
			}
			if (!file_exists(self::$smarty->cache_dir)) {
				if (!mkdir(self::$smarty->cache_dir, 0777, true)) {
					throw new sfCacheException('Unable to create cache directory "' . self::$smarty->cache_dir . '"');
				}
				if (self::$log) self::$log->info(sprintf('{sfSmarty} creating cache directory: %s', self::$smarty->cache_dir));
			}
			self::$smarty->register_compiler_function('use', array($this, 'smartyCompilerfunctionUse'));
			self::$smarty->register_postfilter(array('sfSmarty', 'smartyPostFilter'));
		}			
		return self::$smarty;
   	}
   	
   	private function getSfData($view, $escaping = ESC_RAW) 
   	{
   		$current_sf_data = self::$smarty->get_template_vars('sf_data');
		if (empty($current_sf_data)) {
			$sf_data = sfOutputEscaper::escape($escaping, $view->getAttributeHolder()->getAll());	
		} else {
			foreach ($current_sf_data as $key => $value) {
				$c_sf_data[$key] = $value;
			}
			$sf_data = sfOutputEscaper::escape($escaping, array_merge($view->getAttributeHolder()->getAll(), $c_sf_data)); 
		}
		return $sf_data;
   	}
   	
    /**
    * sfSmarty::renderFile()
    * render template file using Smarty
    *
    * @param sfView $view
    * @param mixed $file
    * @return 
	* @access protected
    **/
	public function renderFile($view, $file)
	{	
		$sf_context = sfContext::getInstance();
		$sf_request = $sf_context->getRequest(); 
		$sf_params = $sf_request->getParameterHolder();
		$sf_user = $sf_context->getUser();
		
		self::$smarty->compile_id = $sf_context->getModuleName();
		self::$usedHelpers = array();
		
		$view->setTemplate($file);		
		$this->loadCoreAndStandardHelpers();
		
		$_escaping = $view->getAttributeHolder()->getEscaping();
		if ($_escaping == 'on') {
			$sf_data = $this->getSfData($view, $view->getAttributeHolder()->getEscapingMethod());
			self::$smarty->assign_by_ref('sf_data', $sf_data);			
		} elseif ($_escaping === false || $_escaping == 'off') {
			$sf_data = $this->getSfData($view);
			self::$smarty->assign($view->getAttributeHolder()->getAll());
			self::$smarty->assign_by_ref('sf_data', $sf_data);
		}	
		
		// we need to add the context to smarty
		self::$smarty->assign_by_ref('sf_context', $sf_context);
		
		// we need to add the request to smarty
		self::$smarty->assign_by_ref('sf_request', $sf_request);
		
		// we need to add the params to smarty
		self::$smarty->assign_by_ref('sf_params', $sf_params);
		
		// we need to add the user to smarty
		self::$smarty->assign_by_ref('sf_user', $sf_user);
		
		/*
		$er = error_reporting();
		if ($er > E_STRICT) {
			error_reporting($er - E_STRICT);
		}*/
		
		$result = self::$smarty->fetch("file:$file");
		//error_reporting($er);
		return $result;       		
	}
	    
	/**
	 * sfSmarty::loadCoreAndStandardHelpers()
	 *
	 * @return
	 * @access protected
	 **/
	protected function loadCoreAndStandardHelpers()
	{
		$core_helpers = array('Helper', 'Url', 'Asset', 'Tag', 'Escaping', 'AppUrl');
		$standard_helpers = sfConfig::get('sf_standard_helpers');
		$helpers = array_unique(array_merge($core_helpers, $standard_helpers));
		foreach ($helpers as $helperName) {
			$this->loadHelper($helperName);
		}
	}

	/**
	 * sfSmarty::loadHelper()
	 *
	 * @param mixed $helperName
	 * @return
	 * @access protected
	 **/
	protected function loadHelper($helperName)
	{
		static $dirs;
		self::$usedHelpers[$helperName] = true;
		if (isset(self::$loadedHelpers[$helperName])) {
			return;
		}
		if (!self::$cache->has($helperName)) {
			if (!is_array($dirs)) {
				$dirs = sfLoader::getHelperDirs(/*$moduleName*/);
				$dirs = array_merge($dirs, explode(PATH_SEPARATOR, ini_get('include_path')));
				$dirs = array_merge($dirs, array(dirname(__FILE__) . '/helper'));
			}
		
			$fileName = $helperName . 'Helper.php';
			$path = '';
			foreach($dirs as $dir) {
				if (is_readable($dir . DIRECTORY_SEPARATOR . $fileName)) {
					$path = $dir . DIRECTORY_SEPARATOR . $fileName;
				    self::$cache->set($helperName, self::parseFile($path));
					break;
				}
			}
		}
        
		eval(self::$cache->get($helperName));
				
		try {
			sfLoader::loadHelpers(array($helperName, 'Smarty' . $helperName));
		}
		catch (sfViewException $e) {
			if (!strpos($e->getMessage(), 'Smarty' . $helperName)) {
				throw $e;
			}
		}
		self::$loadedHelpers[$helperName] = true;
	}
	

	/**
	 * sfSmarty::parseFile()
	 *
	 * @param mixed $path
	 * @return
	 **/
	protected static function parseFile($path)
	{
		if (self::$log) self::$log->info('{sfSmarty} parsing file: ' . $path . ' into the Smarty helper cache');	        
		//$code = '<?php ';
		$code = '';
		$lines = file($path);
		foreach($lines as $line) {
			$line = trim($line);
			if (strpos($line, 'function') === 0) {
				preg_match('/function\\s+(\\w+)\\s*\\((.*)\\)\\s*\\{?$/', $line, $matches);
				$name = $matches[1];
				if ($name{0} == '_' && $name !== '__') {
					continue;
				}
                 
				$code .= "\nself::\$knownFunctions['$name']=";
				if ($matches[2]) {
					$code .= var_export(self::parseArguments($matches[2]), true);
				} else {
					$code .= 'array()';
				}
				$code .= ";\nself::registerCompilerFunction('$name', array(\$this, '{$name}_CompilerFunction'));";
			}
		}
		return $code;
	}

	/**
	 * sfSmarty::parseArguments()
	 *
	 * @param mixed $argumentString
	 * @param boolean $smarty
	 * @return
	 **/
	protected static function parseArguments($argumentString, $smarty = false)
	{
		$argumentString .= $smarty ? ' ' : ',';
		$inDoubleQuotes = false;
		$inSingleQuotes = false;
		$args = array();
		$argumentName = '';
		$defaultValue = '';
		$parsingDefaultValue = false;
		$inArray = 0;
		for ($i = 0; $i < strlen($argumentString); $i++) {
			$letter = $argumentString{$i};
			if (!$smarty && !$inDoubleQuotes && !$inSingleQuotes && ($letter == ' ' || $letter == '	')) {
				continue;
			}
			if (!$parsingDefaultValue) {
				if (preg_match('/\\w/', $letter) || $letter == '$' || $letter == '>') {
					$argumentName .= $letter;
				} elseif ($letter == '=') {
					$parsingDefaultValue = true;
				} elseif ((!$smarty && $letter == ',') || ($letter == ' ' || $letter == '	')) {
					$args[$argumentName] = array();
					$argumentName = '';
				} elseif ($letter == '&') {
				} else {
					print_r($args);
					die("$inDoubleQuotes/$inSingleQuotes/$argumentName/$defaultValue/$parsingDefaultValue/'$letter'\n$argumentString\nI wonder...");
				}
			} else {
				switch ($letter) {
					case '(':
						if (!$inSingleQuotes && !$inDoubleQuotes) {
							$inArray++;
							if (self::$templateSecurity && strcasecmp(substr($defaultValue, -5), 'array')) {
								throw new Exception('sfSmartyView: You may not use PHP functions in a template! "' . $defaultValue . '"');
							}
						}
						$defaultValue .= $letter;
						break;
					case ')':
						if (!$inSingleQuotes && !$inDoubleQuotes) {
							$inArray--;
						}
						$defaultValue .= $letter;
						break;
					case ',':
						if ($inSingleQuotes || $inDoubleQuotes || $inArray) {
							$defaultValue .= $letter;
						} elseif (!$smarty) {
							$parsingDefaultValue = false;
							$args[$argumentName] = array('default' => $defaultValue);
							$argumentName = '';
							$defaultValue = '';
						}
						break;
					case '"':
						if (!$inSingleQuotes) {
							$inDoubleQuotes ^= true;
						}
						$defaultValue .= $letter;
						break;
					case "'":
						if (!$inDoubleQuotes) {
							$inSingleQuotes ^= true;
						}
						$defaultValue .= $letter;
						break;
					case ' ':
					case '	':
						if (!($inSingleQuotes || $inDoubleQuotes || $inArray)) {
							$parsingDefaultValue = false;
							$args[$argumentName] = preg_replace('/\\$(\\w+)/', '$this->_tpl_vars[\'$1\']', $defaultValue);
							$argumentName = '';
							$defaultValue = '';
						} else {
							$defaultValue .= $letter;
						}
						break;
					default:
						$defaultValue .= $letter;
				} // switch
			}
		}
		if (isset($args[''])) {
			unset($args['']);
		}
		return $args;
	}

	/**
	 * sfSmarty::smartyCompilerfunctionUse()
	 * this provides the use tag in smarty: {use helper="path"}
	 *
	 * @param mixed $content
	 * @param Smarty $smarty
	 * @return
	 **/
	public function smartyCompilerfunctionUse($content, Smarty $smarty)
	{
		if (!preg_match('/helper="([^"]+)"/', $content, $matches)) {
			throw new Exception('sfSmartyView: Cannot compile template. Use: {use helper="helpername"}');
		}
		$this->loadHelper($matches[1]);
		return '';
	}
	
	/**
	 * sfSmarty::smartyPostFilter()
	 *
	 * @param mixed $content
	 * @param Smarty $smarty
	 * @return
	 **/
	public static function smartyPostFilter($content, Smarty $smarty)
	{
		$helpers = '';
		foreach(self::$loadedHelpers as $helper => $dummy) {
			$helpers .= "use_helper('$helper');";
		}
		if ($helpers) {
			$helpers = "<?php $helpers ?>";
		}
		return $helpers . $content;
	}
	
	/**
	 * sfSmarty::__call()
	 * generic compiler function for all new tags
	 *
	 * @param mixed $functionName
	 * @param mixed $argsArray
	 * @return
	 **/
	public function __call($functionName, $argsArray)
	{
		if (!trim($argsArray[0])) {
			$args = array();
		} else {
			$args = $this->parseArguments($argsArray[0], true);
		}
		$functionName = str_replace('_CompilerFunction', '', $functionName);
		$argsOrder = $allArgs = (array)self::$knownFunctions[$functionName];
		$helperWithVarArgs = count($allArgs) == 0;
		$cacheArgs = array();
		foreach($args as $name => $value) {
			$name = '$' . trim($name);
			$value = trim($value);
			if (!isset($allArgs[$name]) && !$helperWithVarArgs) {
				throw new Exception('sfSmartyView: Cannot compile template. Unknown field found: "' . substr($name, 1) . '" near tag ' . $functionName);
			}
			$cacheArgs[$name] = $value;
			unset($allArgs[$name]);
		}
		foreach($allArgs as $name => $default) {
			if (!isset($default['default'])) {
				throw new Exception('sfSmartyView: Cannot compile template. Required field "' . substr($name, 1) . '" not found near tag ' . $functionName);
			}
			$cacheArgs[$name] = $default['default'];
		}
		$code = '';
		if (!$helperWithVarArgs) {
			foreach($argsOrder as $name => $value) {
				$code .= $code?',':'';
				if (is_bool($value)) {
					$code .= $cacheArgs[$name]?'true':'false';
				} else {
					$code .= $cacheArgs[$name];
				}
			}
		} else {
			foreach($cacheArgs as $name => $value) {
				$code .= $code?',':'';
				if (is_bool($value)) {
					$code .= $value?'true':'false';
				} else {
					$code .= $value;
				}
			}
		}
		$code = "echo $functionName($code);\n";
		return $code;
	}

	/**
	 * sfSmarty::registerBlock()
	 * this is an access function to the internal smarty instance
	 * to register a block function
	 *
	 * @param mixed $tag
	 * @param mixed $function
	 * @return
	 **/
	public static function registerBlock($tag, $function)
	{
		self::$smarty->register_block($tag, $function);
	}

	/**
	 * sfSmarty::registerFunction()
	 * this is an access function to the internal smarty instance
	 * to register a function
	 *
	 * @param mixed $tag
	 * @param mixed $function
	 * @return
	 **/
	public static function registerFunction($tag, $function)
	{
		self::$smarty->register_function($tag, $function);
	}

    /**
     * sfSmarty::registerCompilerFunction()
     * this is an access function to the internal smarty instance
     * to register a compiler function
     *
     * @param mixed $tag
     * @param mixed $function
     * @return
     **/
    public static function registerCompilerFunction($tag, $function)
    {
        self::$smarty->register_compiler_function($tag, $function);
    }
          
	/**
	 * sfSmarty::registerModifier()
	 * this is an access function to the internal smarty instance
	 * to register a modifier
	 *
	 * @param mixed $tag
	 * @param mixed $function
	 * @return
	 **/
	public static function registerModifier($tag, $function)
	{
		self::$smarty->register_modifier($tag, $function);
	}    
}
