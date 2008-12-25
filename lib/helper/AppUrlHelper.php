<?php
use_helper('Url');
/**
* link_to with additionnal param : $app
*
* @param string name of the link, i.e. string to appear between the <a> tags
* @param string 'module/action' or '@rule' of the action
* @param array additional HTML compliant <a> tag parameters
* @param string name of the application
* @return string XHTML compliant <a href> tag
*/
function link_to_app($app, $name = '', $internal_uri = '', $options = array(), $subdomain = true, $path = '/') {
	$current_app = sfConfig::get('sf_app');
	$environment = (sfConfig::get('sf_environment') == 'prod') ? "" : "_".sfConfig::get('sf_environment');
	$host = $_SERVER['SERVER_NAME'];
	$protocol = (sfContext::getInstance()->getRequest()->isSecure()) ? "https" : "http";
	$url = $protocol."://";
	$file = sfConfig::get('sf_web_dir').$path.$app.$environment.'.php';
	
	if (file_exists($file)) {
		if (!empty($environment)) {
			$host = __strip_subdomain($current_app);
			if ($subdomain) {
				$file_woe = sfConfig::get('sf_web_dir').$path.$app.'.php';
				if (file_exists($file_woe)) {
					$url .= $app.'.';
				}
			}				
		} else if ($subdomain) {
			$url .= $app.'.';
		}
		$url .= $host.$path.$app.$environment.'.php';
		if (!empty($internal_uri)) $url .= '/';
	} else {
		if (!empty($environment)) {
			throw new Exception ('Application cannot be linked to: Does not exist.');
		}
		$host = __strip_subdomain($current_app);
		$url .= $host.$path;			
	}
	
	$url .= $internal_uri;
	return link_to($name, $url, $options);
}

function __strip_subdomain($app)
{
	if (strpos($_SERVER['SERVER_NAME'],$app) === 0) {
		return str_replace($app.'.', '', $_SERVER['SERVER_NAME']);
	}
	return $_SERVER['SERVER_NAME'];
}
?>