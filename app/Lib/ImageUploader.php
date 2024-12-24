<?php
// app/Lib/ImageUploader.php

App::uses('CakeEmail', 'Network/Email');

class ImageUploader {

    // Defina o bucket S3 e outras configurações
    private $s3Bucket = 'buzke-images'; // Nome do seu bucket S3
    private $s3Client;

    // Construtor para inicializar o cliente S3
    public function __construct() {
        // Aqui você pode configurar o cliente S3 (usando AWS SDK, por exemplo)
        $this->s3Client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => 'sa-east-1',  // Região do seu bucket S3
            'credentials' => [
                'key'    => getenv('AWS_ACCESS_KEY'),
                'secret' => getenv('AWS_SECRET_KEY'),
            ],
            'suppress_php_deprecation_warning' => true,  // Suprimir o aviso
            'http' => [
                'verify' => false,
            ]
        ]);
    }

    // Método para criar a miniatura (thumb) da imagem usando GD
    private function createThumbnail($filePath, $thumbPath) {
        // Obter as dimensões da imagem original
        list($width, $height, $type) = getimagesize($filePath);

        // Define o novo tamanho da miniatura (512x512)
        $thumbWidth = 512;
        $thumbHeight = 512;

        // Calcular as novas dimensões mantendo a proporção
        $ratio = min($thumbWidth / $width, $thumbHeight / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        // Criar uma nova imagem com as novas dimensões
        $thumb = imagecreatetruecolor($newWidth, $newHeight);

        // Carregar a imagem original dependendo do tipo
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($filePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($filePath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($filePath);
                break;
            default:
                throw new Exception('Formato de imagem não suportado');
        }

        // Copiar a imagem original para a nova imagem redimensionada
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Salvar a miniatura
        imagejpeg($thumb, $thumbPath);  // Salva a imagem como JPEG
        imagedestroy($thumb);
        imagedestroy($source);
    }

    // Método para upload de imagem
    public function uploadToS3($file, $directory) {

        try {
            // Verifique se o arquivo foi carregado corretamente
            if (!empty($file['tmp_name'])) {
                // Nome do arquivo para o S3
                $image_name = uniqid() . '_' . basename($file['name']);
                $fileName = $directory . '/' . $image_name;

                $mimeType = mime_content_type($file['tmp_name']);

                // Enviar para o bucket S3
                $result = $this->s3Client->putObject([
                    'Bucket' => $this->s3Bucket,
                    'Key'    => $fileName,
                    'SourceFile' => $file['tmp_name'],  // Caminho local do arquivo
                    'ContentType' => $mimeType,  
                ]);

                // Criar miniatura (thumb) com o prefixo "thumb_"
                $thumbName = 'thumb_' . $image_name;
                $thumbPath = sys_get_temp_dir() . '/' . $thumbName;  // Usar o diretório temporário do sistema

                // Gerar a miniatura
                $this->createThumbnail($file['tmp_name'], $thumbPath);

                // Enviar a miniatura para o S3
                $thumbResult = $this->s3Client->putObject([
                    'Bucket' => $this->s3Bucket,
                    'Key'    => $directory . '/' . $thumbName,
                    'SourceFile' => $thumbPath,
                    'ContentType' => $mimeType,
                ]);

                // Remover o arquivo temporário da miniatura
                unlink($thumbPath);

                // Retornar as URLs do arquivo original e da miniatura
                return $result['ObjectURL'];
            } else {
                throw new Exception('Arquivo não carregado corretamente');
            }
        } catch (Exception $e) {
            // Trate o erro aqui (se necessário)
            return false;
        }
    }

    // Método para deletar imagem do S3
    public function deleteFromS3($fileKey) {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $fileKey,  // Caminho do arquivo no S3
            ]);
            return true;
        } catch (Exception $e) {
            // Trate o erro aqui (se necessário)
            return false;
        }
    }
}
