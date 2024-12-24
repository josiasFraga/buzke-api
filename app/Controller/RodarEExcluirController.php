<?php
class RodarEExcluirController extends AppController {

    public $components = array('RequestHandler');

    private $s3Client;
    private $s3Bucket = 'buzke-images'; // Nome do seu bucket S3

    // Método para inicializar o controlador (usando beforeFilter no CakePHP 2.x)
    public function beforeFilter() {
        parent::beforeFilter();

        // Configuração do cliente S3
        $this->loadS3Client();
    }

    // Função para carregar o cliente S3
    private function loadS3Client() {
        // Verifique se as credenciais estão corretas
        if (empty(getenv('AWS_ACCESS_KEY')) || empty(getenv('AWS_SECRET_KEY'))) {
            throw new Exception('Credenciais da AWS não encontradas.');
        }

        // Inicializar o cliente S3
        $this->s3Client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region'  => 'sa-east-1',
            'credentials' => [
                'key'    => getenv('AWS_ACCESS_KEY'),
                'secret' => getenv('AWS_SECRET_KEY'),
            ],
            'suppress_php_deprecation_warning' => true,
            'http' => [
                'verify' => false,
            ]
        ]);
    }

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

    function envia_imagens_pro_bucket($tipo = 'user') {

        if ($tipo == 'user') {
            $this->loadModel('Usuario');
            $diretorio_bucket = 'users';
            $usuarios = $this->Usuario->find('all', [
                'fields' => [
                    'Usuario.img'
                ],
                'conditions' => [
                    'not' => [
                        'Usuario.img' => 'thumb_user.png'
                    ]
                ]
            ]);

            foreach ($usuarios as $usuario) {
                $imagem = $usuario['Usuario']['img'];

                // Verificar se a imagem existe na pasta antes de enviar para o S3
                $filePath = WWW_ROOT . 'img' . DS . 'usuarios' . DS . $imagem;

                // Verifica se a imagem existe na pasta
                if (file_exists($filePath)) {

                    // Verificar se a imagem já existe no bucket
                    if ($this->checkIfFileExists($imagem, $diretorio_bucket)) {
                        echo "A imagem {$imagem} já existe no bucket S3.\n";

                        // Verifique se o nome da imagem contém o diretório correto
                        if (strpos($imagem, $diretorio_bucket.'/') === 0) {
                            $this->Usuario->id = $usuario['Usuario']['id'];
                            $this->Usuario->save(['img' => "https://buzke-images.s3.sa-east-1.amazonaws.com/{$diretorio_bucket}/" . $imagem]);
                        }
                        continue; // Se a imagem já existe, pula para a próxima
                    }

                    // Enviar a imagem para o bucket S3 e obter a URL
                    $imageUrl = $this->uploadImageToS3($filePath, $diretorio_bucket, $imagem);
                    echo "Imagem {$imagem} enviada para o S3.\n";

                    // Atualizar o nome da imagem no banco com a URL do S3
                    if ($imageUrl) {
                        $this->Usuario->id = $usuario['Usuario']['id'];
                        $this->Usuario->save(['img' => $imageUrl]);
                        echo "A URL da imagem foi atualizada no banco de dados.\n";
                    }
                } else {
                    echo "A imagem {$imagem} não foi encontrada na aplicação.\n";
                }
            }
        } 

        // Definir o tipo de resposta como JSON (se necessário)
        $this->set([
            'status' => 'ok',
            'msg' => 'Imagens enviadas com sucesso.',
            '_serialize' => ['status', 'msg']
        ]);
    }

    // Função para verificar se o arquivo existe no S3
    // Função para verificar se o arquivo existe no S3
    private function checkIfFileExists($fileName, $directory) {
        try {
            // Tenta obter o objeto usando a chave (nome do arquivo)
            if ($this->s3Client) {
                $this->s3Client->headObject([
                    'Bucket' => $this->s3Bucket,
                    'Key'    => $directory. '/' .$fileName,
                ]);
                // Se não lançar erro, significa que o arquivo existe
                return true;
            } else {
                throw new Exception("Cliente S3 não está inicializado.");
            }
        } catch (Aws\S3\Exception\S3Exception $e) {
            // Se o erro for NoSuchKey, significa que o arquivo não existe
            if ($e->getAwsErrorCode() === 'NoSuchKey' || $e->getAwsErrorCode() === 'NotFound') {
                return false;
            }
            // Para outros erros, lançar novamente
            throw $e;
        }
    }

    // Função para enviar a imagem para o S3
    private function uploadImageToS3($filePath, $directory, $fileName) {
        try {
            $mimeType = mime_content_type($filePath); // Obtém o tipo MIME da imagem

            // Enviar para o bucket S3
            $result = $this->s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $directory . '/' . $fileName, // Caminho do arquivo no S3
                'SourceFile' => $filePath, // Caminho local do arquivo
                'ContentType' => $mimeType, // Tipo de conteúdo da imagem
            ]);

            // Enviar para o bucket S3
            $result_thumb = $this->s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $directory . '/thumb_' . $fileName, // Caminho do arquivo no S3
                'SourceFile' => $filePath, // Caminho local do arquivo
                'ContentType' => $mimeType, // Tipo de conteúdo da imagem
            ]);

            // Retornar a URL do arquivo no S3
            return $result['ObjectURL'];

        } catch (Exception $e) {
            // Tratar erro
            echo "Erro ao enviar a imagem para o S3: " . $e->getMessage() . "\n";
            return false;
        }
    }
}