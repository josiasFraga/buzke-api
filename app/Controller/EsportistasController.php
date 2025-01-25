<?php
class EsportistasController extends AppController {

    public $components = array('RequestHandler');

    public function dupla_fixa_acao() {
        $this->layout = 'ajax';
        
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        //$this->log($dados, 'debug');
        //die();

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));

        }else {
            $dados = json_decode($dados);
        }

        if (!isset($dados->email) || $dados->email == '') {
            throw new BadRequestException('E-mail não informado', 400);
        }

        if ( !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'E-mail inválido!'))));
        }

        if (!isset($dados->tipo) || $dados->tipo == '') {
            throw new BadRequestException('Tipo do convite não informado', 400);
        }

        if (!isset($dados->token) || $dados->token == '') {
            throw new BadRequestException('Token não informado', 400);
        }

        if ( empty($dados->convidante_id) ) {
            throw new BadRequestException('Token não informado', 400);
        }

        if ( empty($dados->action) || !in_array($dados->action, [1,2]) ) {//1 confirmar = Y, 2 recusar = R
            throw new BadRequestException('Ação não informada', 400);
        }

        $dados_token = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $acao = $dados->action;
        $tipo = $dados->tipo;
        $conviante_id = $dados->convidante_id;
        $usuario_id = $dados_token['Usuario']['id'];

        if ( $tipo === 'padel' ) {

            $this->loadModel('UsuarioDadosPadel');
            $this->loadModel('Notificacao');
            $this->loadModel('Token');

            // Esses são os dados de padelista do usuário que adicionou este 
            $dados_padel_usuario_convidante = $this->UsuarioDadosPadel->find('first', [
                'fields' => [
                    'UsuarioDadosPadel.id',
                    'UsuarioDadosPadel.dupla_fixa'
                ],
                'conditions' => [
                    'usuario_id' => $conviante_id
                ],
                'link' => []
            ]);
    
            if ( empty($dados_padel_usuario_convidante) ) {
                throw new BadRequestException('Dados de padelista não encontrados', 400);
            }

            $notificacoes_atualizar = array_values($this->Notificacao->find('list',[
                'fields' => [
                    'Notificacao.id',
                    'Notificacao.id'
                ],
                'conditions' => [
                    'Notificacao.acao_selecionada' => null,
                    'Notificacao.usuario_origem' => $conviante_id,
                    'NotificacaoUsuario.usuario_id' => $usuario_id,
                    'Notificacao.registro_id' => $dados_padel_usuario_convidante['UsuarioDadosPadel']['id'],
                    'NotificacaoMotivo.nome' => 'dupla_fixa_padel',
                ],
                'link' => [
                    'NotificacaoUsuario',
                    'NotificacaoMotivo'
                ]
            ]));

            $enviar_notificacao_convidante = false;

            // Se o usuário convidante já setou outro usuário como dupla fixa e o usuario está recusando
            if ( $dados_padel_usuario_convidante['UsuarioDadosPadel']['dupla_fixa'] !== $usuario_id && $acao == 2 ) {
                $this->Notificacao->updateAll([
                    'acao_selecionada' => "'N'",
                    'acao_selecionada_desc' => "'Recusado'"
                ], [
                    'Notificacao.id' => $notificacoes_atualizar
                ]);
            }
            // Se o usuário convidante já setou outro usuário como dupla fixa e o usuario está aceitando
            else if ( $dados_padel_usuario_convidante['UsuarioDadosPadel']['dupla_fixa'] !== $usuario_id && $acao == 1 ) {
                $this->Notificacao->updateAll([
                    'acao_selecionada' => "'N'",
                    'acao_selecionada_desc' => "'Expirada'"
                ], [
                    'Notificacao.id' => $notificacoes_atualizar
                ]);
            }
            // Se o usuário aceitou ser dupla fixa
            else if ($acao == 1) {
                $this->Notificacao->updateAll([
                    'acao_selecionada' => "'Y'",
                    'acao_selecionada_desc' => "'Confirmado'"
                ], [
                    'Notificacao.id' => $notificacoes_atualizar
                ]);

                $dados_atualizar = [
                    'id' => $dados_padel_usuario_convidante['UsuarioDadosPadel']['id'],
                    'dupla_fixa_aprovado' => "Y"
                ];
    
                if ( !$this->UsuarioDadosPadel->save($dados_atualizar) ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar a ação!'))));
                }

                $enviar_notificacao_convidante = true;
            }
            // Se o usuário recusou ser dupla fixa
            else if ($acao == 2) {
                $this->Notificacao->updateAll([
                    'acao_selecionada' => "'N'",
                    'acao_selecionada_desc' => "'Recusado'"
                ], [
                    'Notificacao.id' => $notificacoes_atualizar
                ]);

                $dados_atualizar = [
                    'id' => $dados_padel_usuario_convidante['UsuarioDadosPadel']['id'],
                    'dupla_fixa_aprovado' => "Y"
                ];
    
                if ( !$this->UsuarioDadosPadel->save($dados_atualizar) ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar a ação!'))));
                }

                $enviar_notificacao_convidante = true;
            }

            if ( $enviar_notificacao_convidante ) {
    
                // Envia notificação pro usuário que convidou para dupla fixa
                $notifications_ids = $this->Token->getIdsNotificationsUsuario($conviante_id);

                $this->sendNotificationNew( 
                    $conviante_id,
                    $notifications_ids, 
                    $dados_padel_usuario_convidante['UsuarioDadosPadel']['id'],
                    null,
                    'dupla_fixa_padel_resposta',
                    ["en"=> '$[notif_count] Resposta de adição como dupla fixa']
                );
            }
    
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Ação registrada com sucesso!'))));

    }


}