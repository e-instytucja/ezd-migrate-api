<?php
declare(strict_types=1);
namespace App\Source\V1\Services\Attachment;
use App\Source\V1\DTO\TypHistoriaObiegu;
use App\Source\V1\DTO\TypZalacznik;
use App\Source\V1\Queries\Attachment\AttachmentQuery;

class AttachmentService
{
    public function __construct(
        private readonly AttachmentQuery $attachmentQuery,
    )
    {

    }

    /**
     * @param $attachmentUids
     * @return TypHistoriaObiegu[]
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

            $typZalacznik = new TypZalacznik(
                nazwa: $item->zalacznik_filename,
                rozmiar: $item->zalacznik_filesize,
                url: $url,
                md5: $item->zalacznik_md5_sum,
                opis: $item->zalacznik_opis,
            );
            $attachmentDetails[] = $typZalacznik;

        }
        return $attachmentDetails;
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
}