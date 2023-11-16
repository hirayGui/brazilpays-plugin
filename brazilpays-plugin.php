<?php 

/**
 * Plugin Name: Brazil Pays
 * Plugin URI:  https://github.com/hiraygui/brazilpays-plugin 
 * Author: BrazilPays
 * Author URI: https://brazilpays.com/
 * Description: Este plugin permite que o usuário possa realizar pagamentos através de um checkout transparente da brazilpays dentro do woocommerce
 * Version: 1.0.0
 * License:     GPL-2.0+
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: brazilpays-plugin 
 * 
 * Class WC_BrazilPays_Gateway file.
 *
 * @package WooCommerce\brazilpays-plugin 
 */

 if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

//condição verifica se plugin woocommerce está ativo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

//função permite ativação de plugin
add_action('plugins_loaded', 'brazilpays_init', 11);
add_filter('woocommerce_payment_gateways', 'add_to_woo_brazilpays_gateway');

function brazilpays_init()
{
	if (class_exists('WC_Payment_Gateway')) {
        require_once plugin_dir_path( __FILE__ ) . '/includes/class-brazilpays-gateway-credit.php';
        require_once plugin_dir_path( __FILE__ ) . '/includes/class-brazilpays-gateway-pix.php';
        require_once plugin_dir_path( __FILE__ ) . '/includes/brazilpays-description-fields.php';
    }
}

function add_to_woo_brazilpays_gateway($gateways){
   $gateways[] = 'WC_BrazilPays_Gateway_Credit';
   $gateways[] = 'WC_BrazilPays_Gateway_Pix';
   return $gateways;
}