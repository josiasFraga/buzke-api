<?php
class WebhooksController extends AppController {

    public $components = array('RequestHandler');

    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");
    }

    public function asaas() {

        $this->layout = 'ajax';
        $data = $this->request->input('json_decode');
        $data = json_decode(json_encode($data), true);

        if ($data['event'] == 'PAYMENT_UPDATED') {

        }
        else if ($data['event'] == 'PAYMENT_CONFIRMED') {

            if ( isset($data['payment']) && isset($data['payment']['subscription'])) {
                if ( !$this->reativaAssinatura($data['payment']['subscription']) ) {
                    $this->log('impssível reativar assinatura.','warning');
                    die();
                }
            }

        }
        else if ($data['event'] == 'PAYMENT_RECEIVED') {

            if ( isset($data['payment']) && isset($data['payment']['subscription'])) {
                if ( !$this->reativaAssinatura($data['payment']['subscription']) ) {
                    $this->log('impssível reativar assinatura.','warning');
                    die();
                }
            }

        }
        else if ($data['event'] == 'PAYMENT_OVERDUE') {

            if ( isset($data['payment']) && isset($data['payment']['subscription'])) {
                if ( !$this->setaAssinaturaAtrasada($data['payment']['subscription']) ) {
                    $this->log('impssível desativar assinatura.','warning');
                    die();
                }
            }

        }
        else if ($data['event'] == 'PAYMENT_DELETED') {

        }
        else if ($data['event'] == 'PAYMENT_RESTORED') {

        }
        else if ($data['event'] == 'PAYMENT_REFUNDED') {

        }
        else if ($data['event'] == 'PAYMENT_RECEIVED_IN_CASH_UNDONE') {

        }
        else if ($data['event'] == 'PAYMENT_CHARGEBACK_REQUESTED') {

        }
        else if ($data['event'] == 'PAYMENT_CHARGEBACK_DISPUTE') {

        }
        else if ($data['event'] == 'PAYMENT_AWAITING_CHARGEBACK_REVERSAL') {

        }
        else if ($data['event'] == 'PAYMENT_DUNNING_RECEIVED') {

        }
        else if ($data['event'] == 'PAYMENT_DUNNING_REQUESTED') {

        }
        else if ($data['event'] == 'PAYMENT_BANK_SLIP_VIEWED') {

        }
        else if ($data['event'] == 'PAYMENT_CHECKOUT_VIEWED') {

        }

        die();
        
    }

    private function reativaAssinatura($assinatura_id = null) {
        if ( $assinatura_id == null ){
            return false;
        }

        $this->loadModel('Cliente');
        $this->loadModel('ClienteAssinatura');

        $dados_cliente = $this->Cliente->findBySinatureId($assinatura_id);
        if ( count($dados_cliente) == 0 ) {
            $this->log('cliente ou assinatura não encontrados.','warning');
            return false;
        }

        if ( $dados_cliente['ClienteAssinatura']['status'] == 'OVERDUE' ) {
            return $this->ClienteAssinatura->reativaAssinatura($dados_cliente['ClienteAssinatura']['id']);
        }

        return true;
    }

    private function setaAssinaturaAtrasada($assinatura_id = null) {
        if ( $assinatura_id == null ){
            return false;
        }

        $this->loadModel('Cliente');
        $this->loadModel('ClienteAssinatura');

        $dados_cliente = $this->Cliente->findBySinatureId($assinatura_id);
        if ( count($dados_cliente) == 0 ) {
            $this->log('cliente ou assinatura não encontrados.','warning');
            return false;
        }

        if ( $dados_cliente['ClienteAssinatura']['status'] == 'ACTIVE' ) {
            return $this->ClienteAssinatura->setaAssinaturaAtrasada($dados_cliente['ClienteAssinatura']['id']);
        }

        return true;
    }
}