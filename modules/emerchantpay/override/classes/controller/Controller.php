<?php

abstract class Controller extends ControllerCore
{
	/**
	 * Use Google's CDN to host jQuery
	 * @param string  $version  Version of jQuery to include
	 * @param string  $folder   Not used in this override
	 * @param boolean $minifier Use minified version?
	 */
	/*
	public function addJquery($version = '1.11.2', $folder = null, $minifier = true, $migrate = '1.2.1') {
		$this->addJS(Media::getJSPath(Tools::getCurrentUrlProtocolPrefix() . 'ajax.googleapis.com/ajax/libs/jquery/' . ($version ? $version : _PS_JQUERY_VERSION_) . '/jquery'.($minifier ? '.min.js' : '.js')));
		$this->addJS(Media::getJSPath(Tools::getCurrentUrlProtocolPrefix() . 'code.jquery.com/jquery-migrate-' . ($migrate ? $migrate : _PS_JQUERY_VERSION_) . ($minifier ? '.min.js' : '.js')));
	}
	*/
}