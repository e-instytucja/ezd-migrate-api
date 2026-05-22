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