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
function link_to_app($name = '', $internal_uri = '', $options = array(), $app = SF_APP) {
	$environment = (sfConfig::get('sf_environment') == 'prod') ? "" : "_".sfConfig::get('sf_environment');
	$protocol = (sfContext::getInstance()->getRequest()->isSecure()) ? "https" : "http";
	$url = $protocol."://".$_SERVER["SERVER_NAME"]."/".strtolower($app).$environment. ".php/".$internal_uri;
	return link_to($name, $url, $options);
}
?>