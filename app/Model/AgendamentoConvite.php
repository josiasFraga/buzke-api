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

    public function getConfirmedUsers($agendamento_id = null, $photo_path = '',$agendamento_horario='') {
        if ( $agendamento_id == null ) {
            return [];
        }
        $this->virtualFields['_usuario_foto'] = 'CONCAT("'.$photo_path .'",Usuario.img)';
        return $this->find('all',[
            'fields' => [
                'Usuario.id',
                'ClienteCliente.id',
                'ClienteCliente.nome',
                'AgendamentoConvite._usuario_foto',
                'AgendamentoConvite.id',
                'UsuarioDadosPadel.lado',
            ],
            'conditions' => [
                'AgendamentoConvite.agendamento_id' => $agendamento_id,
                'AgendamentoConvite.confirmado_usuario' => 'Y',
                'AgendamentoConvite.confirmado_convidado' => 'Y',
                'AgendamentoConvite.horario' => $agendamento_horario,
                'AgendamentoConvite.horario_cancelado' => 'N'
                //'ClienteCliente.id' => null,

            ],
            'link' => ['ClienteCliente' => ['Usuario' => 'UsuarioDadosPadel']],
            'group' => ['AgendamentoConvite.id']
        ]);

    }
    
    public function getUnconfirmedUsers($agendamento_id = null, $photo_path = '',$agendamento_horario='') {
        if ( $agendamento_id == null ) {
            return [];
        }
        $this->virtualFields['_usuario_foto'] = 'CONCAT("'.$photo_path .'",Usuario.img)';
        return $this->find('all',[
            'fields' => [
                'ClienteCliente.id',
                'ClienteCliente.nome',
                'AgendamentoConvite.id',
                'AgendamentoConvite.agendamento_id',
                'AgendamentoConvite._usuario_foto'
            ],
            'conditions' => [
                'AgendamentoConvite.agendamento_id' => $agendamento_id,
                'or' => [
                    'AgendamentoConvite.confirmado_usuario' => 'N',
                    'AgendamentoConvite.confirmado_convidado' => 'N',
                ],
                'AgendamentoConvite.horario' => $agendamento_horario,
                'AgendamentoConvite.horario_cancelado' => 'N'
                //'ClienteCliente.id' => null,

            ],
            'link' => ['ClienteCliente' => ['Usuario']],
            'group' => ['AgendamentoConvite.id']
        ]);

    }
    
    public function getNotRecusedUsers($agendamento_id = null, $photo_path = '',$agendamento_horario='') {
        if ( $agendamento_id == null ) {
            return [];
        }
        $this->virtualFields['_usuario_foto'] = 'CONCAT("'.$photo_path .'",Usuario.img)';
        return $this->find('all',[
            'fields' => [
                'ClienteCliente.id',
                'ClienteCliente.nome',
                'AgendamentoConvite.id',
                'AgendamentoConvite.agendamento_id',
                'AgendamentoConvite._usuario_foto'
            ],
            'conditions' => [
                'AgendamentoConvite.agendamento_id' => $agendamento_id,
                'AgendamentoConvite.confirmado_usuario' => ['N', 'Y'],
                'AgendamentoConvite.confirmado_convidado' => ['N', 'Y'],
                'AgendamentoConvite.horario' => $agendamento_horario,
                'AgendamentoConvite.horario_cancelado' => 'N'
                //'ClienteCliente.id' => null,

            ],
            'link' => ['ClienteCliente' => ['Usuario']],
            'group' => ['AgendamentoConvite.id']
        ]);

    }
}
