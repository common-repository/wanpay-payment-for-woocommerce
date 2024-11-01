<?php
class WcwpAopClientWanPay {

	public $appId;

	public $rsaPrivateKeyFilePath;

	public $rsaPrivateKey;

	public $gatewayUrl = "";

	public $format = "json";

	public $apiVersion = "1.0";


	public $postCharset = "UTF-8";


	public $alipayPublicKey = null;


	public $alipayrsaPublicKey;


	public $debugInfo = false;

	private $fileCharset = "UTF-8";

	private $RESPONSE_SUFFIX = "_response";

	private $ERROR_RESPONSE = "error_response";

	private $SIGN_NODE_NAME = "sign";


	private $ENCRYPT_XML_NODE_NAME = "response_encrypted";

	private $needEncrypt = false;


	public $signType = "RSA";


	public $encryptKey;

	public $encryptType = "AES";

	protected $alipaySdkVersion = "alipay-sdk-php-20161101";


	protected function curl($url, $postFields = null) {
        $result = wp_remote_post( $url, array(
            'body' => $postFields ) );
        return $result['body'];
	}

	protected function getMillisecond() {
		list($s1, $s2) = explode(' ', microtime());
		return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
	}


	/**
	 * 转换字符集编码
	 * @param $data
	 * @param $targetCharset
	 * @return string
	 */
	function characet($data, $targetCharset) {
		
		if (!empty($data)) {
			$fileType = $this->fileCharset;
			if (strcasecmp($fileType, $targetCharset) != 0) {
				$data = mb_convert_encoding($data, $targetCharset, $fileType);
				//				$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
			}
		}


		return $data;
	}


	/**
	 * 校验$value是否非空
	 *  if not set ,return true;
	 *    if is null , return true;
	 **/
	protected function checkEmpty($value) {
		if (!isset($value))
			return true;
		if ($value === null)
			return true;
		if (trim($value) === "")
			return true;

		return false;
	}

}