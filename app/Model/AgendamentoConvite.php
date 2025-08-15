<?php 
class AgendamentoConvite extends AppModel {
    public $useTable = 'agendamento_convites';

    public $name = 'AgendamentoConvite';

    public $belongsTo = array(
		'Agendamento' => array(
			'foreignKey' => 'agendamento_id'
        ),
		'Usuario' => array(
			'foreignKey' => 'usuario_id'
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
                'Usuario.nome',
                'AgendamentoConvite._usuario_foto',
                'AgendamentoConvite.id',
                'UsuarioDadosPadel.lado',
            ],
            'conditions' => [
                'AgendamentoConvite.agendamento_id' => $agendamento_id,
                'AgendamentoConvite.confirmado' => 'Y'
            ],
            'link' => ['Usuario' => 'UsuarioDadosPadel'],
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
                'AgendamentoConvite.id',
                'AgendamentoConvite.agendamento_id',
                'AgendamentoConvite._usuario_foto',
                'Usuario.id',
                'Usuario.nome',
            ],
            'conditions' => [
                'AgendamentoConvite.agendamento_id' => $agendamento_id,
                'AgendamentoConvite.confirmado' => 'N',

            ],
            'link' => ['Usuario'],
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
                'AgendamentoConvite.id',
                'AgendamentoConvite.agendamento_id',
                'AgendamentoConvite._usuario_foto',
                'Usuario.id',
                'Usuario.nome',
            ],
            'conditions' => [
                'AgendamentoConvite.agendamento_id' => $agendamento_id,
                'NOT' => [
                    'AgendamentoConvite.confirmado' => 'R',
                ],

            ],
            'link' => ['Usuario'],
            'group' => ['AgendamentoConvite.id']
        ]);

    }
}
