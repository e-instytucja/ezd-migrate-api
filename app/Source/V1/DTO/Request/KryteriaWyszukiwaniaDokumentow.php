<?php

declare(strict_types=1);

namespace App\Source\V1\DTO\Request;

readonly class KryteriaWyszukiwaniaDokumentow
{
    public function __construct(
        public ApiKonfiguracja $konfiguracja,
        public TypFiltrDokument $filtry,
        public Paginacja $paginacja,
        public SortowanieDokumentow $sortowanie
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            konfiguracja: ApiKonfiguracja::fromArray($payload['konfiguracja'] ?? []),
            filtry: TypFiltrDokument::fromArray(is_array($payload['filtry'] ?? null) ? $payload['filtry'] : []),
            paginacja: Paginacja::fromPayload($payload),
            sortowanie: SortowanieDokumentow::fromPayload($payload)
        );
    }

    public static function forTeczkaUid(string $teczkaUid): self
    {
        return new self(
            konfiguracja: new ApiKonfiguracja(),
            filtry: TypFiltrDokument::forTeczkaUid($teczkaUid),
            paginacja: new Paginacja(page: 1, limit: 10000, offset: 0),
            sortowanie: new SortowanieDokumentow('data_rejestracji', 'desc')
        );
    }

    public static function forTeczkaUidPaginated(string $teczkaUid, AktaSprawyPaginacja $aktaPaginacja): self
    {
        return new self(
            konfiguracja: new ApiKonfiguracja(),
            filtry: TypFiltrDokument::forTeczkaUid($teczkaUid),
            paginacja: new Paginacja(
                page: $aktaPaginacja->page,
                limit: $aktaPaginacja->limit,
                offset: $aktaPaginacja->offset,
            ),
            sortowanie: new SortowanieDokumentow('data_rejestracji', 'desc')
        );
    }
}
