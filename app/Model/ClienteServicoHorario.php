<?php 
class ClienteServicoHorario extends AppModel {
    public $useTable = 'clientes_servicos_horarios';

    public $belongsTo = array(
      'ClienteServico' => array(
        'foreignKey' => 'cliente_servico_id'
      ),
    );
    
    public $validate = array();

    public function lsitaDiasSemana($servico_id = null) {

      $dias_semana = [
        [
          "name" => "Dom",
          "active" => false
        ],
        [
          "name" => "Seg",
          "active" => false
        ],
        [
          "name" => "Ter",
          "active" => false
        ],
        [
          "name" => "Qua",
          "active" => false
        ],
        [
          "name" => "Qui",
          "active" => false
        ],
       	[
          "name" => "Sex",
          "active" => false
        ],
        [
          "name" => "Sáb",
          "active" => false
        ],
      ];

	  $dias_servico = array_values($this->find('list',[
		'fields'=> [
			'ClienteServicoHorario.dia_semana',
			'ClienteServicoHorario.dia_semana'
		],
		'conditions' => [
			'ClienteServicoHorario.cliente_servico_id' => $servico_id
		],
		'group' => [
			'ClienteServicoHorario.dia_semana'
		]
	  ]));

	  foreach( $dias_semana as $key => $dia_semana ){
		if ( in_array($key, $dias_servico) ) {
			$dias_semana[$key]["active"] = true;
		}
	  }

	  return $dias_semana;
        
    }

}
