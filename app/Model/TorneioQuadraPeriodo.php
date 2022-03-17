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

        return $horarios;

	}
}