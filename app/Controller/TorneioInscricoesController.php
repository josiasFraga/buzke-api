<?php
class TorneioInscricoesController extends AppController {

    public $components = array('RequestHandler');

    public function view($id = null) {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dados_token['Usuario']['nivel_id'] != 2 ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('TorneioInscricao');
        $this->loadModel('TorneioInscricaoJogador');
        $this->loadModel('TorneioInscricaoJogadorImpedimento');

        $dados_inscricao = $this->TorneioInscricao->find('first',[
            'fields' => [
                'TorneioInscricao.id',
                'Torneio.nome',
                'Torneio.impedimentos',
                'PadelCategoria.titulo',
                'TorneioCategoria.nome',
                'TorneioGrupo.nome',
                'TorneioCategoria.sexo'
            ],
            'conditions' => [
                'TorneioInscricao.id' => $id,
                'Torneio.cliente_id' => $dados_token['Usuario']['cliente_id']
            ],
            'link' => [
                'Torneio',
                'TorneioCategoria' => ['PadelCategoria'],
                'TorneioGrupo'
            ]
        ]);

        if ( count($dados_inscricao) > 0 ) {
            $inscricao_jogadores = $this->TorneioInscricaoJogador->find('all',[
                'fields' => [
                    'TorneioInscricaoJogador.id',
                    'ClienteCliente.nome',
                    'ClienteCliente.email',
                ],
                'conditions' => [
                    'TorneioInscricaoJogador.torneio_inscricao_id' => $dados_inscricao['TorneioInscricao']['id']
                ],
                'link' => [
                    'ClienteCliente'
                ]
            ]);

            $inscricao_jogadores[0]['_impedimentos'] = $this->TorneioInscricaoJogadorImpedimento->find('all',[
                'conditions' => [
                    'TorneioInscricaoJogadorImpedimento.torneio_inscricao_jogador_id' => $inscricao_jogadores[0]['TorneioInscricaoJogador']['id']
                ],
                'link' => []
            ]);

            $inscricao_jogadores[1]['_impedimentos'] = $this->TorneioInscricaoJogadorImpedimento->find('all',[
                'conditions' => [
                    'TorneioInscricaoJogadorImpedimento.torneio_inscricao_jogador_id' => $inscricao_jogadores[1]['TorneioInscricaoJogador']['id']
                ],
                'link' => []
            ]);

            $dados_inscricao['_times'] = $inscricao_jogadores;
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados_inscricao))));

    }
}