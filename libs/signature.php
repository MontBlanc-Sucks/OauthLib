<?php 

if (!class_exists('Hmac')) {
	App::import('Lib', 'OauthLib.Hmac');
}
if (!class_exists('RequestFactory')) {
	App::import('Lib', 'OauthLib.RequestFactory');
}
if (!class_exists('OauthHelper')) {
	App::import('Lib', 'OauthLib.OauthHelper');
}

class Signature {
/**
 *  List of registered signature methods
 *
 * @var array $availableMethods
 * @access public
 */
	public $availableMethods = array();

/**
 * Factory register signature method
 *
 * @param string $signatureMethod
 * @param string $class
 * @access public
 */
	public function register($signatureMethod, $class) {
		$_this = Signature::getInstance();
		$_this->availableMethods[$signatureMethod] = $class;
	}

/**
 * Constructor
 *
 * @access public
 */
	public function __construct() {
	}
/**
 * Singleton constructor
 *
 * @return Signature instance
 * @access public
 */
	public function &getInstance() {
		static $instance = array();
		if (!isset($instance[0]) || !$instance[0]) {
			$instance[0] = new Signature();
		}
		return $instance[0];
	}

/**
 * Factory build method for signature 
 *
 * @param Request $request
 * @param array $options
 * @access public
 */
	public function build($request, $options = array()) {
		$_this = Signature::getInstance();
		$request = RequestFactory::proxy($request, $options);
		$signatureMethod = $request->signatureMethod();
		if (isset($_this->availableMethods[$signatureMethod])) {
			$class = $_this->availableMethods[$signatureMethod];
		} else {
			throw new Exception("UnknownSignatureMethod $signatureMethod");
		}
		return new $class($request, $options);
	}

/**
 * Sign request
 *
 * @param Request $request
 * @param array $options
 * @return string request signature
 * @access public
 */
	public function sign($request, $options = array()) {
		$class = Signature::build($request, $options);
		if (is_object($class)) {
			return $class->signature();
		} else {
			return null;
		}
	}

/**
 * Verify the signature of request
 *
 * @param Request $request
 * @param array $options
 * @return boolean
 * @access public
 */
	public function verify($request, $options = array()) {
		$class = Signature::build($request, $options);
		if (is_object($class)) {
			return $class->verify();
		} else {
			return null;
		}
	}
/**
 * Generate base string for signature
 *
 * @param Request $request
 * @param array $options
 * @return string
 * @access public
 */
	public function signatureBaseString($request, $options = array()) {
		$class = Signature::build($request, $options);
		if (is_object($class)) {
			return $class->signatureBaseString();
		} else {
			return null;
		}
	}
}

/**
 * Base signature class
 */
class SignatureBase {

/**
 * Secret token key
 *
 * @var string $tokenSecret
 * @access public
 */
	public $tokenSecret;

/**
 * Options setting storage
 *
 * @var array $options
 * @access public
 */
	public $options;

/**
 * Secret consumer key
 *
 * @var string $consumerSecret
 * @access public
 */
	public $consumerSecret;

/**
 * Request Object
 *
 * @var Object $request
 * @access public
 */
	public $request;

/**
 * digest class
 *
 * @var string $digestClass
 * @access public
 */
	public $digestClass;

/**
 * Constructor
 *
 * @param Request $request
 * @param array $options
 */
	public function __construct(&$request, $options = array()) {
		$this->request = $request;
		$this->options = $options;
		if (isset($options['consumer_secret'])) {
			$this->consumerSecret = $this->options['consumer_secret'];
		} elseif (isset($options['consumer'])) {
			$this->consumerSecret = $this->options['consumer']->secret;
		}
		if (isset($options['token_secret'])) {
			$this->tokenSecret = $this->options['token_secret'];
		} elseif (isset($options['token'])) {
			$this->tokenSecret = $this->options['token']->tokenSecret;
		} else {
			$this->tokenSecret = '';
		}
	}

/**
 * Return signature for request
 *
 * @return string
 * @access public
 */
	public function signature() {
		OauthHelper::log(array('local' => base64_encode($this->__digest()), 'ext' => '', 'localString' => $this->signatureBaseString(), 'localSecret' => $this->_secret()));
		//debug(array('local' => base64_encode($this->__digest()), 'ext' => '', 'localString' => $this->signatureBaseString(), 'localSecret' => $this->_secret()));
		return base64_encode($this->__digest());
	}

/**
 * Compare with other signature
 *
 * @param string $cmpSignature
 * @return boolean
 * @access public
 */
	public function eq($cmpSignature) {
		return base64_decode($this->signature()) == base64_decode($cmpSignature);
	}

/**
 * Verify validness of signature
 *
 * @return boolean
 * @access public
 */
	public function verify() {
		OauthHelper::log(array('req_sig' => $this->request->signature(), 'local_sig' => $this->signature(), 'request' => get_class($this->request)));
		return $this->eq($this->request->signature());
	}

/**
 * Generate base string for signature
 *
 * @return string
 * @access public
 */
	public function signatureBaseString() {
		$normalizedParams = $this->request->parametersForSignature();
		ksort($normalizedParams);
		$normalizedParamsJoined = array();
		foreach($normalizedParams as $key => $value) {
			$normalizedParamsJoined[] = OauthHelper::escape($key) . '=' . OauthHelper::escape($value);
		}
		$normalizedParams = implode('&', $normalizedParamsJoined);
		$base = array($this->request->method(), $this->request->uri(), $normalizedParams);
		$result = implode("&", array_map(array(&$this, 'escape'), $base));
		OauthHelper::log(array('signatureBaseString' => $result));
		return $result;
	}

/**
 * Secret key for request
 *
 * @return string
 * @access public
 */
	protected function _secret() {
		OauthHelper::log($this->escape($this->consumerSecret) . '&' . $this->escape($this->tokenSecret));
		return ($this->consumerSecret) . '&' . $this->escape($this->tokenSecret);
	}

/**
 * Calculate diggest for request signature
 *
 * @return string
 * @access public
 */
	private function __digest() {
		$this->digestClass->setKey($this->_secret());
		$tt = $this->signatureBaseString();
		return $this->digestClass->hash($this->signatureBaseString(), Hmac::BINARY);
	}
/**
 * Escape request value
 *
 * @param string $value
 * @return string
 * @access public
 */
	public function escape($value) {
		if ($value === false) {
			return $value;
		} else {
			return str_replace('%7E', '~', rawurlencode($value));
		}
	}
}


Signature::register('PLAINTEXT', 'SignaturePlaintext');

class SignaturePlaintext extends SignatureBase {

/**
 * Return signature for request
 *
 * @return string
 * @access public
 */
	public function signature() {
		return $this->signatureBaseString();
	}

/**
 * Compare with other signature
 *
 * @param string $cmpSignature
 * @return boolean
 * @access public
 */
	public function eq($cmpSignature) {
		return $this->signature() == $this->escape($cmpSignature);
	}

/**
 * Generate base string for signature
 *
 * @return string
 * @access public
 */
	public function signatureBaseString() {
		return $this->escape($this->_secret());
		return $this->_secret();
	}

/**
 * Secret key for request
 *
 * @return string
 * @access public
 */
	// protected function _secret() {
		// OauthHelper::log($this->escape($this->consumerSecret) . '&' . $this->escape($this->tokenSecret));
		//return $this->escape(parent::_secret());
		// return parent::_secret();
	// }
	
}

Signature::register('HMAC-MD5', 'SignatureMD5');

class SignatureMD5 extends SignatureBase {

/**
 * Constructor
 *
 * @param Request $request
 * @param array $options
 */
	public function __construct(&$request, $options = array()) {
		parent::__construct($request, $options);
		$this->digestClass = &new Hmac(null, 'md5');
	}

}

Signature::register('HMAC-SHA1', 'SignatureSHA1');

class SignatureSHA1 extends SignatureBase {
/**
 * Constructor
 *
 * @param Request $request
 * @param array $options
 */
	public function __construct(&$request, $options = array()) {
		parent::__construct($request, $options);
		$this->digestClass = &new Hmac(null, 'sha1');
	}
}


Signature::register('RSA-SHA1', 'SignatureRsaSha1');

class SignatureRsaSha1 extends SignatureBase {

/**
 * Public certificate
 *
 * @var string $publicCert
 * @access public
 */
	public $publicCert = null;

/**
 * Private certificate
 *
 * @var string $privateCert
 * @access public
 */
	public $privateCert = null;

/**
 * Private certificate
 *
 * @var string $privateCert
 * @access public
 */
	public $privateCertPass = null;

/**
 * Constructor
 *
 * @param Request $request
 * @param array $options
 */
	public function __construct(&$request, $options = array()) {
		if (!isset($options['publicCert'])) {
			throw new Exception('Public certificate not present');
		} else {
			$this->publicCert = $options['publicCert'];
		}
		if (!isset($options['privateCert'])) {
			throw new Exception('Private certificate not present');
		} else {
			$this->privateCert = $options['privateCert'];
		}
		if (!isset($options['privateCertPass'])) {
			throw new Exception('Private key password not present');
		} else {
			$this->privateCertPass = $options['privateCertPass'];
		}
		return parent::__construct($request, $options);
	}

/**
 * Private certificate getter
 *
 * @return string
 * @access protected
 */
	protected function _fetchPublicCertificate() {
		return $this->publicCert;
	}

/**
 * Private certificate
 *
 * @var string $privateCert
 * @access protected
 */
	protected function _fetchPrivateCertificate() {
		if (empty($this->privateCertPass)) {
			return $this->privateCert;
		} else {
			return array($this->privateCert, $this->privateCertPass);
		}
	}

/**
 * Return signature for request
 *
 * @return string
 * @access public
 */
	public function signature() {
		$privateKeyId = openssl_get_privatekey($this->_fetchPrivateCertificate());
		$ok = openssl_sign($this->signatureBaseString(), $signature, $privateKeyId);   
		openssl_free_key($privateKeyId);
		return base64_encode($signature);
	}

/**
 * Verify validness of signature
 *
 * @param string $cmpSignature
 * @return boolean
 * @access public
 */
	public function verify($cmpSignature = null) {
		if ($cmpSignature == null) {
			$cmpSignature = $this->request->signature();
			if (is_array($cmpSignature)) {
				$cmpSignature = array_shift($cmpSignature);
			}
		}
		$decodedSignature = base64_decode($cmpSignature);
		$publicKeyId = openssl_get_publickey($this->_fetchPublicCertificate());
		$ok = openssl_verify($this->signatureBaseString(), $decodedSignature, $publicKeyId);
		openssl_free_key($publicKeyId);
		return $ok == 1;
	} 
}
?>