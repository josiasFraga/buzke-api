<?php 
class Usuario extends AppModel {
    public $useTable = 'usuarios';

    public $name = 'Usuario';

    public $hasMany = array(
		'Token' => array(
			'foreignKey' => 'usuario_id'
		),
		'ClienteCliente' => array(
			'foreignKey' => 'usuario_id'
		),
        'UsuarioResetSenha' => [
			'foreignKey' => 'usuario_id'
        ],
        'UsuarioLocalizacao' => [
			'foreignKey' => 'usuario_id'
        ],
        'UsuarioDadosPadel' => [
			'foreignKey' => 'usuario_id'
        ],
        'UsuarioPadelCategoria' => [
			'foreignKey' => 'usuario_id'
        ],
        'Sugestao' => [
			'foreignKey' => 'usuario_id'
        ],
        'ClienteServicoProfissional' => [
			'foreignKey' => 'usuario_id'
        ]
    );

    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
		),
    );
    
    public $validate = array();

    public $actsAs = array(
		'Upload.Upload' => array(
			'img' => array(
				'path' => "{ROOT}{DS}webroot{DS}img{DS}usuarios", // {ONDE ARQ ESTA}{ENTRA}webroot{ENTRA}img{ENTRA}lotes
				'thumbnailSizes' => array(
                    'thumb' => '512x512',
				),
				'pathMethod' => 'flat',
				'nameCallback' => 'rename',
                'keepFilesOnDelete' => true,
			)
		)
	);

    public function rename($field, $currentName, array $data, array $options) {
        $ext = pathinfo($currentName, PATHINFO_EXTENSION);
        $name = md5(uniqid(rand())).'.'.mb_strtolower($ext);
        return $name;
    }

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['senha']) && $this->data[$this->alias]['senha'] != '') {
            $this->data[$this->alias]['senha'] = AuthComponent::password($this->data[$this->alias]['senha']);
        }
        return true;
    }

    public function listUsuarios() {
        return $this->find('list', array(
            'fields' => array(
                'Usuario.id',
                'Usuario.nome'
            ),
            'conditions' => array(
                'Usuario.ativo' => 'Y',
                'Usuario.nivel !=' => 'admin'
            )
        ));
    }

	public function findIdsOneSingalAdmins() {
		return $this->find('list',array(
			'fields' => array(
				'Usuario.id', 
				'Usuario.notifications_id'
			),
			'conditions' => array(
				'Usuario.usuario_tipo_id' => array(2,3)
			)
		));
	}

	public function getClientDataByPadelistProfile($dados_peril) {

        $conditions = [
            'ClienteCliente.cliente_id' => null,
            'not' => [
                'ClienteCliente.id' => null
            ]
        ];
        if ( $dados_peril->lado != 'I' ) {
            $conditions = array_merge($conditions, [
                'UsuarioDadosPadel.lado' => ['A', $dados_peril->lado]
            ]);
        }
        if ( $dados_peril->sexo != 'I' ) {
            $conditions = array_merge($conditions, [
                'ClienteCliente.sexo' => $dados_peril->sexo
            ]);
        }

        $categorias = [];
        foreach($dados_peril as $key => $perfil) {
            if (strpos($key, 'categoria_') > -1 && $perfil) {
                list($discard, $categoria) = explode('_',$key);
                $categorias[] = $categoria;
            }
        }
        if ( count($categorias) > 0 ) {
            $conditions = array_merge($conditions, [
                'UsuarioPadelCategoria.categoria_id' => $categorias
            ]);
        }

        $jogadores = $this->find('all', [
            'fields' => [
                'ClienteCliente.id',
                'ClienteCliente.usuario_id'
            ],
            'conditions' => $conditions,
            'link' => ['UsuarioDadosPadel', 'ClienteCliente', 'UsuarioPadelCategoria'],
            'group' => ['ClienteCliente.id']
        ]);
        
        return $jogadores;
	}

    

    public function getShedulingConfirmedUsers($agendamento_id = null,$agendamento_horario='') {
        if ( $agendamento_id == null || $agendamento_horario == '' ) {
            return [];
        }
        return $this->find('list',[
            'fields' => [
                'Usuario.id',
                'Usuario.id',
            ],
            'conditions' => [
                'AgendamentoConvite.agendamento_id' => $agendamento_id,
                'AgendamentoConvite.confirmado_usuario' => 'Y',
                'AgendamentoConvite.confirmado_convidado' => 'Y',
                'AgendamentoConvite.horario' => $agendamento_horario,
                'AgendamentoConvite.horario_cancelado' => 'N'
            ],
            'link' => ['ClienteCliente' => ['AgendamentoConvite']],
            'group' => ['Usuario.id']
        ]);

    }

    public function getByEmail($email) {
        return $this->find('first',[
            'fields' => [
                'Usuario.id',
                'Usuario.nome',
                'Usuario.telefone'
            ],
            'conditions' => [
                'Usuario.email' => $email
            ]
        ]);
    }

    public function atualizaTelefone($usuario_id, $ddi, $telefone){
        return $this->save([
            'id' => $usuario_id,
            'telefone_ddi' => $ddi,
            'telefone' => $telefone
        ]);
    }

}