<?php

/**
 * WC_BrazilPays_Gateway_Credit
 *
 * Providencia um Gateway de pagamento próprio via Cartão de Crédito do BrazilPays
 *
 * @class       WC_BrazilPays_Gateway_Credit
 * @extends     WC_Payment_Gateway
 * @version     2.1.0
 * @package     WooCommerce\Classes\Payment
 */
class WC_BrazilPays_Gateway_Credit extends WC_Payment_Gateway
{

	/**
	 * Gateway instructions that will be added to the thank you page and emails.
	 *
	 * @var string
	 */
	public $instructions;

	public $status_when_waiting;

	
	public $title;
	public $description;
	public $id;
	public $icon;
	public $method_title;
	public $method_description;
	public $has_fields;
	public $form_fields;

	public $merchant_code;
	public $public_key;


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

		add_filter('woocommerce_gateway_description', array($this, 'brazilpays_description_fields_credit'), 20, 2);
		add_action('woocommerce_checkout_process', array($this, 'brazilpays_description_fields_validation_credit'));
		// add_action('woocommerce_checkout_update_order_meta', 'brazilpays_checkout_update_order_meta', 10, 1);

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
		$this->id                 = 'brazilpays-credit';
        $this->merchant_code	  = __('Adicionar Merchant Code', 'brazilpays-plugin ');
        $this->public_key         = __('Adicionar Public Key', 'brazilpays-plugin ');
		$this->icon               = apply_filters('brazilpays-plugin', plugins_url('../assets/icon-credit.png', __FILE__));
		$this->method_title       = __('Cartão de Crédito', 'brazilpays-plugin ');
		$this->method_description = __('Receba pagamentos no crédito utilizando sua conta BrazilPays', 'brazilpays-plugin ');
		$this->has_fields         = false;
		$this->instructions 	  = __('Realize seu pagamento com cartão de crédito!', 'brazilpays-plugin ');
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __('Ativar/Desativar', 'brazilpays-plugin '),
				'label'       => __('Ativar Pagamento no Cartão de Crédito - BrazilPays', 'brazilpays-plugin '),
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
				'default'     => __('Cartão de Crédito - Brazil Pays Pagamentos', 'brazilpays-plugin '),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __('Descrição', 'brazilpays-plugin '),
				'type'        => 'textarea',
				'description' => __('Descrição do método de pagamento', 'brazilpays-plugin '),
				'default'     => __('Realize o pagamento utilizando o seu cartão de crédito!', 'brazilpays-plugin '),
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
			if (!isset($_REQUEST['section']) || 'brazilpays-credit' !== $_REQUEST['section']) {
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
	public function process_payment($order_id){
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

        $urlCard = 'https://api-brazilpays.megaleios.com/api/v1/Charge';

        $zipCode = $order->get_billing_postcode();
        $address = $order->get_billing_address_1();
		if(isset($_POST['billing_number']) && !empty($_POST['billing_number'])){
			$number = $_POST['billing_number'];
		}else{
			$number = preg_replace("/[^0-9]/", "", $address);
		}
        $cityName = $order->get_billing_city();
        $stateName = $order->get_billing_state();
		$complement = $order->get_billing_address_2();
        $fullName = $order->get_formatted_billing_full_name();
        $email = $order->get_billing_email();
        $phone = $order->get_billing_phone();
        $cpfCnpj = $_POST['card_cpf_cnpj'];
		$cpfFinal = preg_replace("/[^0-9]/", "", $cpfCnpj);
		$cardNumber = $_POST['card_number'];
		$cardName = $_POST['card_name'];
		$cardMonth = $_POST['card_month'];
		$cardYear = $_POST['card_year'];
		$cardCvv = $_POST['card_cvv'];
		$cardInstallments = $_POST['card_installments'];
		$gender = $_POST['card_gender'];
		$birthDate = $_POST['card_birth_date'];

		// $date = date('d/m/y', strtotime($birthDate));

		$body_req = [
			'profile' => [
				'zipCode' => $zipCode,
				'streetAddress' => $address,
				'number' => $number,
				'cityName' => $cityName,
				'stateName' => $stateName,
				'stateUf' => $stateName,
				'neighborhood' => 'bairro',
				'complement' => $complement,
				'fullName' => $fullName,
				'email' => $email,
				'phone' => $phone,
				'cpfOrCnpj' => $cpfFinal,
				'creditCard' => [
					'cardNumber' => $cardNumber,
					'holderName' => $cardName,
					'expireMonth' => $cardMonth,
					'expireYear' => $cardYear,
					'cvv' => $cardCvv
				],
				'gender' => $gender,
				'birthDate' => $birthDate,
			],
			'exchange' => $cotacao_dolar['cotacao'],
			'usedExchange' => $cotacao_dolar['usedExchange'],
			'baseChargeId' => '',
			'invoice' => $order_id,
			'description' => $order_id,
			'typeCharge' => '2',
			'paymentMethod' => '2',
			'installment' => $cardInstallments,
			'valuesUsd' => [
				'netValue' => $total,
			],
			'urlWebHook' => ''
		];

		// if(!empty(($number))){
		// 	echo("<script>console.log('PHP: " . var_dump($cpfFinal) . "');</script>");
		// }

		$argscard = array(
			'method' => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer '. $token,
				'Content-Type' => 'application/json',
				'Connection' => 'keep-alive',
				'Accept-Encoding' => 'gzip, deflate, br',
				'Accept' => 'application/json'
			),
			'body' => json_encode($body_req),
			'timeout' => 90
		);

        $res = wp_remote_post($urlCard, $argscard);

		echo("<script>console.log('PHP: " . var_dump($body_req) . "');</script>");

        if(wp_remote_retrieve_response_code($res) != 200){
            wc_add_notice(
				__('Erro ao tentar realizar pagamento com o cartão de crédito', 'brazilpays-plugin'),
                'error'
            );
            return ['result' => 'fail'];
        }

		if(wp_remote_retrieve_response_code($res) == 400){
            wc_add_notice(
				__('Um dos dados informados está incorreto!', 'brazilpays-plugin'),
                'error'
            );
            return ['result' => 'fail'];
        }

        if(!is_wp_error($res)){
			$body = array();
			$data = array();

            $body = wp_remote_retrieve_body($res);

            $data = json_decode($body, true);

            $order->update_meta_data('id_transacao', $data['data']['id']);
            
            $order->save();

            //adicionando a chave pix como anotação do pedido
            $order->add_order_note(
                __("Método de pagamento: Cartão de crédito", 'brazilpays-plugin')
            );

			$order->update_status('completed');

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
				'timeout' => 90
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
			'body' => json_encode(array('amount' => 1.00)),
			'timeout' => 90
		);

		$response = wp_remote_post($url, $args);

		//verificando resposta da requisição
        if(wp_remote_retrieve_response_code($response) != 200){
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

	public function brazilpays_check_payment_status(){
		$order = wc_get_orders(array('status' => array('wc-pending', 'wc-on-hold', 'wc-processing', 'wc-completed', 'wc-failed', 'wc-cancelled', 'wc-refunded'), 'limit' => -1));
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
				// if(wp_remote_retrieve_response_code($response) != 200){
				// 	return ['result'=> 'fail'];
				// }

				if(wp_remote_retrieve_response_code($response) == 200){

					$body = wp_remote_retrieve_body($response);
		
					$data_request = json_decode($body, true);

					if(!empty($data_request['data']['paymentStatus'])){
						if($data_request['data']['paymentStatus'] === 0){
							$single_order->update_status('wc-pending');
						} else if($data_request['data']['paymentStatus'] === 1){
							$single_order->update_meta_data('pago', true);
							$single_order->update_status('wc-completed');
						} else if($data_request['data']['paymentStatus'] === 2){
							$single_order->update_status('wc-failed');
						} else if($data_request['data']['paymentStatus'] === 3){
							$single_order->update_status('wc-failed');
						} else if($data_request['data']['paymentStatus'] === 4){
							$single_order->update_status('wc-cancelled');
						} else if($data_request['data']['paymentStatus'] === 6){
							$single_order->update_status('wc-refunded');
						}
					}

					// switch($data_request['data']['paymentStatus']){
					// 	case "0":
					// 		$single_order->update_status('wc-pending');
					// 		break;

					// 	case "1":
					// 		$single_order->update_meta_data('pago', true);
					// 		$single_order->update_status('wc-completed');
					// 		break;

					// 	case "2":
					// 		$single_order->update_status('wc-failed');
					// 		break;

					// 	case "3":
					// 		$single_order->update_status('wc-failed');
					// 		break;
						
					// 	case "4":
					// 		$single_order->update_status('wc-cancelled');
					// 		break;

					// 	case "6":
					// 		$single_order->update_status('wc-refunded');
					// 		break;
					// }
				}
			}
		}
	}

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page()
	{
		echo '<div style="font-size: 20px;color: #303030;text-align: center;">';
		echo '<h3>O pagamento foi registrado no sistema com sucesso!</h3>';
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

	public function getParcelas($token, $total_amount){

		$url = 'https://api-brazilpays.megaleios.com/api/v1/Charge/Calculate';

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer '. $token,
				'Content-Type' => 'application/json'
			),
			'body' => json_encode(array('amount' => $total_amount)),
			'timeout' => 90
		);

		$response = wp_remote_post($url, $args);

		//verificando resposta da requisição
        if(wp_remote_retrieve_response_code($response) != 200){
            return ['result'=> 'fail'];
        }
		
		if(!is_wp_error($response)){
			$parcelas_total = array();
			$body = wp_remote_retrieve_body($response);

			$data_request = json_decode($body, true);
			$parcelas_total = $data_request['data']['creditCard']['installments'];

			return $parcelas_total;
		}
	}

	
	public function brazilpays_description_fields_credit( $description, $payment_id) {
		$parcelas = array();
		$total_amount = $this->get_order_total();
		$token = $this->authToken();
		$parcelas = $this->getParcelas($token, $total_amount);


		// $options = array();

		// foreach($parcelas as $parcela){
		// 	if(!empty($parcela['amount'])){
		// 		$valor = $parcela['amount'];
		// 	}
		// 	if(!empty($parcela['qtInstallment'])){
		// 		$qnt = $parcela['qtInstallment'];
		// 		$qntInt = $parcela['qtInstallment'];
		// 		settype($qntInt, 'integer');
		// 	}

		// 	$options[$qntInt] = $qnt.'x de R$'. number_format((float)($valor), 2, '.', '');
		// }
		/*
		* Apresentando campos para o método de pagamento com Cartão de Crédito
		*/    
		if($payment_id === 'brazilpays-credit'){
			ob_start();

			echo '<div style="display: flex; width: 100%!important; height: auto;">';

			echo '<div style="display: block; width: 100% !important; height: auto;">';

			echo '<h4>Número de Parcelas: &nbsp;<abbr class="required" title="obrigatório">*</abbr></h4>';
			echo '<br><span class="woocommerce-input-wrapper" style="padding-top: 15px;">';
			
			// woocommerce_form_field('card_installments', array(
			// 	'type' => 'radio',
			// 	'class' => array('form-row'),
			// 	'required' => true,
			// 	'options' => $options,
			// 	)
			// );

			foreach($parcelas as $parcela){
				if(!empty($parcela['amount']) && !empty($parcela['qtInstallment'])){
					$valor = $parcela['amount'];
				
					$qnt = $parcela['qtInstallment'];
					$qntInt = $parcela['qtInstallment'];
					settype($qntInt, 'integer');
					echo '<div style="margin-bottom: 10px;">';
					echo '<input type="radio" class="input-radio" name="card_installments" value="'.$qntInt.'" data-saved-value="CFW_EMPTY" data-parsley-required="true" data-parsley-multiple="card_installments" id="card_installments_'.$qntInt.'">';
					echo '<label for="card_installments_'.$qntInt.'" class="radio">'.$qntInt.'x de R$'.number_format((float)($valor), 2, '.', '').'</label>';
					echo '</div>';
				}
			}

			echo '</span>';

			echo '</div>';
			
			echo '<div style="display: block; width: 100% !important; height: auto;">';

			echo '<p class="form-row form-row validate-required woocommerce-invalid woocommerce-invalid-required-field" id="card_numberfield" data-priority="">';
			echo '<label for="card_number">Informe o número do cartão: <abbr class="required" title="obrigatório">*</abbr></label>';
			echo '<span class="woocommerce-input-wrapper">';
			echo '<input type="text" name="card_number" required class="input-text" onkeypress="return event.charCode >= 48 && event.charCode <= 57">';
			echo '</span>';
			echo '</p>';

			// woocommerce_form_field('card_number', array(
			// 		'type' => 'text',
			// 		'class' => array('form-row'),
			// 		'label' => __('Informe o número do cartão: ', 'brazilpays-plugin'),
			// 		'required' => true,
			// 	)
			// );

			woocommerce_form_field('card_name', array(
					'type' => 'text',
					'class' => array('form-row'),
					'label' => __('Informe o nome que está no cartão: ', 'brazilpays-plugin'),
					'required' => true,
				)
			);

			woocommerce_form_field('card_month', array(
					'type' => 'select',
					'class' => array('form-row'),
					'label' => __('Mês de vencimento: ', 'brazilpays-plugin'),
					'required' => true,
					'options' => array(
						'1' => __('01', 'brazilpays-plugin'),
						'2' => __('02', 'brazilpays-plugin'),
						'3' => __('03', 'brazilpays-plugin'),
						'4' => __('04', 'brazilpays-plugin'),
						'5' => __('05', 'brazilpays-plugin'),
						'6' => __('06', 'brazilpays-plugin'),
						'7' => __('07', 'brazilpays-plugin'),
						'8' => __('08', 'brazilpays-plugin'),
						'9' => __('09', 'brazilpays-plugin'),
						'10' => __('10', 'brazilpays-plugin'),
						'11' => __('11', 'brazilpays-plugin'),
						'12' => __('12', 'brazilpays-plugin'),
					),
				)
			);

			// woocommerce_form_field('card_year', array(
			// 		'type' => 'text',
			// 		'class' => array('form-row'),
			// 		'label' => __('Ano de vencimento: ', 'brazilpays-plugin'),
			// 		'required' => true,
			// 	)
			// );

			echo '<p class="form-row form-row validate-required woocommerce-invalid woocommerce-invalid-required-field" id="card_number_field" data-priority="">';
			echo '<label for="card_year">Ano de vencimento: <abbr class="required" title="obrigatório">*</abbr></label>';
			echo '<span class="woocommerce-input-wrapper">';
			echo '<select name="card_year" required class="input-text">';
			for($i = 2023; $i <= 2060; $i++){
				echo '<option value="'.$i.'">'.$i.'</option>';
			}
			echo '</select>';
			echo '</span>';
			echo '</p>';

			echo '<p class="form-row form-row validate-required woocommerce-invalid woocommerce-invalid-required-field" id="card_number_field" data-priority="">';
			echo '<label for="card_cvv">CVV: <abbr class="required" title="obrigatório">*</abbr></label>';
			echo '<span class="woocommerce-input-wrapper">';
			echo '<input type="number" name="card_cvv" required class="input-text" max="999">';
			echo '</span>';
			echo '</p>';

			// woocommerce_form_field('card_cvv', array(
			// 		'type' => 'text',
			// 		'class' => array('form-row'),
			// 		'label' => __('CVV: ', 'brazilpays-plugin'),
			// 		'required' => true,
			// 	)
			// );

			// 

			echo '<p class="form-row form-row validate-required woocommerce-invalid woocommerce-invalid-required-field" id="card_cpf_cnpj_field" data-priority="">';
			echo '<label for="card_cpf_cnpj">CPF ou CNPJ: <abbr class="required" title="obrigatório">*</abbr></label>';
			echo '<span class="woocommerce-input-wrapper">';
			echo '<input type="text" id="card_cpf_cnpj" name="card_cpf_cnpj" required class="input-text" onkeypress="return event.charCode >= 48 && event.charCode <= 57">';
			echo '</span>';
			echo '</p>';

			// woocommerce_form_field('card_cpf_cnpj', array(
			// 	'type' => 'text',
			// 	'class' => array('form-row'),
			// 	'label' => __('CPF ou CNPJ: ', 'brazilpays-plugin'),
			// 	'required' => true,
			// 	)
			// );

			woocommerce_form_field('card_gender', array(
				'type' => 'select',
				'class' => array('form-row'),
				'label' => __('Gênero: ', 'brazilpays-plugin'),
				'required' => true,
				'options' => array(
					'M' => 'Masculino',
					'F' => 'Feminino'
					)
				)
			);
			
			echo '<p class="form-row form-row validate-required woocommerce-invalid woocommerce-invalid-required-field" id="card_number_field" data-priority="">';
			echo '<label for="card_birth_date">Data de nascimento: <abbr class="required" title="obrigatório">*</abbr></label>';
			echo '<span class="woocommerce-input-wrapper">';
			echo '<input type="text" name="card_birth_date" required class="input-text" maxlength="10" onkeypress="return event.charCode >= 48 && event.charCode <= 57" id="card_birth_date">';
			echo '<span/>';
			echo '</p>';

			?>

			<script>
				document.getElementById("card_birth_date").addEventListener("input", function() {
				var i = document.getElementById("card_birth_date").value.length;
				var str = document.getElementById("card_birth_date").value;
				if (isNaN(Number(str.charAt(i-1)))) {
					document.getElementById("card_birth_date").value = str.substr(0, i-1);
				}
				});
				document.addEventListener('keydown', function(event) { 
				if(event.keyCode != 46 && event.keyCode != 8){
				var i = document.getElementById("card_birth_date").value.length;
				if (i === 2 || i === 5)
					document.getElementById("card_birth_date").value = document.getElementById("card_birth_date").value + "/";
				}
				});
			</script>

			<?php 

			// woocommerce_form_field('card_birth_date', array(
			// 	'type' => 'text',
			// 	'class' => array('form-row'),
			// 	'label' => __('Data de nascimento: ', 'brazilpays-plugin'),
			// 	'required' => true,
			// 	)
			// );

			echo '</div>';

			echo '</div>';

			?>

			<script>
				function checkPattern(elem){
					if(!elem.value.match('^' + elem.getAttribute('pattern') + '$')){
						alert('')
					}
				}
			</script>
			
			
			<?php

			$description .= ob_get_clean();
		}

		return $description;
	}

	public function brazilpays_description_fields_validation_credit(){
		if($_POST['payment_method'] === 'brazilpays-credit'){

			if(isset($_POST['card_cpf_cnpj']) && empty($_POST['card_cpf_cnpj'])){
				wc_add_notice('Por favor informe um CPF ou CNPJ válido!', 'error');
			}

			if(isset($_POST['card_cpf_cnpj'])){
				if(strlen($_POST['card_cpf_cnpj']) < 11){
					wc_add_notice('O CPF informado não é válido!', 'error');
				}				
			}

			if(isset($_POST['card_number']) && empty($_POST['card_number'])){
				wc_add_notice('Por favor informe o número do cartão!', 'error');
			}

			if(isset($_POST['card_name']) && empty($_POST['card_name'])){
				wc_add_notice('Por favor informe o nome escrito no cartão!', 'error');
			}

			if(isset($_POST['card_month']) && empty($_POST['card_month'])){
				wc_add_notice('Por favor informe o mês de vencimento do cartão!', 'error');
			}

			if(isset($_POST['card_year']) && empty($_POST['card_year'])){
				wc_add_notice('Por favor informe o ano de vencimento do cartão!', 'error');
			}

			if(isset($_POST['card_cvv']) && empty($_POST['card_cvv'])){
				wc_add_notice('Por favor informe o CVV do cartão!', 'error');
			}

			if(isset($_POST['card_cvv']) && !empty($_POST['card_cvv']) && $_POST['card_cvv'] > 999){
				wc_add_notice('Por favor informe um CVV válido!', 'error');
			}

			if(isset($_POST['card_gender']) && empty($_POST['card_gender'])){
				wc_add_notice('Por favor informe o gênero!', 'error');
			}

			if(isset($_POST['card_birth_date']) && empty($_POST['card_birth_date'])){
				wc_add_notice('Por favor informe a data de nascimento!', 'error');
			}
		}
	
	}

	// public function brazilpays_checkout_update_order_meta_credit($order_id){
		
	// 	if(isset($_POST['card_cpf_cnpj']) || !empty($_POST['card_cpf_cnpj'])){
	// 		update_post_meta($order_id, 'card_cpf_cnpj', $_POST['card_cpf_cnpj']);
	// 	}
		
	// }
}