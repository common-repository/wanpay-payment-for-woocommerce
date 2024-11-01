<?php

class WCWP_Gateway_Wanpay extends WC_Payment_Gateway
{
    private $fileCharset = "UTF-8";
    private $wanpayAop;
    private string $appid;
    private string $appsecret;
    private string $gatewayUrl;
    private string $currency;


    /**
     * WCWP_Gateway_Wanpay constructor.
     */
    public function __construct()
    {
        $this->id = "wanpay-plugin-for-wp";
        $this->icon = WCWP_WANPAY_URL . "/assets/imgs/logo.png";
        $this->has_fields = false;
        $this->method_title = "wanpay";
        $this->method_description = "wanpay";
        $this->init_form_fields();
        $this->init_settings();
        $this->init_option();
        $this->init_aop();
        //添加hook钩子，设置回调函数
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wanpay-notify', [$this, 'notify_wanpay']);
        add_action('woocommerce_api_wanpay-pay-result', [$this, 'pay_result']);
        add_action( 'before_woocommerce_pay_form', [$this, 'order_pay'], 10, 2 );
        add_action( 'woocommerce_before_thankyou', [$this, 'thankyou_page']);
    }

    private function init_option()
    {
        $this->enabled = $this->get_option("enabled");
        $this->title = $this->get_option("title");
        $this->description = $this->get_option("description");
        $this->appid = $this->get_option("appid");
        $this->appsecret = $this->get_option("appsecret");
        $this->gatewayUrl = $this->get_option("gatewayUrl");
        $this->currency = $this->get_option("currency",'TWD');
    }

    private function init_aop()
    {
        require_once("WcwpAopClientWanPay.php");
        $this->wanpayAop = new WcwpAopClientWanPay();
        $this->wanpayAop->gatewayUrl = $this->gatewayUrl;
        $this->wanpayAop->appId = $this->appid;
        $this->wanpayAop->rsaPrivateKey = $this->appsecret;
        $this->wanpayAop->format = "json";
    }

    /**
     * 配置列表项
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable payment', 'woocommerce'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('Title that users will see during settlement', 'wanpay-payment-for-woocommerce'),
                'default' => __('wanpay', 'woocommerce'),
                'desc_tip' => true,
                'css' => 'width:400px'
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'textarea',
                'default' => '',
                'css' => 'width:400px'
            ),
            'appid' => array(
                'title' => __('MERCHANT ID', 'wanpay-payment-for-woocommerce'),
                'type' => 'text',
                'default' => '',
                'description' => __('MERCHANT ID', 'wanpay-payment-for-woocommerce'),
                'css' => 'width:400px'
            ),
            'appsecret' => array(
                'title' => __('App Secret', 'wanpay-payment-for-woocommerce'),
                'type' => 'text',
                'default' => '',
                'description' => __('App Secret', 'wanpay-payment-for-woocommerce'),
                'css' => 'width:400px'
            ),
            'currency' => array(
                'title' => __('Currency', 'wanpay-payment-for-woocommerce'),
                'type' => 'text',
                'default' => 'TWD',
                'description' => __('Currency', 'wanpay-payment-for-woocommerce'),
                'css' => 'width:200px;',
                'custom_attributes' => array(
                    'readonly' => 'readonly', // Set the input field as readonly
                ),
            ),
            'gatewayUrl' => array(
                'title' => __('Default gateway', 'wanpay-payment-for-woocommerce'),
                'type' => 'text',
                'label' => __('Enabling the environment', 'wanpay-payment-for-woocommerce'),
                'default' => '',
                'description' => __('Modify the gateway address according to the document', 'wanpay-payment-for-woocommerce'),
                'css' => 'width:400px'
            )
        );
    }
    public function notify_wanpay()
    {
        //This step only requires recording the parameters of the request in the log. Please ignore and use $_POST directly
        $this->log($_POST, 'notify');
        $data = array_map( 'sanitize_text_field', $_POST );
        //This side must undergo signature verification
        if(!$this->check_response($data)){
            echo esc_html('error');exit;
        }
        $data = [
            'status' => sanitize_text_field($_POST['status']),
            'out_trade_no' => sanitize_text_field($_POST['out_trade_no']),
        ];
        if ($data['status'] == '0000') {
            $order_id = $this->out_trade_no_to_order_id($data['out_trade_no']);
            $order = new WC_Order($order_id);
            if ($order->needs_payment()) {
                $order->payment_complete();
            }
            echo esc_html("success");
        } else {
            echo esc_html("fail");
        }

        die;
    }

    public function thankyou_page($order_id){
        $notes = wc_get_order_notes(
            [
                'order_id' => $order_id,
            ]
        );

        echo esc_html($notes[0]->content ?? '');
    }
    
    public function pay_result(){
        // 对每个字段进行验证和清理
        $data = array_map( 'sanitize_text_field', $_GET );
        if(!$this->check_response($data)){
            echo esc_html('error');exit;
        }
        //This step only requires recording the parameters of the request in the log. Please ignore and use $_GET directly
        $this->log($_GET, 'pay_result');
        $data = [
            'status' => sanitize_text_field($_GET['status']),
            'out_trade_no' => sanitize_text_field($_GET['out_trade_no']),
            'result' => sanitize_text_field($_GET['result']),
        ];
        $order_id = $this->out_trade_no_to_order_id(sanitize_text_field($data['out_trade_no']));
        $order = new WC_Order($order_id);
        $return_url = $this->get_return_url($order);
        if(sanitize_text_field($data['status']) == '0000'){
            $order->update_status('pending', __( 'Awaiting cheque payment', 'woocommerce' ));
        }else{
            $order->update_status('failed', $data['result']);
        }
        wp_safe_redirect( $return_url );
        exit;
    }

    private function out_trade_no_to_order_id($out_trade_no)
    {
        $out_trade_no = explode("E", $out_trade_no)[1];
        if (is_numeric($out_trade_no)) {
            if (!empty($this->order_prefix)) {
                $order_id = (int)str_replace($this->order_prefix, '', $out_trade_no);
            } else {
                $order_id = (int)$out_trade_no;
            }
        } else {
            $order_id = (int)str_replace('T', '', $out_trade_no);
        }
        return $order_id;
    }

    public function log($message, $name = "")
    {
        // 1. 初始化 WordPress 文件系统
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ( ! WP_Filesystem() ) {
            // 文件系统初始化失败，可能是权限不足等问题，处理错误情况
            return false;
        }
// 2. 使用文件系统对象进行文件操作
        global $wp_filesystem;
        $upload_dir = wp_upload_dir();
        $log_file_path = $upload_dir['basedir'] . '/wanpay_' . $name . '.log';
        // 将日志信息写入文件
        $log_content = gmdate('Y-m-d H:i:s', time()) . ' ' . wp_json_encode($message) . PHP_EOL;
        // 读取文件内容
        $content = "";
        if($wp_filesystem->exists($log_file_path)){
            $content = $wp_filesystem->get_contents($log_file_path);
        }
//        file_put_contents($log_file_path, date('Y-m-d H:i:s', time()) . ' ' . json_encode($message) . PHP_EOL, FILE_APPEND);
        $wp_filesystem->put_contents( $log_file_path, $content.$log_content, FS_CHMOD_FILE );
//        $wp_filesystem->chmod( $log_file_path, 0644 );
    }
    
    public function order_pay($order)
    {
        $paymentResult =  $this->process_payment($order->id);
        wp_redirect( $paymentResult['redirect'] );
    }


    /**
     * 支付方法
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        global $woocommerce;

        $order = new WC_Order($order_id);
        $notify_url = WC()->api_request_url('wanpay-notify');
        $return_url = WC()->api_request_url('wanpay-pay-result');
        $this->log($notify_url,"order");

        $total_amount = $order->get_total();
        $order_no = $this->get_order_number($order_id);
        //请求参数
        $post_data = [];
        $post_data["orgno"] = $this->wanpayAop->appId;//商户
        $post_data["out_trade_no"] = $order_no;
        $post_data["total_fee"] = $total_amount * 100;
        $post_data["returnurl"] = $return_url;
        $post_data["backurl"] = $notify_url;
        $post_data["currency"] = $this->currency;
        $post_data['nonce_str'] = wp_rand(10000000, 99999999);
        $post_data['secondtimestamp'] = time();
        $stringSignTemp = $this->sign($post_data,  $this->wanpayAop->rsaPrivateKey);
        $post_data['sign'] = $stringSignTemp;
        try {
            $this->log($this->wanpayAop->gatewayUrl. "/wxzfservice/wallet", "order");
            $this->log($post_data, "order");
            $resp = $this->curl( $this->wanpayAop->gatewayUrl. "/wxzfservice/wallet", $post_data);
            $this->log($resp, "order");
            $response = json_decode($resp, true);
            if ($response['status'] == "900") {
                $msg = 'Customers are paying by code wanpay';
                $order->add_order_note($msg);

                $woocommerce->cart->empty_cart();
                update_post_meta($order_id, '_gateway_payment_url', $response['data']['html']);
                // 返重定向到支付表单
                return array(
                    'result' => 'success',
                    'redirect' => $response['data']['html']
                );
            } else {
                wc_add_notice($response['info'], 'error');
                return array(
                    'result' => 'fail',
                    'redirect' => $this->get_return_url($order)
                );
            }
        } catch (Exception $e) {
            wc_add_notice("errcode:{$e->getCode()},errmsg:{$e->getMessage()}", 'error');
            return array(
                'result' => 'fail',
                'redirect' => $this->get_return_url($order)
            );
        }
    }

    /* 拼接 sign */
    public function sign($post_data, $signStr)
    {
        $post_datastr = '';
        //使用URL键值对的格式（即key1=value1&key2=value2…）拼接成字符串stringA
        foreach ($post_data as $name => $value) {
            if (!is_array($value)) {
                $post_datastr = $post_datastr . $name . '=' . $value . '&';
            }
        }
        $tmpArr = explode('&', trim($post_datastr, '&'));
        //参数名ASCII码从小到大排序（字典序）
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode('&', $tmpArr);
        //拼接API密钥
        $tmpStr = $tmpStr . '&key=' . $signStr;
        return strtoupper(md5($tmpStr));
    }


    /**
     * 验签
     * @param $arr
     * @return mixed
     */
    public function check_response($arr)
    {
        unset($arr['wc-api']);
        unset($arr['woocommerce-reset-password-nonce']);
        $originalSign = $arr['sign'];
        unset($arr['sign']);
        $stringSignTemp = $this->sign($arr, $this->appsecret);
        return ($originalSign == $stringSignTemp) ? true : false;
    }

    protected function curl($url, $postFields = null)
    {
        $result = wp_remote_post( $url, array(
            'body' => $postFields ) );
        return $result['body'];
    }

    public function get_order_number($order_id)
    {
        return "Y" . gmdate("YmdHis") . 'E' .ltrim($order_id, '#');
    }
}