<?php

/**
 * WC_BrazilPays_Gateway_Pix
 *
 * Providencia um Gateway de pagamento próprio via PIX do BrazilPays
 *
 * @class       WC_BrazilPays_Gateway_Pix
 * @extends     WC_Payment_Gateway
 * @version     2.1.0
 * @package     WooCommerce\Classes\Payment
 */
class WC_BrazilPays_Gateway_Pix extends WC_Payment_Gateway
{

	/**
	 * Gateway instructions that will be added to the thank you page and emails.
	 *
	 * @var string
	 */
	public $instructions;

	public $status_when_waiting;


	/**
	 * Enable for shipping methods.
	 *
	 * @var array
	 */
	public $enable_for_methods;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct()
	{
		// Setup general properties.
		$this->setup_properties();

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Get settings.
		$this->title              = $this->get_option('title');
        $this->merchant_code      = $this->get_option('merchant_code');
        $this->public_key         = $this->get_option('public_key');
		$this->description        = $this->get_option('description');
		$this->instructions       = $this->get_option('instructions');
		$this->enable_for_methods = $this->get_option('enable_for_methods', array());

		// Actions.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

		add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
		

		//função verifica se pagamentos foram efetuados
		add_action('rest_api_init', array($this, 'brazilpays_check_payment_status'), 10);

		// Customer Emails.
		add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
	}


	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties()
	{
		$this->id                 = 'brazilpays-pix';
        $this->merchant_code	  = __('Adicionar Merchant Code', 'brazilpays-plugin ');
        $this->public_key         = __('Adicionar Public Key', 'brazilpays-plugin ');
		$this->icon               = apply_filters('brazilpays-plugin', plugins_url('../assets/icon-pix.png', __FILE__));
		$this->method_title       = __('Pix', 'brazilpays-plugin ');
		$this->method_description = __('Receba pagamentos em Pix utilizando sua conta BrazilPays', 'brazilpays-plugin ');
		$this->has_fields         = false;
		$this->instructions 	  = __('Escaneie o código QR para realizar o pagamento!', 'brazilpays-plugin ');
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __('Ativar/Desativar', 'brazilpays-plugin '),
				'label'       => __('Ativar Pagamento em Pix - BrazilPays', 'brazilpays-plugin '),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
            'merchant_code'              => array(
				'title'       => __('Merchant Code', 'brazilpays-plugin '),
				'type'        => 'text',
			),
            'public_key'              => array(
				'title'       => __('Public Key', 'brazilpays-plugin '),
				'type'        => 'text',
			),
			'title'              => array(
				'title'       => __('Título', 'brazilpays-plugin '),
				'type'        => 'safe_text',
				'description' => __('Título que o cliente verá na tela de pagamento', 'brazilpays-plugin '),
				'default'     => __('Pix - Brazil Pays Pagamentos', 'brazilpays-plugin '),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __('Descrição', 'brazilpays-plugin '),
				'type'        => 'textarea',
				'description' => __('Descrição do método de pagamento', 'brazilpays-plugin '),
				'default'     => __('Realize o pagamento através de Pix!', 'brazilpays-plugin '),
				'desc_tip'    => true,
			),
		);
	}


	/**
	 * Check If The Gateway Is Available For Use.
	 *
	 * @return bool
	 */
	public function is_available()
	{
		$order          = null;
		$needs_shipping = false;

		// Test if shipping is needed first.
		if (WC()->cart && WC()->cart->needs_shipping()) {
			$needs_shipping = true;
		} elseif (is_page(wc_get_page_id('checkout')) && 0 < get_query_var('order-pay')) {
			$order_id = absint(get_query_var('order-pay'));
			$order    = wc_get_order($order_id);

			// Test if order needs shipping.
			if ($order && 0 < count($order->get_items())) {
				foreach ($order->get_items() as $item) {
					$_product = $item->get_product();
					if ($_product && $_product->needs_shipping()) {
						$needs_shipping = true;
						break;
					}
				}
			}
		}

		$needs_shipping = apply_filters('woocommerce_cart_needs_shipping', $needs_shipping);

		// Only apply if all packages are being shipped via chosen method, or order is virtual.
		if (!empty($this->enable_for_methods) && $needs_shipping) {
			$order_shipping_items            = is_object($order) ? $order->get_shipping_methods() : false;
			$chosen_shipping_methods_session = WC()->session->get('chosen_shipping_methods');

			if ($order_shipping_items) {
				$canonical_rate_ids = $this->get_canonical_order_shipping_item_rate_ids($order_shipping_items);
			} else {
				$canonical_rate_ids = $this->get_canonical_package_rate_ids($chosen_shipping_methods_session);
			}

			if (!count($this->get_matching_rates($canonical_rate_ids))) {
				return false;
			}
		}

		return parent::is_available();
	}

	/**
	 * Checks to see whether or not the admin settings are being accessed by the current request.
	 *
	 * @return bool
	 */
	private function is_accessing_settings()
	{
		if (is_admin()) {
			// phpcs:disable WordPress.Security.NonceVerification
			if (!isset($_REQUEST['page']) || 'wc-settings' !== $_REQUEST['page']) {
				return false;
			}
			if (!isset($_REQUEST['tab']) || 'checkout' !== $_REQUEST['tab']) {
				return false;
			}
			if (!isset($_REQUEST['section']) || 'brazilpays-pix' !== $_REQUEST['section']) {
				return false;
			}
			// phpcs:enable WordPress.Security.NonceVerification

			return true;
		}

		return false;
	}

	/**
	 * Loads all of the shipping method options for the enable_for_methods field.
	 *
	 * @return array
	 */
	private function load_shipping_method_options()
	{
		// Since this is expensive, we only want to do it if we're actually on the settings page.
		if (!$this->is_accessing_settings()) {
			return array();
		}

		$data_store = WC_Data_Store::load('shipping-zone');
		$raw_zones  = $data_store->get_zones();

		foreach ($raw_zones as $raw_zone) {
			$zones[] = new WC_Shipping_Zone($raw_zone);
		}

		$zones[] = new WC_Shipping_Zone(0);

		$options = array();
		foreach (WC()->shipping()->load_shipping_methods() as $method) {

			$options[$method->get_method_title()] = array();

			// Translators: %1$s shipping method name.
			$options[$method->get_method_title()][$method->id] = sprintf(__('Any &quot;%1$s&quot; method', 'brazilpays-plugin '), $method->get_method_title());

			foreach ($zones as $zone) {

				$shipping_method_instances = $zone->get_shipping_methods();

				foreach ($shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance) {

					if ($shipping_method_instance->id !== $method->id) {
						continue;
					}

					$option_id = $shipping_method_instance->get_rate_id();

					// Translators: %1$s shipping method title, %2$s shipping method id.
					$option_instance_title = sprintf(__('%1$s (#%2$s)', 'brazilpays-plugin '), $shipping_method_instance->get_title(), $shipping_method_instance_id);

					// Translators: %1$s zone name, %2$s shipping method instance name.
					$option_title = sprintf(__('%1$s &ndash; %2$s', 'brazilpays-plugin '), $zone->get_id() ? $zone->get_zone_name() : __('Other locations', 'brazilpays-plugin '), $option_instance_title);

					$options[$method->get_method_title()][$option_id] = $option_title;
				}
			}
		}

		return $options;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
	 * @return array $canonical_rate_ids    Rate IDs in a canonical format.
	 */
	private function get_canonical_order_shipping_item_rate_ids($order_shipping_items)
	{

		$canonical_rate_ids = array();

		foreach ($order_shipping_items as $order_shipping_item) {
			$canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
		}

		return $canonical_rate_ids;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
	 * @return array $canonical_rate_ids  Rate IDs in a canonical format.
	 */
	private function get_canonical_package_rate_ids($chosen_package_rate_ids)
	{

		$shipping_packages  = WC()->shipping()->get_packages();
		$canonical_rate_ids = array();

		if (!empty($chosen_package_rate_ids) && is_array($chosen_package_rate_ids)) {
			foreach ($chosen_package_rate_ids as $package_key => $chosen_package_rate_id) {
				if (!empty($shipping_packages[$package_key]['rates'][$chosen_package_rate_id])) {
					$chosen_rate          = $shipping_packages[$package_key]['rates'][$chosen_package_rate_id];
					$canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
				}
			}
		}

		return $canonical_rate_ids;
	}

	/**
	 * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
	 *
	 * @since  3.4.0
	 *
	 * @param array $rate_ids Rate ids to check.
	 * @return boolean
	 */
	private function get_matching_rates($rate_ids)
	{
		// First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
		return array_unique(array_merge(array_intersect($this->enable_for_methods, $rate_ids), array_intersect($this->enable_for_methods, array_unique(array_map('wc_get_string_before_colon', $rate_ids)))));
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment($order_id)
	{
        //buscando token já autenticado
        $token = $this->authToken();

        if(empty($token)){
            return ['result' => 'fail'];
        }

		$cotacao_dolar = $this->cotarDolar($token);

		if(empty($cotacao_dolar)){
            return ['result' => 'fail'];
        }

        $order = wc_get_order($order_id);
        $cart_total = $this->get_order_total();
		$total = (string)$cart_total;

		if($cart_total < 1.00){
			wc_add_notice(
				__('O valor total do pedido deve ser superior a $1.00!', 'brazilpays-plugin'),
                'error'
            );

            return [
                'result' => 'fail',
            ];
		}

        $urlPix = 'https://api-brazilpays.megaleios.com/api/v1/Charge';

        $zipCode = $order->get_billing_postcode();
        $address = $order->get_billing_address_1();
        $cityName = $order->get_billing_city();
        $stateName = $order->get_billing_state();
		$complement = $order->get_billing_address_2();
        $fullName = $order->get_formatted_billing_full_name();
        $email = $order->get_billing_email();
        $phone = $order->get_billing_phone();
        $cpfCnpj = $order->get_meta('cpf_cnpj');

		$body_req = [
			'profile' => [
				'zipCode' => $zipCode,
				'streetAddress' => $address,
				'number' => $address,
				'cityName' => $cityName,
				'stateName' => $stateName,
				'stateUf' => $stateName,
				'neighborhood' => $zipCode,
				'complement' => $complement,
				'fullName' => $fullName,
				'email' => $email,
				'phone' => $phone,
				'cpfOrCnpj' => $cpfCnpj,
				'creditCard' => [
					'cardNumber' => '0000000000000000',
					'holderName' => '00',
					'expireMonth' => '00',
					'expireYear' => '0000',
					'cvv' => '000'
				],
				'gender' => 'O',
				'birthDate' => '20/04/1977',
			],
			'exchange' => $cotacao_dolar['cotacao'],
			'usedExchange' => $cotacao_dolar['usedExchange'],
			'baseChargeId' => '',
			'invoice' => '',
			'description' => $order_id,
			'typeCharge' => '0',
			'paymentMethod' => '0',
			'installment' => 1,
			'valuesUsd' => [
				'netValue' => $total,
			],
			'urlWebHook' => ''
		];

		$args = array(
			'headers' => array(
					'Authorization' => 'Bearer '. $token,
					'Content-Type' => 'application/json'
				),
			'body' => json_encode($body_req)
		);

        $response = wp_remote_post($urlPix, $args);		

        if($response['response']['code'] != 200){
            wc_add_notice(
				__('Erro ao tentar realizar pagamento em pix', 'brazilpays-plugin'),
                'error'
            );

            return [
                'result' => 'fail',
            ];
        }

        if(!is_wp_error($response)){
            $body = wp_remote_retrieve_body($response);

            $data = json_decode($body, true);

            $order->update_meta_data('id_transacao', $data['data']['id']);
            $order->update_meta_data('url_qrcode', $data['data']['qrCode']);
            
            $order->save();

            //informando que o pagamento do pedido está pendente
            $order->update_status(
                $this->status_when_waiting,
                __('BrazilPays: O pix foi emitido, mas o pagamento ainda não foi realizado.', 'brazilpays-plugin')
            );

            //adicionando a chave pix como anotação do pedido
            $order->add_order_note(
                __("Url qrcode pix:" . $data['data']['qrCode'], 'brazilpays-plugin')
            );

            // Remove cart.
            WC()->cart->empty_cart();

            // Return thankyou redirect.
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }else{
            return ['result' => 'fail'];
        }
        
	}


	public function brazilpays_check_payment_status(){
		$order = wc_get_orders(array('status' => 'wc-pending'));
		$token = $this->authToken();

		$args = array(
			'headers' => array( 'Authorization' => 'Bearer '. $token ),
		);

		foreach($order as $single_order){
			$id_transaction = $single_order->get_meta('id_transacao');

			if(!empty($id_transaction)){
				$url = 'https://api-brazilpays.megaleios.com/api/v1/Charge/'.$id_transaction;

				$response = wp_remote_get($url, $args);

				//verificando resposta da requisição
				if($response['response']['code'] != 200){
					return ['result'=> 'fail'];
				}

				if(!is_wp_error($response)){

					$body = wp_remote_retrieve_body($response);
		
					$data_request = json_decode($body, true);
		
					if($data_request['data']['paymentStatus'] == "1"){
						$single_order->update_meta_data('pago', true);
						$single_order->update_status('completed');
					}else{
						$single_order->update_status('pending');
					}
				}
			}
		}
	}
	
	/**
	 * Função responsável por fazer a autenticação do token para realizar todas as outras requisições no plugin.
	 */
    public function authToken(){

        $url = 'https://api-brazilpays.megaleios.com/api/v1/Merchant/External/Token';
        
        //fazendo requisição para autenticar token do usuário
        $response = wp_remote_post(
            $url,
            [
                'headers' => [
                    'merchantCode' => $this->merchant_code,
                    'publicKey' => $this->public_key
                ],
            ]
        );

        //verificando resposta da requisição
        if($response['response']['code'] != 200){
            return ['result'=> 'fail'];
        }

        if(!is_wp_error($response)){

            $body = wp_remote_retrieve_body($response);

            $data_request = json_decode($body, true);

            $tokenFinal = $data_request['data']['access_token'];

			//retornando token
            return $tokenFinal;
        }
    }

	/**
	 * Função retorna cotação do dólar
	 */
	public function cotarDolar($token){

		$url = 'https://api-brazilpays.megaleios.com/api/v1/Charge/Calculate';

		$args = array(
			'headers' => array(
					'Authorization' => 'Bearer '. $token,
					'Content-Type' => 'application/json'
				),
			'body' => json_encode(array('amount' => 1.00))
		);

		$response = wp_remote_post($url, $args);

		//verificando resposta da requisição
        if($response['response']['code'] != 200){
            return ['result'=> 'fail'];
        }

        if(!is_wp_error($response)){

            $body = wp_remote_retrieve_body($response);

            $data_request = json_decode($body, true);

			$dolar = [
				'cotacao' => $data_request['data']['exchange'],
				'usedExchange' => $data_request['data']['usedExchange']
			];

			//retornando token
            return $dolar;
        }

	}

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page($order_id)
	{
		//buscando informações do pedido
		$order = wc_get_order($order_id);
		$order_data = $order->get_meta('url_qrcode');

		//apresentando qr_code
		$finalImage = '<img src="data:image/png;base64,' .base64_encode($order_data) .'" id="imageQRCode" alt="QR Code" class="qrcode" style="display: block;margin-left: auto;margin-right: auto;"/>';
		echo $finalImage;
	
		echo '<div style="font-size: 20px;color: #303030;text-align: center;">';
		echo wp_kses_post(wpautop(wptexturize($this->instructions)));

		echo 'Ou copie e cole a seguinte chave pix em seu aplicativo de banco para realizar o pagamento:';
		echo '<blockquote>' . $order_data . '</blockquote>';
		echo '</div>';
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin  Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions($order, $sent_to_admin, $plain_text = false)
	{
		if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
			echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
		}
	}

}