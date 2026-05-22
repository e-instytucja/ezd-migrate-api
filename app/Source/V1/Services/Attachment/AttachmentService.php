<?php
declare(strict_types=1);
namespace App\Source\V1\Services\Attachment;
use App\Source\V1\DTO\TypHistoriaObiegu;
use App\Source\V1\DTO\TypZalacznik;
use App\Source\V1\Queries\Attachment\AttachmentQuery;
use App\Source\V1\Queries\Case\CaseQuery;
use App\Source\V1\Queries\Form\FormQuery;

class AttachmentService
{


    public function __construct(
        private readonly AttachmentQuery $attachmentQuery,
        private readonly CaseQuery $caseQuery,
        private readonly FormQuery $formQuery
    )
    {

    }

    /**
     * @param $attachmentUids
     * @return TypZalacznik[]
     * @throws \JsonException
     */
    public function getAttachmentDetails(string $attachmentUids): array
    {
        $attachmentUids = array_values(array_filter(explode(';', $attachmentUids)));
        if(empty($attachmentUids)) {
            return [];
        }
        $attachmentDetails = [];
        foreach ($this->attachmentQuery->getAttachmentRows($attachmentUids) as $item) {
            $url = $this->createUrl($item);

            $fileInfo = $this->resolveFileInfo($item->zalacznik_original_filename);

            $typZalacznik = new TypZalacznik(
                filename:  $item->zalacznik_filename,
                nazwa:     $item->zalacznik_original_filename,
                rozmiar:   $item->zalacznik_filesize,
                url:       $url,
                md5:       $item->zalacznik_md5_sum,
                opis:      $item->zalacznik_opis,
                mime:      $fileInfo['mime'],
                extension: $fileInfo['extension']
            );
            $attachmentDetails[] = $typZalacznik;

        }
        return $attachmentDetails;
    }

    private function resolveFileInfo(string $filename): array
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $map = [
            // Dokumenty biurowe
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'dot'  => 'application/msword',
            'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xlt'  => 'application/vnd.ms-excel',
            'xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
            'ppt'  => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt'  => 'application/vnd.oasis.opendocument.text',
            'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp'  => 'application/vnd.oasis.opendocument.presentation',
            'odg'  => 'application/vnd.oasis.opendocument.graphics',
            'odf'  => 'application/vnd.oasis.opendocument.formula',
            'rtf'  => 'application/rtf',
            // Tekst / dane
            'txt'  => 'text/plain',
            'csv'  => 'text/csv',
            'tsv'  => 'text/tab-separated-values',
            'xml'  => 'application/xml',
            'xsl'  => 'application/xml',
            'xslt' => 'application/xslt+xml',
            'html' => 'text/html',
            'htm'  => 'text/html',
            'json' => 'application/json',
            'yaml' => 'application/yaml',
            'yml'  => 'application/yaml',
            // Archiwa
            'zip'  => 'application/zip',
            'rar'  => 'application/vnd.rar',
            '7z'   => 'application/x-7z-compressed',
            'tar'  => 'application/x-tar',
            'gz'   => 'application/gzip',
            'bz2'  => 'application/x-bzip2',
            // Obrazy
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'bmp'  => 'image/bmp',
            'tif'  => 'image/tiff',
            'tiff' => 'image/tiff',
            'svg'  => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico'  => 'image/x-icon',
            // Audio
            'mp3'  => 'audio/mpeg',
            'wav'  => 'audio/wav',
            'ogg'  => 'audio/ogg',
            'flac' => 'audio/flac',
            'm4a'  => 'audio/mp4',
            // Wideo
            'mp4'  => 'video/mp4',
            'avi'  => 'video/x-msvideo',
            'mov'  => 'video/quicktime',
            'mkv'  => 'video/x-matroska',
            'webm' => 'video/webm',
            'wmv'  => 'video/x-ms-wmv',
            // Podpisy elektroniczne / certyfikaty
            'sig'  => 'application/pgp-signature',
            'p7s'  => 'application/pkcs7-signature',
            'p7m'  => 'application/pkcs7-mime',
            'xades'=> 'application/xml',
            'pades'=> 'application/pdf',
            'cades'=> 'application/octet-stream',
            'cer'  => 'application/x-x509-ca-cert',
            'crt'  => 'application/x-x509-ca-cert',
        ];

        return [
            'mime'      => $map[$ext] ?? 'application/octet-stream',
            'extension' => $ext,
        ];
    }

    private function createUrl($item): string
    {
        return base64_encode(
            json_encode([
                'attachmentUid' => $item->zalacznik_uid,
                'md5' => $item->zalacznik_md5_sum
            ], JSON_THROW_ON_ERROR)
        );
    }

    public function getAttachmentContent($token): string
    {
        $content = "%PDF-1.4\nPrzykłądowy plik pdf dla token:  {$token}\n%%EOF";

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="dokument.pdf"');
        header('Content-Length: ' . strlen($content));

        return $content;
    }

    /**
     * @param $caseUid
     * @return array|TypZalacznik[]
     * @throws \JsonException
     */
    public function getCaseAttachments($caseUid): array
    {
        $mainDocumentUid = $this->caseQuery->getMainDocumentUidByCaseUid($caseUid);
        $attachments = $this->formQuery->getValuesFromFormDane($mainDocumentUid, 'pliki');
        if(empty($attachments)) {
            return [];
        }
        $attachments = implode(';', array_column($attachments, 'form_dane_wartosc'));
        return $this->getAttachmentDetails($attachments);
    }
}