<?php

App::uses('CakeEmail', 'Network/Email');
class SugestoesController extends AppController {

    public function cadastra() {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), false);
        }elseif ( gettype($dados) == 'array' ) {
            $dados = json_decode(json_encode($dados), false);
        }

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->sugestao) || $dados->sugestao == "") {
            throw new BadRequestException('Sugestão não informada!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Sugestao');
        $dados_salvos = $this->Sugestao->save([
            'usuario_id' => $dados_usuario['Usuario']['id'],
            'sugestao' => $dados->sugestao
        ]);

        if ( !$dados_salvos ) {

            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao enviar sua sugestão. Por favor, tente novamente mais tarde. ;('))));

        }

        $Email = new CakeEmail('smtp_aplicativo');
        $Email->from(array('aplicativo@buzke.com.br' => 'Buzke'));
        $Email->emailFormat('html');
        $Email->to('josiasrs2009@gmail.com');
        $Email->template('sugestao');
        $Email->subject('Sugestao - Buzke');
        $Email->viewVars(array('nome_usuario'=>$dados_usuario['Usuario']['nome'], 'sugestao' => $dados->sugestao ));//variable will be replaced from template
        $Email->send();

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Sua sugestão foi cadastrada com sucesso! Agradecemos muito, sua opinião é muito importante pra nós. <3'))));
        

    }
}