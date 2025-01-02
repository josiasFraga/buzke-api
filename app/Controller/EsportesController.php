<?php
class EsportesController extends AppController {

    public $components = array('RequestHandler');

    public function lista_com_perfil_gerenciavel() {

        $dados = $this->request->query;
        
        if ( empty($dados['token']) ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
    
        $token = $dados['token'];
        $email = null;

        if ( !empty($dados['email']) ) {
            $email = $dados['email'];
        }

        $dados_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }


        $this->loadModel('Subcategoria');
        $esportes = $this->Subcategoria->find('all', [
            'conditions' => [
                'NOT' => [
                    'Subcategoria.esporte_nome' => null,
                    'Subcategoria.cena_criacao_perfil' => null
                ]
            ],
            'link' => []
        ]);

        $check_is_padelist = false;

        if ( !empty($email) ) {
            $this->loadModel('UsuarioDadosPadel');
            $check_is_padelist = $this->UsuarioDadosPadel->checkIsAthlete($dados_usuario['Usuario']['id']);
        }

        $esportes_retornar = array_map(function($esporte) use($check_is_padelist) {

            if ( $check_is_padelist && $esporte['Subcategoria']['id'] == 7 ) {
                $esporte['Subcategoria']['have_profile'] = true;
            } else {
                $esporte['Subcategoria']['have_profile'] = false;
            }
    
            return $esporte['Subcategoria'];
        },$esportes);

        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $esportes_retornar))));
    }
}