<?php
class TorneioImpedimentosController extends AppController {

    public $components = array('RequestHandler');

    function add() {
        
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), false);
        }elseif ( gettype($dados) == 'array' ) {
            $dados = json_decode(json_encode($dados), false);
        }

        if (!isset($dados->email) || $dados->email == '') {
            throw new BadRequestException('E-mail não informado', 400);
        }

        if ( !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'E-mail inválido!'))));
        }

        if (!isset($dados->token) || $dados->token == '') {
            throw new BadRequestException('Token não informado', 400);
        }

        $dados_token = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('TorneioInscricao');
        $this->loadModel('TorneioInscricaoJogador');
        $this->loadModel('TorneioInscricaoJogadorImpedimento');

        $dados_inscricao = $this->TorneioInscricao->find('first',[
            'fields' => [
                'TorneioInscricao.id',
                'Torneio.id',
                'Torneio.nome',
                'Torneio.impedimentos',
                'PadelCategoria.titulo',
                'TorneioCategoria.nome',
                'TorneioGrupo.nome',
                'TorneioCategoria.sexo'
            ],
            'conditions' => [
                'TorneioInscricao.id' => $dados->inscricao_id,
                'Torneio.cliente_id' => $dados_token['Usuario']['cliente_id']
            ],
            'link' => [
                'Torneio',
                'TorneioCategoria' => ['PadelCategoria'],
                'TorneioGrupo'
            ]
        ]);

        if ( count($dados_inscricao) === 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados da inscrição não encontrados!'))));
        }

        $jogadores = $this->TorneioInscricaoJogador->find('all', [
            'conditions' => [
                'TorneioInscricaoJogador.torneio_inscricao_id' => $dados->inscricao_id
            ],
            'link' => []
        ]);

        $impedimentos_jogador_1 = [];
        if ( isset($dados->impedimentos_jogador_1) && count($dados->impedimentos_jogador_1) > 0 ) {
            foreach( $dados->impedimentos_jogador_1 as $key => $impedimento ){
    
                if ( !isset($impedimento->data) || $impedimento->data == ""  ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Data do impedimento não informado'))));
                }
                if ( !isset($impedimento->das) || $impedimento->das == ""  ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Hora início do impedimento não informado'))));
                }
                if ( !isset($impedimento->ate_as) || $impedimento->ate_as == ""  ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Hora limite do impedimento não informado'))));
                }
                $impedimentos_jogador_1[$key]['torneio_inscricao_jogador_id'] = $jogadores[0]['TorneioInscricaoJogador']['id'];
                $impedimentos_jogador_1[$key]['inicio'] = $this->dateBrEn($impedimento->data).' '.$impedimento->das;
                $impedimentos_jogador_1[$key]['fim'] = $this->dateBrEn($impedimento->data).' '.$impedimento->ate_as;
    
            }
        }
    
        $impedimentos_jogador_2 = [];
        if ( isset($dados->impedimentos_jogador_2) && count($dados->impedimentos_jogador_2) > 0 ) {
            foreach( $dados->impedimentos_jogador_2 as $key => $impedimento ){
    
                if ( !isset($impedimento->data) || $impedimento->data == ""  ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Data do impedimento não informado'))));
                }
                if ( !isset($impedimento->das) || $impedimento->das == ""  ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Hora início do impedimento não informado'))));
                }
                if ( !isset($impedimento->ate_as) || $impedimento->ate_as == ""  ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Hora limite do impedimento não informado'))));
                }
                $impedimentos_jogador_2[$key]['torneio_inscricao_jogador_id'] = $jogadores[1]['TorneioInscricaoJogador']['id'];
                $impedimentos_jogador_2[$key]['inicio'] = $this->dateBrEn($impedimento->data).' '.$impedimento->das;
                $impedimentos_jogador_2[$key]['fim'] = $this->dateBrEn($impedimento->data).' '.$impedimento->ate_as;
    
            }
        }

        $n_impedimentos_jogador_1 = $this->TorneioInscricaoJogadorImpedimento->countByPlayerOtherSubscriptions($jogadores[0]['TorneioInscricaoJogador']['cliente_cliente_id'], $dados_inscricao['Torneio']['id'], $dados->inscricao_id);
        $n_impedimentos_jogador_2 = $this->TorneioInscricaoJogadorImpedimento->countByPlayerOtherSubscriptions($jogadores[1]['TorneioInscricaoJogador']['cliente_cliente_id'], $dados_inscricao['Torneio']['id'], $dados->inscricao_id);

        if ( (count($dados->impedimentos_jogador_1) + $n_impedimentos_jogador_1) > $dados_inscricao['Torneio']['impedimentos']) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O Jogador 1 ultrapassou o limite de impedimentos para esse torneio'))));
        }

        if ( (count($dados->impedimentos_jogador_2) + $n_impedimentos_jogador_2) > $dados_inscricao['Torneio']['impedimentos']) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O Jogador 2 ultrapassou o limite de impedimentos para esse torneio'))));
        }

        $this->TorneioInscricaoJogadorImpedimento->deleteAll(['torneio_inscricao_jogador_id' => [$jogadores[0]['TorneioInscricaoJogador']['id'],$jogadores[1]['TorneioInscricaoJogador']['id']]]);


        if ( count($impedimentos_jogador_1) > 0 ) {
            if ( !$this->TorneioInscricaoJogadorImpedimento->saveAll($impedimentos_jogador_1) ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar os impedimentos do jogador 1'))));
            }
        }
    
        if ( count($impedimentos_jogador_2) > 0 ) {
            if ( !$this->TorneioInscricaoJogadorImpedimento->saveAll($impedimentos_jogador_2) ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar os impedimentos do jogador 2'))));
            }
        }
    
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Impedimentos cadastrados com sucesso!'))));
    }
}