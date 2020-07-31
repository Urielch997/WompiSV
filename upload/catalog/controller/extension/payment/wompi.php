<?php
class ControllerExtensionPaymentwompi extends Controller {

	public function index() {
		$curl = curl_init();

		curl_setopt_array($curl, array(
		CURLOPT_URL => "https://id.wompi.sv/connect/token",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_POSTFIELDS => "grant_type=client_credentials&client_id=".$this->config->get('payment_wompi_account')."&client_secret=".$this->config->get('payment_wompi_secret')."&audience=wompi_api",
		CURLOPT_HTTPHEADER => array(
			"content-type: application/x-www-form-urlencoded"
		),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
			return "cURL Error #:" . $err;
		} else {
			$tokenwompi = json_decode($response);
			
		}

		$data['button_confirm'] = $this->language->get('button_confirm');

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

	

		$data['sid'] = $this->config->get('payment_wompi_account');
		$data['payment_wompi_aplicativo'] = $this->config->get('payment_wompi_aplicativo');
		$data['currency_code'] = $order_info['currency_code'];
		$data['total'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
		$data['cart_order_id'] = $this->session->data['order_id'];
		$data['card_holder_name'] = $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
		$data['street_address'] = $order_info['payment_address_1'];
		$data['city'] = $order_info['payment_city'];

		if ($order_info['payment_iso_code_2'] == 'US' || $order_info['payment_iso_code_2'] == 'CA') {
			$data['state'] = $order_info['payment_zone'];
		} else {
			$data['state'] = 'XX';
		}

		
		$data['zip'] = $order_info['payment_postcode'];
		$data['country'] = $order_info['payment_country'];
		$data['email'] = $order_info['email'];
		$data['phone'] = $order_info['telephone'];

		if ($this->cart->hasShipping()) {
			$data['ship_street_address'] = $order_info['shipping_address_1'];
			$data['ship_city'] = $order_info['shipping_city'];
			$data['ship_state'] = $order_info['shipping_zone'];
			$data['ship_zip'] = $order_info['shipping_postcode'];
			$data['ship_country'] = $order_info['shipping_country'];
		} else {
			$data['ship_street_address'] = $order_info['payment_address_1'];
			$data['ship_city'] = $order_info['payment_city'];
			$data['ship_state'] = $order_info['payment_zone'];
			$data['ship_zip'] = $order_info['payment_postcode'];
			$data['ship_country'] = $order_info['payment_country'];
		}

		$data['products'] = array();

		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$data['products'][] = array(
				'product_id'  => $product['product_id'],
				'name'        => $product['name'],
				'description' => $product['name'],
				'quantity'    => $product['quantity'],
				'monto'       => $this->currency->format($product['price'], $order_info['currency_code'], $order_info['currency_value'], false)
			);
		}

		$nombreProducto = '';
		foreach ($products as $product) {
			$nombreProducto .= $product['name'].'; ';
		}

		$curl2 = curl_init();
		curl_setopt_array($curl2, array(
			CURLOPT_URL => "https://api.wompi.sv/EnlacePago",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => '{
				"identificadorEnlaceComercio": "'.$data['payment_wompi_aplicativo'].'",
				"monto": '.$data['total'].',
				"nombreProducto": "'.$nombreProducto.'",
				"formaPago": {
				  "permitirTarjetaCreditoDebido": true,
				  "permitirPagoConPuntoAgricola": false,
				},
				"infoProducto": {
				  "descripcionProducto": "'.$nombreProducto.'",
				},
				"configuracion": {
					"urlRedirect": "http://www.localhost/upload/index.php?route=extension/payment/wompi/callback&order_id='.$data['cart_order_id'].'",
					"emailsNotificacion": "'.$data['email'].'",
					"notificarTransaccionCliente": true
				  },
			}',
			CURLOPT_HTTPHEADER => array(
			"authorization: Bearer ".$tokenwompi->access_token,
			"Content-Type: application/json"
			),
		));
		

			$res = curl_exec($curl2);
			$err = curl_error($curl2);
			
			curl_close($curl2);
			
			if ($err) {
				return "cURL Error #:" . $err;
			} else {

				$urlwompisv = json_decode($res);
				$data['action'] = "#";
				
			}

		if ($this->config->get('payment_wompi_test')) {
			$data['demo'] = 'Y';
		} else {
			$data['demo'] = '';
		}

		if ($this->config->get('payment_wompi_display')) {
			$data['display'] = 'Y';
		} else {
			$data['display'] = '';
		}

		$data['lang'] = $this->session->data['language'];

		$data['return_url'] = $this->url->link('extension/payment/wompi/callback', '', true);

		echo "<script>location.href='".$urlwompisv->urlEnlace."';</script>";
		return $this->load->view('extension/payment/wompi', $data);
	}

	public function callback(){
		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($this->request->get['order_id']);

		if (!$this->config->get('payment_wompi_test')) {
			$order_number = $this->request->get['order_id'];
		} else {
			$order_number = '1';
		}

		
			
		$this->model_checkout_order->addOrderHistory($this->request->get['order_id'], $this->config->get('payment_wompi_order_status_id'));

			// We can't use $this->response->redirect() here, because of 2CO behavior. It fetches this page
			// on behalf of the user and thus user (and his browser) see this as located at 2checkout.com
			// domain. So user's cookies are not here and he will see empty basket and probably other
			// weird things.

			echo '<html>' . "\n";
			echo '<head>' . "\n";
			echo '  <meta http-equiv="Refresh" content="0; url=' . $this->url->link('checkout/success') . '">' . "\n";
			echo '</head>' . "\n";
			echo '<body>' . "\n";
			echo '  <p>Please follow <a href="' . $this->url->link('checkout/success') . '">link</a>!</p>' . "\n";
			echo '</body>' . "\n";
			echo '</html>' . "\n";
			
			exit();
			echo $this->request->get['idEnlace'].' '. $this->config->get('payment_wompi_order_status_id');
			
		
	}
}