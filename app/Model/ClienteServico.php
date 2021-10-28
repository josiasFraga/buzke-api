<?php 
class ClienteServico extends AppModel {
    public $useTable = 'clientes_servicos';

    public $name = 'ClienteServico';

    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
		),
    );

    public $hasMany = array(
		'Agendamento' => array(
			'foreignKey' => 'servico_id'
		),
    );
    
    public $validate = array();

    public function modaArrayServicosIndisponiveis($horarios = [], $cliente_id = null) {


        if ( count($horarios) == 0 )
            return [];
        
        if ( $cliente_id == null )
            return [];

        foreach( $horarios as $key => $horario ) {
            $horarios[$key]['servicos_desativar'] = [];
            if (isset($horario['agendamentos_marcados']) && count($horario['agendamentos_marcados']) > 0){
                foreach($horario['agendamentos_marcados'] as $key_agendamento => $agendamento) {
                    $horarios[$key]['servicos_desativar'][] = $agendamento['ClienteServico']['id'];

                }
            }
            unset($horarios[$key]['agendamentos_marcados']);

        }

        return $horarios;
         
    }

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['valor']) && $this->data[$this->alias]['valor'] != '') {
            $this->data[$this->alias]['valor'] = $this->currencyToFloat($this->data[$this->alias]['valor']);
        }
        return true;
    }





}
