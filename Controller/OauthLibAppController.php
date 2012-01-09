<?php

/**
 * Short description for file.
 *
 * Long description for file
 *
 * PHP versions 4 and 5
 *
 * Copyright 2007-2008, Cake Development Corporation
 * 							1785 E. Sahara Avenue, Suite 490-423
 * 							Las Vegas, Nevada 89104
 *
 * You may obtain a copy of the License at:
 * License page: http://projects.cakedc.com/licenses/TBD  TBD
 *
 * @filesource
 * @copyright		Copyright 2007-2008, Cake Development Corporation
 * @package			oauth_lib
 * @subpackage		oauth_lib
 * @license			http://projects.cakedc.com/licenses/TBD  TBD
 */
/**
 * Short description for class.
 *
 * @package			oauth_lib
 * @subpackage		oauth_lib
 */

if (!class_exists('Signature')) {
	App::import('Lib', 'OauthLib.Signature');
}if (!class_exists('RequestProxyController')) {
	App::import('Lib', 'OauthLib.RequestProxyController');
}
if (!class_exists('OauthHelper')) {
	App::import('Lib', 'OauthLib.OauthHelper');
}
if (!class_exists('RequestFactory')) {
	App::import('Lib', 'OauthLib.RequestFactory');
}
if (!class_exists('ClientHttp')) {
	App::import('Lib', 'OauthLib.ClientHttp');
}

class OauthLibAppController extends AppController {
/**
 * Name
 *
 * @var string $name
 * @access public
 */
	public $name = 'OAuthRequests';

/**
 * Flag that identify oauth signed request actions
 *
 * @var string $useOauth
 * @access public
 */
	public $useOauth = false;

/**
 * Parameters show which action need to check with verifyOauthSignature
 *
 * @var array $requireOAuth
 * @access public
 */
	public $requireOAuth = array(
		'actions' => array(),
		'enabled' => false);

/**
 * tokenData, is ServerToken for the request after verifyOauthSignature
 *
 * @var string $tokenData
 * @access public
 */
	public $tokenData = null;

/**
 * Before filter callback
 * Load Server models and verify oauth request
 *
 * @return boolean
 * @access public
 */
	public function beforeFilter() {
		if ($this->requireOAuth['enabled']) {
			$this->_loadModels();
			$actions = $this->requireOAuth['actions'];
			if (is_array($actions) && (in_array($this->action, $actions) || in_array('*', $actions)) || $actions == '*') {
				$this->verifyOauthRequest();
			}
			$this->configureOAuth();
			$this->_afterOauthChecked();
		} else {
			parent::beforeFilter();
		}
	}

/**
 * load oauth server models callback
 *
 * @return void
 * @access protected
 */
	public function _loadOauthModels() {
	}
	
	public function _loadModels() {
	}

/**
 * after Oauth Checked callback
 *
 * @return void
 * @access protected
 */
	public function _afterOauthChecked() {
	}
	
/**
 * Do verify for oauth request
 *
 * @return boolean
 * @access public
 */
	public function verifyOauthRequest() {
		return $this->verifyOauthSignature();
		//return ($this->verifyOauthSignature() && isset($this->{$providerInstance}->data['id']));
	}

/**
 * Check oauth request signature
 *
 * @return boolean
 * @access public
 */
	public function verifyOauthSignature() {
		$proxy = & new RequestProxyController($this);
		$params = $proxy->parameters();
		$token = '';
		if (isset($params['oauth_token'])) {
			$token = $params['oauth_token'];
		}
		$serverRegistry = & new ServerRegistry;
		$this->tokenData = $serverRegistry->AccessServerToken->find(array('AccessServerToken.token' => $token, 'AccessServerToken.authorized' => 1));
		try {
			$valid = Signature::verify($this, array('consumer_secret' => $this->tokenData['ServerRegistry']['consumer_secret'], 'token_secret' => $this->tokenData['AccessServerToken']['token_secret']));
		} catch(Exception $e) {
			$valid = false;
		}
		if (!$valid) {
			Configure::write('debug', 0);
			header("HTTP/1.1 401 Unauthorized");
			echo "Invalid OAuth Request";
			exit;
		}
		return $valid;
	}

/**
 * Before render callback
 *
 * @return boolean
 * @access public
 */
	public function beforeRender() {
		//$this->applyOAuth();
		return parent::beforeRender();
	}

/**
 * Configure oauth common settings
 *
 * @return boolean
 * @access public
 */
	public function configureOAuth($consumer = null, $token = null, $options = array()) {
		$this->default = array( 'consumer' => $consumer,
			   'token' => $token,
			   'scheme' => 'header',
			   'signature_method' => null,
			   'nonce' => null,
			   'timestamp' => null);
		$this->options = array_merge($this->default, $options);
    }

/**
 * Signing oauth request
 *
 * @return boolean
 * @access public
 */
	public function applyOAuth() {
		$options = array_merge($this->default, $this->options);
		if ($this->useOauth) {
			return;
		}
		$this->oauthHelper = new ClientHelper($this, array_merge($this->options, array('request_uri' => $this->params['url']['url'])));
		$header = array();
		$this->oauthHelper->amendUserAgentHeader($header);
		if (!empty($header['User-Agent'])) {
			header('User-Agent:' . $header['User-Agent']);
		}

		$method = "__setOAuth" . Inflector::camelize($options['scheme']);
		return $this->{$method}();
    }

/**
 * Header signing auth method implementation
 *
 * @access private
 */
	private function __setOAuthHeader() {
		header('Authorization:' . $this->oauthHelper->header());
	}

/**
 * Configure oauth parameters
 *
 * @return boolean
 * @access public
 */
	function setOAuthParameters() {
		$this->queryParameters = $this->oauthHelper->parametersWithOauth();
		$this->queryParameters = array_merge($this->queryParameters, array('oauth_signature' => $this->oauthHelper->signature()));
		return $this->queryParameters;
    }

/**
 * Not implemented!
 * Possible need to implemented in the special view class
 *
 * @return boolean
 * @access private
 */
	private function __setOAuthBody() {
	}

/**
 * Not possible to rewrite url request in response
 * Will not implemented
 *
 * @return boolean
 * @access private
 */
	private function __setOAuthQueryString() {
	}

/**
 * Oauth request parameters
 *
 * @return boolean
 * @access public
 */
	public function oauthParameters() {
		$proxy = RequestFactory::proxy($this);
		return $proxy->parameters();
	}

/**
 * Build url for redirection
 *
 * @return string
 * @access protected
 */
	protected function _gatherUrl() {
		$params = $this->params['url'];
		$url = $params['url'];
		unset($params['url']);
		if (count($params) > 0) {
			$url .= '?' . OauthHelper::mapper($params, '&', '');
		}
		if (strlen($url) > 0 && strpos($url, 0, 1) != '/') {
			$url = '/' . $url;
		}
		if (strlen($url) == 0) {
			$url = '/';
		}
		return $url;
	}

}
?>