<?php
declare(strict_types=1);
namespace App\Source\V1\Services\Attachment;
use App\Source\V1\DTO\TypHistoriaObiegu;
use App\Source\V1\DTO\TypZalacznik;
use App\Source\V1\Queries\Attachment\AttachmentQuery;
use App\Source\V1\Queries\Case\CaseQuery;
use App\Source\V1\Queries\Form\FormQuery;
use DateTime;
use Exception;
use RuntimeException;

class AttachmentService
{


    public function __construct(
        private readonly AttachmentQuery $attachmentQuery,
        private readonly CaseQuery $caseQuery,
        private readonly FormQuery $formQuery
    )
    {

    }

    private function getAttachmentDetails(string $attachmentUid): ?TypZalacznik
    {
        $attachmentDetails = $this->getAttachmentsDetails($attachmentUid);
        if(!empty($attachmentDetails)) {
            return $attachmentDetails[0];
        }
        return null;
    }
    /**
     * @param $attachmentUids
     * @return TypZalacznik[]
     * @throws \JsonException
     */
    public function getAttachmentsDetails(string $attachmentUids): array
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
                filename: $item->zalacznik_filename,
                uid: $item->zalacznik_uid,
                nazwa: $item->zalacznik_original_filename,
                zalacznik_obcy_uid: $item->zalacznik_obcy_uid,
                rozmiar: $item->zalacznik_filesize,
                url: $url,
                md5: $item->zalacznik_md5_sum,
                opis: $item->zalacznik_opis,
                mime: $fileInfo['mime'],
                data_utworzenia: $item->zalacznik_createdate,
                extension: $fileInfo['extension'],
            );
            $attachmentDetails[] = $typZalacznik;

        }
        return $attachmentDetails;
    }



    private function createUrl(object $item): string
    {
        return base64_encode(
            json_encode([
                'attachmentUid' => $item->zalacznik_uid,
                'md5' => $item->zalacznik_md5_sum
            ], JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @return array{
     *     path: string,
     *     mime: string,
     *     filename: string,
     *     content_length: int,
     *     extension: string,
     *     md5: string
     * }
     */
    public function getAttachmentContent(string $token): array
    {
        $attachmentDetails = $this->getAttachmentDetails($token);
        if ($attachmentDetails === null) {
            throw new RuntimeException('Attachment details not found');
        }

        $path = $this->buildAttachmentPath(
            basePath: (string) env('FILES_URL'),
            createdAt: $attachmentDetails->data_utworzenia,
            foreignUid: $attachmentDetails->zalacznik_obcy_uid,
            storedFilename: $attachmentDetails->filename
        );

        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('File not found or not readable');
        }

        $fileSize = filesize($path);
        if ($fileSize === false) {
            throw new RuntimeException('Cannot resolve file size');
        }

        $downloadFilename = $this->resolveDownloadFilename(
            originalName: $attachmentDetails->nazwa,
            storedFilename: $attachmentDetails->filename,
            extension: $attachmentDetails->extension
        );

        return [
            'path' => $path,
            'mime' => $attachmentDetails->mime !== '' ? $attachmentDetails->mime : 'application/octet-stream',
            'filename' => $downloadFilename,
            'content_length' => $fileSize,
            'extension' => $attachmentDetails->extension,
            'md5' => $attachmentDetails->md5,
        ];
    }

    public function buildAttachmentPath(
        string $basePath,
        string $createdAt,
        string $foreignUid,
        string $storedFilename
    ): string {
        $basePath = rtrim($basePath, "\\/ ");
        $foreignUid = basename($foreignUid);
        $storedFilename = basename($storedFilename);
        $datePath = trim($this->buildPathFromDate($createdAt), "\\/");

        if ($basePath === '' || $datePath === '' || $foreignUid === '' || $storedFilename === '') {
            throw new RuntimeException('Invalid attachment path data');
        }

        return $basePath
            . DIRECTORY_SEPARATOR . $datePath
            . DIRECTORY_SEPARATOR . $foreignUid
            . DIRECTORY_SEPARATOR . $storedFilename;
    }

    private function resolveDownloadFilename(
        string $originalName,
        string $storedFilename,
        string $extension
    ): string {
        $downloadFilename = trim($originalName) !== ''
            ? $originalName
            : $storedFilename;

        if (pathinfo($downloadFilename, PATHINFO_EXTENSION) === '' && $extension !== '') {
            $downloadFilename .= '.' . $extension;
        }

        return $downloadFilename;
    }

    private function buildPathFromDate(string $dataUtworzenia): string
    {
        // usuń apostrofy z DB timestamp (np. '2024-01-25 12:00:00')
        $dataUtworzenia = trim($dataUtworzenia, "' ");

        try {
            $date = new DateTime($dataUtworzenia);
        } catch (Exception $e) {
            return '';
        }

        return sprintf(
            '/%s/%s/%s/',
            $date->format('Y'),
            $date->format('m'),
            $date->format('d')
        );
    }
    /**
     * @param $caseUid
     * @return array|TypZalacznik[]
     * @throws \JsonException
     */
    public function getCaseAttachments(string $caseUid): array
    {
        $mainDocumentUid = $this->caseQuery->getMainDocumentUidByCaseUid($caseUid);
        $attachments = $this->formQuery->getValuesFromFormDane($mainDocumentUid, 'pliki');
        if(empty($attachments)) {
            return [];
        }
        $attachments = implode(';', array_column($attachments, 'form_dane_wartosc'));
        return $this->getAttachmentsDetails($attachments);
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
}