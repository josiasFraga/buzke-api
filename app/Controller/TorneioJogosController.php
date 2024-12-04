<?php
class TorneioJogosController extends AppController {

    public $components = array('RequestHandler');

    public function add_seguidor(){
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        //$this->log($dados, 'debug');
        //die();

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), false);
        }elseif ( gettype($dados) == 'array' ) {
            $dados = json_decode(json_encode($dados), false);
        }

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( empty($dados->torneio_jogo_id) && empty($dados->torneio_inscricao_id) ) {
            throw new BadRequestException('Nome não informado!', 400);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dados_usuario['Usuario']['nivel_id'] != 3 ) {
            throw new BadRequestException('Sem permissão de seguir jogos!', 400);
        }

        $this->loadModel('TorneioJogoSeguidor');

        $usuario_id = $dados_usuario['Usuario']['id'];
        $torneio_jogo_id = !empty($dados->torneio_jogo_id) ? $dados->torneio_jogo_id : null;
        $torneio_inscricao_id = !empty($dados->torneio_inscricao_id) ? $dados->torneio_inscricao_id : null;

        $checkIsFollower = $this->TorneioJogoSeguidor->isFollowing($usuario_id, $torneio_jogo_id, $torneio_inscricao_id);

        if ( $checkIsFollower ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Você já estava seguindo.'))));
        }

        $dados_salvar = [
            'usuario_id' => $usuario_id,
            'torneio_jogo_id' => $torneio_jogo_id,
            'torneio_inscricao_id' => $torneio_inscricao_id
        ];

        $this->TorneioJogoSeguidor->create();

        if ( !$this->TorneioJogoSeguidor->save($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao seguir o jogo, por favor, tente mais tarde.'))));
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Tudo certo! Agora você receberá notificações quando o organizador informar o placar.'))));
    }

    public function delete_seguidor(){
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        //$this->log($dados, 'debug');
        //die();

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), false);
        }elseif ( gettype($dados) == 'array' ) {
            $dados = json_decode(json_encode($dados), false);
        }

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( empty($dados->torneio_jogo_id) && empty($dados->torneio_inscricao_id) ) {
            throw new BadRequestException('Nome não informado!', 400);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dados_usuario['Usuario']['nivel_id'] != 3 ) {
            throw new BadRequestException('Sem permissão de seguir jogos!', 400);
        }

        $this->loadModel('TorneioJogoSeguidor');

        $usuario_id = $dados_usuario['Usuario']['id'];
        $torneio_jogo_id = !empty($dados->torneio_jogo_id) ? $dados->torneio_jogo_id : null;
        $torneio_inscricao_id = !empty($dados->torneio_inscricao_id) ? $dados->torneio_inscricao_id : null;

        $checkIsFollower = $this->TorneioJogoSeguidor->isFollowing($usuario_id, $torneio_jogo_id, $torneio_inscricao_id);

        if ( !$checkIsFollower ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Você já não estava seguindo.'))));
        }

        $conditions_deletar = [
            'usuario_id' => $usuario_id,
            'torneio_jogo_id' => $torneio_jogo_id,
            'torneio_inscricao_id' => $torneio_inscricao_id
        ];

        if ( !$this->TorneioJogoSeguidor->deleteAll($conditions_deletar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao deixar de seguir o jogo, por favor, tente mais tarde.'))));
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Tudo certo! Agora você não receberá notificações quando o organizador informar o placar.'))));
    }

}