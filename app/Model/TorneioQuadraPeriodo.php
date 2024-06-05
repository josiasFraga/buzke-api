<?php
class TorneioQuadraPeriodo extends AppModel {
	public $useTable = 'torneio_quadra_periodo';

	public $belongsTo = array(
		'TorneioQuadra' => array(
			'foreignKey' => 'torneio_quadra_id'
        ),
	);

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['inicio']) && $this->data[$this->alias]['inicio'] != '') {
            $this->data[$this->alias]['inicio'] = $this->datetimeBrEn($this->data[$this->alias]['inicio']).":00";
        }
        if ( isset($this->data[$this->alias]['fim']) && $this->data[$this->alias]['fim'] != '') {
            $this->data[$this->alias]['fim'] = $this->datetimeBrEn($this->data[$this->alias]['fim']).":00";
        }
        return true;
    }

	public function getTimeList($torneio_id = null, $torneio_quadra_id = null, $data = null) {

        $conditions = [];

        if ( $torneio_id != null ) {
            $conditions = array_merge($conditions,[
                'TorneioQuadra.torneio_id' => $torneio_id
            ]);
        }

        if ( $torneio_quadra_id != null ) {
            $conditions = array_merge($conditions,[
                'TorneioQuadra.id' => $torneio_quadra_id
            ]);
        }

        if ( $data != null ) {
            $conditions = array_merge($conditions,[
                'DATE(TorneioQuadraPeriodo.inicio)' => $data
            ]);
        }

		$periodos = $this->find('all',[
            'conditions' => $conditions,
            'link' => ['TorneioQuadra'],
            'order' => ['TorneioQuadraPeriodo.inicio']
        ]);

        if ( count($periodos) == 0 ) {
            return [];
        }

        $horarios = [];

        foreach($periodos as $key => $periodo) {

            $inicio = $horario = $periodo['TorneioQuadraPeriodo']['inicio'];
            $fim = $periodo['TorneioQuadraPeriodo']['fim'];

            list($hours,$minutes) = explode(':',$periodo['TorneioQuadraPeriodo']['duracao_jogos']);

            for (
                $horario;
                $horario <= $fim;
                $horario = date('Y-m-d H:i:s',strtotime('+'.$hours.' hour +'.$minutes.' minutes',strtotime($horario))) 
            ) {

                $horarios[] = [
                    'torneio_quadra_id' => $periodo['TorneioQuadraPeriodo']['torneio_quadra_id'],
                    'duracao' => $periodo['TorneioQuadraPeriodo']['duracao_jogos'],
                    'horario' => $horario
                ];

            }

        }

        uasort($horarios, function ($a, $b) {
            $horarioA = strtotime($a['horario']);
            $horarioB = strtotime($b['horario']);
            
            if ($horarioA == $horarioB) {
                return 0;
            }
            
            return ($horarioA < $horarioB) ? -1 : 1;
        });
        return $horarios;

	}


    public function verificaJogo($quadra = null, $data = null, $hora = null, $duracao = null){
        if ( $quadra == null || $data == null || $hora == null || $duracao == null )
            return false;

        list($horas,$minutos,$segundos) = explode(':',$duracao);
        $inicio_agendamento = $data." ".$hora;
        $fim_agendamento = date('Y-m-d H:i:s' , strtotime($inicio_agendamento . ' + ' . $horas . ' hours'));
        $fim_agendamento = date('Y-m-d H:i:s', strtotime($fim_agendamento . ' + ' . $minutos . ' minutes'));
        
        $verifica =  $this->find('count',[
                'fields' => ['*'],
                'conditions' => [
                    'TorneioQuadra.servico_id' => $quadra,
                    'or' => [
                        [
                            'TorneioQuadraPeriodo.inicio <=' => $inicio_agendamento,
                            'TorneioQuadraPeriodo.fim >' => $inicio_agendamento,
                        ],[
                            'TorneioQuadraPeriodo.inicio <' => $fim_agendamento,
                            'TorneioQuadraPeriodo.fim >' => $fim_agendamento,
                        ]
                    ]
                ],
                'link' => ['TorneioQuadra']
        ]) > 0;
        
        return $verifica;
    }

    public function verificaReservaTorneio ($servico_id = null, $data = null, $hora = null) {

        if ( $servico_id == null || $data == null || $hora == null ) {
            return false;
        }

        $conditions = [
            'TorneioQuadra.servico_id' => $servico_id,
            'TorneioQuadraPeriodo.inicio <=' => $data.' '.$hora,
            'TorneioQuadraPeriodo.fim >=' => $data.' '.$hora
        ];

        return $this->find('all', [
            'conditions' => $conditions,
            'link' => [
                'TorneioQuadra'
            ]
        ]);

    }
}