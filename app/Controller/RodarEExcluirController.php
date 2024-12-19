<?php
class RodarEExcluirController extends AppController {

    public $components = array('RequestHandler');

    function migra_horarios() {
        
        $this->layout = 'ajax';
        
        $this->loadModel('ClienteHorarioAtendimento');
        $this->loadModel('ClienteServico');
        $this->loadModel('ClienteServicoHorario');

        $horarios = $this->ClienteHorarioAtendimento->find('all');

        foreach( $horarios as $key => $horario ) {
            $servicos = $this->ClienteServico->find('all',[
                'conditions' => [
                    'ClienteServico.cliente_id' => $horario['ClienteHorarioAtendimento']['cliente_id']
                ],
                'link' => []
            ]);

            foreach ( $servicos as $key_servico => $servico ) {

                $dados_horario_salvar = [
                    'cliente_servico_id' => $servico['ClienteServico']['id'],
                    'inicio' => $horario['ClienteHorarioAtendimento']['abertura'],
                    'fim' => $horario['ClienteHorarioAtendimento']['fechamento'],
                    'dia_semana' => $horario['ClienteHorarioAtendimento']['horario_dia_semana'],
                    'duracao' => $horario['ClienteHorarioAtendimento']['intervalo_horarios'],
                    'a_domicilio' => $horario['ClienteHorarioAtendimento']['a_domicilio'],
                    'apenas_a_domocilio' => 0,
                ];

                $this->ClienteServicoHorario->create();
                $this->ClienteServicoHorario->save($dados_horario_salvar);

            }
        }
    
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Impedimentos cadastrados com sucesso!'))));
    }

    function seta_vencedor() {
        $this->loadModel('TorneioJogo');
        $this->loadModel('TorneioJogoPlacar');
        $jogos = $this->TorneioJogo->find('all',[
            'link' => []
        ]);

        foreach ($jogos as $key => $jogo) {
            $jogo_id = $jogo['TorneioJogo']['id'];
            $vencedor_field = $this->TorneioJogoPlacar->busca_vencedor_por_jogo($jogo_id);

            if ( !empty($vencedor_field) ) {
                $inscricao_vencedora  = $jogo['TorneioJogo'][$vencedor_field];

                $dados_salvar = [
                    'id' => $jogo_id,
                    'vencedor' => $inscricao_vencedora
                ];

                $this->TorneioJogo->save($dados_salvar);

            }

        }

        die('Fim');

    }
}