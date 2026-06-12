<?php
declare(strict_types=1);
namespace App\Source\V1\Services\Dictionary;

use App\Source\V1\Queries\Dictionary\DoctionaryQuery;

class DictionaryService {


    public function __construct(
        private readonly DoctionaryQuery $dictionaryQuery
    )
    {

    }

    /**
     * @throws \JsonException
     */
    public function getDictionaryValue(int $dictionaryContentId): string
    {
        return $this->dictionaryQuery->getDictionaryValue($dictionaryContentId);
    }


}