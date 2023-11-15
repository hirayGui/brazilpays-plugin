<?php

/*
* Este arquivo permite que campos adicionais sejam apresentados ao usuário de acordo com o método de pagamento selecionado
*/
add_filter('woocommerce_gateway_description', 'brazilpays_description_fields', 20, 2);
add_action('woocommerce_checkout_process', 'brazilpays_description_fields_validation');
add_action('woocommerce_checkout_update_order_meta', 'brazilpays_checkout_update_order_meta', 10, 1);

function brazilpays_description_fields( $description, $payment_id) {
    $total_amount = WC()->cart->get_total( 'edit' );

    /*
     * Apresentando campos para o método de pagamento por Pix 
     */
    if($payment_id === 'brazilpays-pix'){
        ob_start();

        echo '<div style="display: block; width: 100%px !important; height: auto;">';

        echo '<div style="display: flex; width: 100%px !important; height: auto;">';
        woocommerce_form_field('gender', array(
                'type' => 'select',
                'class' => array('form-row', 'form-row-wide'),
                'label' => __('Especifique o gênero: ', 'brazilpays-plugin'),
                'required' => true,
                'options' => array(
                    'none' => __('Selecionar gênero', 'brazilpays-plugin'),
                    'M' => __('Masculino', 'brazilpays-plugin'),
                    'F' => __('Feminino', 'brazilpays-plugin'),
                    'O' => __('Outro', 'brazilpays-plugin')
                ),
            )
        );
        
        woocommerce_form_field('birth_date', array(
                'type' => 'text',
                'class' => array('form-row', 'form-row-wide'),
                'label' => __('Data de Nascimento: ', 'brazilpays-plugin'),
                'placeholder' => __('(dd/mm/yyyy)', 'brazilpays-plugin'),
                'required' => true,
            )
        );

        echo '</div>';
        
        woocommerce_form_field('cpf_cnpj', array(
                'type' => 'text',
                'class' => array('form-row'),
                'label' => __('CPF ou CNPJ: ', 'brazilpays-plugin'),
                'required' => true,
            )
        );
        
        echo '</div>';

        $description .= ob_get_clean();

        return $description;

    /*
     * Apresentando campos para o método de pagamento com Cartão de Crédito
     */    
    } elseif($payment_id === 'brazilpays-credit'){
        ob_start();

        echo '<div style="display: block; width: 100%px !important; height: auto;">';

        echo '<div style="display: flex; width: 100%px !important; height: auto;">';
        woocommerce_form_field('card_gender', array(
                'type' => 'select',
                'class' => array('form-row', 'form-row-wide'),
                'label' => __('Especifique o gênero: ', 'brazilpays-plugin'),
                'required' => true,
                'options' => array(
                    'none' => __('Selecionar gênero', 'brazilpays-plugin'),
                    'M' => __('Masculino', 'brazilpays-plugin'),
                    'F' => __('Feminino', 'brazilpays-plugin'),
                    'O' => __('Outro', 'brazilpays-plugin')
                ),
            )
        );
        
        woocommerce_form_field('card_birth_date', array(
                'type' => 'text',
                'class' => array('form-row', 'form-row-wide'),
                'label' => __('Data de Nascimento: ', 'brazilpays-plugin'),
                'placeholder' => __('(dd/mm/yyyy)', 'brazilpays-plugin'),
                'required' => true,
            )
        );

        echo '</div>';
        
        woocommerce_form_field('card_cpf_cnpj', array(
                'type' => 'text',
                'class' => array('form-row'),
                'label' => __('CPF ou CNPJ: ', 'brazilpays-plugin'),
                'required' => true,
            )
        );

        woocommerce_form_field('card_number', array(
                'type' => 'text',
                'class' => array('form-row'),
                'label' => __('Informe o número do cartão: ', 'brazilpays-plugin'),
                'required' => true,
            )
        );

        woocommerce_form_field('card_name', array(
                'type' => 'text',
                'class' => array('form-row'),
                'label' => __('Informe o nome que está no cartão: ', 'brazilpays-plugin'),
                'required' => true,
            )
        );

        echo '<div style="display: flex; width: 100%px !important; height: auto;">';

        woocommerce_form_field('card_month', array(
                'type' => 'select',
                'class' => array('form-row'),
                'label' => __('Mês de vencimento: ', 'brazilpays-plugin'),
                'required' => true,
                'options' => array(
                    '1' => __('Janeiro', 'brazilpays-plugin'),
                    '2' => __('Fevereiro', 'brazilpays-plugin'),
                    '3' => __('Março', 'brazilpays-plugin'),
                    '4' => __('Abril', 'brazilpays-plugin'),
                    '5' => __('Maio', 'brazilpays-plugin'),
                    '6' => __('Junho', 'brazilpays-plugin'),
                    '7' => __('Julho', 'brazilpays-plugin'),
                    '8' => __('Agosto', 'brazilpays-plugin'),
                    '9' => __('Setembro', 'brazilpays-plugin'),
                    '10' => __('Outubro', 'brazilpays-plugin'),
                    '11' => __('Novembro', 'brazilpays-plugin'),
                    '12' => __('Dezembro', 'brazilpays-plugin'),
                ),
            )
        );

        woocommerce_form_field('card_year', array(
                'type' => 'text',
                'class' => array('form-row'),
                'label' => __('Ano de vencimento: ', 'brazilpays-plugin'),
                'required' => true,
            )
        );

        woocommerce_form_field('card_cvv', array(
                'type' => 'text',
                'class' => array('form-row'),
                'label' => __('CVV: ', 'brazilpays-plugin'),
                'required' => true,
            )
        );

        echo '</div>';

        woocommerce_form_field('card_installments', array(
                'type' => 'select',
                'class' => array('form-row'),
                'label' => __('Número de Parcelas: ', 'brazilpays-plugin'),
                'required' => true,
                'options' => array(
                    '1' => __('1x de U$', 'brazilpays-plugin') . number_format((float)($total_amount), 2, '.', ''),
                    '2' => __('2x de U$', 'brazilpays-plugin') . number_format((float)($total_amount / 2), 2, '.', ''),
                    '3' => __('3x de U$', 'brazilpays-plugin') . number_format((float)($total_amount / 3), 2, '.', ''),
                    '4' => __('4x de U$', 'brazilpays-plugin') . number_format((float)($total_amount / 4), 2, '.', ''),
                    '5' => __('5x de U$', 'brazilpays-plugin') . number_format((float)($total_amount / 5), 2, '.', ''),
                    '6' => __('6x de U$', 'brazilpays-plugin') . number_format((float)($total_amount / 6), 2, '.', ''),
                    '7' => __('7x de U$', 'brazilpays-plugin') . number_format((float)($total_amount / 7), 2, '.', ''),
                    '8' => __('8x de U$', 'brazilpays-plugin') . number_format((float)($total_amount / 8), 2, '.', ''),
                    '9' => __('9x de U$', 'brazilpays-plugin') . number_format((float)($total_amount / 9), 2, '.', ''),
                    '10' => __('10x de U$', 'brazilpays-plugin') . number_format((float)($total_amount / 10), 2, '.', ''),
                    '11' => __('11x de U$', 'brazilpays-plugin') . number_format((float)($total_amount / 11), 2, '.', ''),
                    '12' => __('12x de U$', 'brazilpays-plugin') . number_format((float)($total_amount / 12), 2, '.', ''),
                ),
            )
        );
        
        echo '</div>';

        $description .= ob_get_clean();

        return $description;

    }

    return $description;
}

function brazilpays_description_fields_validation(){
    if($_POST['payment_method'] === 'brazilpays-pix'){
        if(!isset($_POST['gender'])){
            wc_add_notice('Por favor informe um gênero!', 'error');
        }

        if(!isset($_POST['birth_date']) || empty($_POST['birth_date'])){
            wc_add_notice('Por favor informe uma data de nascimento!', 'error');
        }

        if(!isset($_POST['cpf_cnpj']) || empty($_POST['cpf_cnpj'])){
            wc_add_notice('Por favor informe um CPF ou CNPJ válido!', 'error');
        }
    }


    if($_POST['payment_method'] === 'brazilpays-credit'){
        if(!isset($_POST['card_gender'])){
            wc_add_notice('Por favor informe um gênero!', 'error');
        }

        if(!isset($_POST['card_birth_date']) || empty($_POST['birth_date'])){
            wc_add_notice('Por favor informe uma data de nascimento!', 'error');
        }

        if(!isset($_POST['card_cpf_cnpj']) || empty($_POST['cpf_cnpj'])){
            wc_add_notice('Por favor informe um CPF ou CNPJ válido!', 'error');
        }

        if(!isset($_POST['card_number']) || empty($_POST['cpf_cnpj'])){
            wc_add_notice('Por favor informe o número do cartão!', 'error');
        }

        if(!isset($_POST['card_name']) || empty($_POST['cpf_cnpj'])){
            wc_add_notice('Por favor informe o nome escrito no cartão!', 'error');
        }

        if(!isset($_POST['card_month']) || empty($_POST['cpf_cnpj'])){
            wc_add_notice('Por favor informe o mês de vencimento do cartão!', 'error');
        }

        if(!isset($_POST['card_year']) || empty($_POST['cpf_cnpj'])){
            wc_add_notice('Por favor informe o ano de vencimento do cartão!', 'error');
        }

        if(!isset($_POST['card_cvv']) || empty($_POST['cpf_cnpj'])){
            wc_add_notice('Por favor informe o CVV do cartão!', 'error');
        }
    }
   
}

function brazilpays_checkout_update_order_meta($order_id){
    if(isset($_POST['gender']) || !empty($_POST['gender'])){
        if(isset($_POST['birth_date']) || !empty($_POST['birth_date'])){
            if(isset($_POST['cpf_cnpj']) || !empty($_POST['cpf_cnpj'])){
                update_post_meta($order_id, 'gender', $_POST['gender']);
                update_post_meta($order_id, 'birth_date', $_POST['birth_date']);
                update_post_meta($order_id, 'cpf_cnpj', $_POST['cpf_cnpj']);
            }
        }
    }

    if(isset($_POST['card_gender']) || !empty($_POST['card_gender'])){
        if(isset($_POST['card_birth_date']) || !empty($_POST['card_birth_date'])){
            if(isset($_POST['card_cpf_cnpj']) || !empty($_POST['card_cpf_cnpj'])){
                update_post_meta($order_id, 'card_gender', $_POST['card_gender']);
                update_post_meta($order_id, 'card_birth_date', $_POST['card_birth_date']);
                update_post_meta($order_id, 'card_cpf_cnpj', $_POST['card_cpf_cnpj']);
            }
        }
    }
}