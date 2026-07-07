<?php


declare(strict_types=1);


namespace App\Source\V1\Services\Suppliant;


use App\Shared\Functions;

use App\Source\V1\DTO\InteresantAdresDto;

use App\Source\V1\DTO\InteresantDto;
use App\Source\V1\DTO\InteresantKontaktDto;
use App\Source\V1\DTO\InteresantKontekstDto;

use App\Source\V1\DTO\InteresantInstytucjaDto;
use App\Source\V1\DTO\InteresantOsobaDto;
use App\Source\V1\Queries\Suppliant\SuppliantQuery;


class SupliantService

{

    public function __construct(

        private readonly SuppliantQuery $suppliantQuery,

    )
    {

    }


    /**
     * @param array<string, mixed> $row
     */

    public function hydrateSuppliantData(array &$row, $documentUid): void

    {
        if (isset($row['interesant'])) {

            $row['interesant'] = Functions::normalizeText($row['interesant']);
            $row['interesant_adres'] = Functions::normalizeText($row['interesant_adres']);
            $row['interesant_type'] = ($row['interesant_type'] ?? null) === 'firma'
                ? 'instytucja'
                : 'osoba';

            $row['interesant_meta'] = [
                'interesant_type' => $row['interesant_type'],
            ];
        }
        $row['pozostali_interesanci'] = [];
        $row['pozostali_interesanci_tooltip_count'] = 0;
        $row['pozostali_interesanci_tooltip'] = '';

        if ($row['has_pozostali_interesanci'] === true) {
            $row['pozostali_interesanci'] = $this->getAdditionalSuppliants($documentUid);
            $row['pozostali_interesanci_tooltip_count'] = count($row['pozostali_interesanci']);
            $row['pozostali_interesanci_tooltip'] = implode(', ', array_column(
                $row['pozostali_interesanci'],
                'interesant',
            ));
        }
    }

    public function getAdditionalSuppliants($mainDocumentUid): array
    {
        $suppliants = $this->suppliantQuery->getAdditionalSuppliants($mainDocumentUid);

        return $this->normalizeSuppliants($suppliants);
    }

    public function getSupliantById($suppliantUid): array
    {
        $suppliant = $this->suppliantQuery->getSupliantById($suppliantUid);
        return $this->normalizeSuppliants($suppliant);
    }

    public function getPetentRoleById($suppliantUid): array
    {
        return $this->suppliantQuery->getPetentRoleById($suppliantUid);
    }

    public function mapToInteresantDto(
        array  $row,
        string $uid,
        bool   $glowny,
        array  $role,
    ): InteresantDto
    {
        $adresMetadane = is_array($row['adres_metadane'] ?? null) ? $row['adres_metadane'] : [];
        return new InteresantDto(
            kontekst: new InteresantKontekstDto(
                uid: $uid,
                glowny: $glowny,
                role: $role,
            ),
            podmiot: $this->mapToPodmiotDto($row),
            adres: new InteresantAdresDto(
                ulica: $this->nullableString($row, 'ulica'),
                numerDomu: $this->nullableString($row, 'numer_domu'),
                numerLokalu: $this->nullableString($row, 'numer_lokalu'),
                kodPocztowy: $this->nullableString($row, 'kod'),
                miasto: $this->nullableString($row, 'miasto'),
                poczta: $this->nullableString($row, 'poczta'),
                kraj: $this->nullableString($row, 'kraj'),
                pelny: $this->nullableString($adresMetadane, 'adres_korespondencyjny'),
            ),
            kontakt: new InteresantKontaktDto(
                adresEpuap: $this->nullableString($row, 'front_office_petent_id'),
                telefon: $this->nullableString($row, 'nr_telefonu'),
                kontakt: $this->nullableString($row, 'kontakt'),
                adresWww: $this->nullableString($row, 'adres_www'),
                odbiorElektroniczny: $this->nullableString($row, 'odb_pism_form_elektr'),
            ),
        );
    }


    /**
     * @param array<string, mixed> $row
     */
    private function mapToPodmiotDto(array $row): InteresantOsobaDto|InteresantInstytucjaDto
    {
        $typ = ($row['typ_osoby'] ?? null) === 'firma' ? 'instytucja' : 'osoba';
        if ($typ === 'instytucja') {
            return new InteresantInstytucjaDto(
                typ: $typ,
                nazwa: $this->nullableString($row, 'view_podmiot'),
                pesel: $this->nullableString($row, 'pesel'),
                instytucja: $this->nullableString($row, 'instytucja'),
                nip: $this->nullableString($row, 'nip'),
                regon: $this->nullableString($row, 'regon'),
                krs: $this->nullableString($row, 'krs'),
            );
        }

        return new InteresantOsobaDto(
            typ: $typ,
            nazwa: $this->nullableString($row, 'view_podmiot'),
            imie: $this->nullableString($row, 'imie1'),
            nazwisko: $this->nullableString($row, 'nazwisko'),
            pesel: $this->nullableString($row, 'pesel'),
        );
    }


    /**
     * @param array<string, mixed> $data
     */

    private function nullableString(array $data, string $key): ?string
    {
        if (!isset($data[$key])) {
            return null;
        }

        $value = $data[$key];
        if (!is_string($value) || $value === '') {
            return null;
        }
        return $value;
    }

    private function normalizeSuppliants($data): array
    {
        $data = json_decode(json_encode($data), true);
        if (!is_array($data)) {
            return [];
        }

        array_walk_recursive($data, static function (&$value): void {
            if (is_string($value) && $value !== '') {
                $value = Functions::normalizeText($value);
            }
        });

        return $data;
    }
}

