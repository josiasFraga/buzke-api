<?php
class TorneioCategoria extends AppModel {
	public $useTable = 'torneio_categorias';

	public $belongsTo = array(
		'Torneio' => array(
			'foreignKey' => 'torneio_id'
        ),
		'PadelCategoria' => array(
			'foreignKey' => 'categoria_id'
		)
	);

	public $hasMany = array(
		'TorneioInscricao' => array(
			'foreignKey' => 'torneio_categoria_id'
		)
	);

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['categoria_id']) && $this->data[$this->alias]['categoria_id'] == '0') {
            $this->data[$this->alias]['categoria_id'] = null;
        }
        return true;
    }

    public function getByTournamentId($tournament_id = null){

        if ( $tournament_id == null )
            return [];

        $this->virtualFields['_inscritos'] = 'count(TorneioInscricao.id)';
        
        $categorias =  $this->find('all',[
            'fields' => ['*'],
            'conditions' => [
                'TorneioCategoria.torneio_id' => $tournament_id
            ],
            'order' => [
                'PadelCategoria.titulo', 
                'TorneioCategoria.nome', 
                'TorneioCategoria.sexo'
            ],
            'link' => ['PadelCategoria', 'TorneioInscricao'],
            'group' => [
                'TorneioCategoria.id'
            ]
        ]);

        $categorias_retornar = [];
        if ( count($categorias) > 0 ) {

            foreach( $categorias as $key => $cat) {
                $sexo = 'Masculina';
                if ($cat['TorneioCategoria']['sexo'] == 'F') {
                    $sexo = 'Feminina';
                }
                if ($cat['TorneioCategoria']['sexo'] == 'MI') {
                    $sexo = 'Mista';
                }
                
                $cat['TorneioCategoria']['_categoria_nome'] = $cat['PadelCategoria']['titulo'].$cat['TorneioCategoria']['nome'].' - '.$sexo;
                $cat['TorneioCategoria']['_categoria_id'] = $cat['PadelCategoria']['id'];

                $categorias_retornar[$key] = $cat['TorneioCategoria'];
            }

        }

        return $categorias_retornar;
    }
}