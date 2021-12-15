<?php 
class AgendamentoConvite extends AppModel {
    public $useTable = 'agendamento_convites';

    public $name = 'AgendamentoConvite';

    public $belongsTo = array(
		'Agendamento' => array(
			'foreignKey' => 'agendamento_id'
        ),
		'ClienteCliente' => array(
			'foreignKey' => 'cliente_cliente_id'
        ),
    );

    public function getConfirmedUsers($agendamento_id = null, $photo_path = '') {
        if ( $agendamento_id == null ) {
            return [];
        }
        $this->virtualFields['_usuario_foto'] = 'CONCAT("'.$photo_path .'",Usuario.img)';
        return $this->find('all',[
            'fields' => [
                'ClienteCliente.id',
                'ClienteCliente.nome',
                'AgendamentoConvite._usuario_foto'
            ],
            'conditions' => [
                'AgendamentoConvite.agendamento_id' => $agendamento_id,
                'AgendamentoConvite.confirmado_usuario' => 'Y',
                'AgendamentoConvite.confirmado_convidado' => 'Y',
                //'ClienteCliente.id' => null,

            ],
            'link' => ['ClienteCliente' => ['Usuario']],
            'group' => ['AgendamentoConvite.id']
        ]);

    }
    
}
