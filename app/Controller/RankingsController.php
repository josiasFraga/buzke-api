<?php
class RankingsController extends AppController
{
    public $components = array('RequestHandler');
    public $uses = array('EstatisticaPadel');


    public function index() {
        // Desabilita a renderização automática e define o tipo de resposta como JSON
        $this->autoRender = false;
        $this->response->type('json');

        // Obter os parâmetros de filtro opcionais
        $categoria = $this->request->query('categoria');;

        // Construir as condições de busca
        $conditions = [];

        if ( !empty($categoria) ) {
            list($categoriaId, $sexo) = explode('_', $categoria);
            $conditions['EstatisticaPadel.categoria_id'] = $categoriaId;
            $conditions['EstatisticaPadel.sexo'] = $sexo;
        }

        // Determinar se devemos agrupar apenas por usuário ou também por categoria e sexo
        $group = ['EstatisticaPadel.usuario_id'];
        if ( !empty($categoria) ) {
            $group[] = 'EstatisticaPadel.categoria_id';
            $group[] = 'EstatisticaPadel.sexo';
        }

        // Definir os campos a serem selecionados
        $fields = [
            'EstatisticaPadel.usuario_id',
            'Usuario.nome',
            'Usuario.usuario',
            'Usuario.img',
            'UsuarioDadosPadel.img',
            'SUM(EstatisticaPadel.vitorias) AS vitorias_jogos',
            'SUM(EstatisticaPadel.torneio_jogos) AS torneio_jogos',
            'SUM(EstatisticaPadel.torneios_participados) AS torneios_participados',
            'SUM(EstatisticaPadel.torneios_vencidos) AS torneios_vencidos',
            'SUM(EstatisticaPadel.finais_perdidas) AS finais_perdidas',
            'SUM(EstatisticaPadel.avancos_de_fase) AS avancos_de_fase',
            'SUM(EstatisticaPadel.pontuacao_total) AS pontuacao_total'
        ];

        // Opções para a consulta
        $options = [
            'conditions' => $conditions,
            'fields' => $fields,
            'group' => $group,
            'order' => ['pontuacao_total DESC'],
            'limit' => 20,
            'link' => ['Usuario' => ['UsuarioDadosPadel']] // Inclui dados do modelo Usuario
        ];

        try {
            // Buscar os dados do ranking a partir da tabela estatisticas_padel
            $results = $this->EstatisticaPadel->find('all', $options);

            // Formatar os resultados para o JSON de resposta
            $ranking = [];

            foreach ($results as $row) {
                $ranking[] = [
                    'id' => $row['EstatisticaPadel']['usuario_id'],
                    'nome' => isset($row['Usuario']['nome']) ? $row['Usuario']['nome'] : 'N/A',
                    'usuario' => isset($row['Usuario']['usuario']) ? $row['Usuario']['usuario'] : 'N/A',
                    'img' => !empty($row['UsuarioDadosPadel']['img']) ? $row['UsuarioDadosPadel']['img'] : $row['Usuario']['img'],
                    'categoria_id' => isset($row['EstatisticaPadel']['categoria_id']) ? (int)$row['EstatisticaPadel']['categoria_id'] : null,
                    'sexo' => isset($row['EstatisticaPadel']['sexo']) ? $row['EstatisticaPadel']['sexo'] : '',
                    'vitorias_jogos' => (int)$row[0]['vitorias_jogos'],
                    'torneio_jogos' => (int)$row[0]['torneio_jogos'],
                    'torneios_participados' => (int)$row[0]['torneios_participados'],
                    'torneios_vencidos' => (int)$row[0]['torneios_vencidos'],
                    'finais_perdidas' => (int)$row[0]['finais_perdidas'],
                    'avancos_de_fase' => (int)$row[0]['avancos_de_fase'],
                    'pontuacao_total' => (int)$row[0]['pontuacao_total']
                ];
            }

            // Preparar o array de resposta
            $response = [
                'status' => 'ok',
                'dados' => $ranking
            ];

        } catch (Exception $e) {
            // Em caso de erro, retornar uma resposta de erro
            $response = [
                'status' => 'error',
                'mensagem' => 'Ocorreu um erro ao buscar o ranking.',
                'detalhes' => $e->getMessage()
            ];

            // Log do erro para análise futura
            CakeLog::write('error', 'Erro no endpoint de ranking: ' . $e->getMessage());
        }

        // Retornar a resposta em JSON
        return $this->response->body(json_encode($response));
    }
}
