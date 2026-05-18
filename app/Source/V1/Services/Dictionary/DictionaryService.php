<?php
declare(strict_types=1);
namespace App\Source\V1\Services\Dictionary;

use App\Source\V1\DTO\TypPozycjaInteresanta;
use App\Source\V1\Queries\Dictionary\DoctionaryQuery;
use App\Source\V1\Queries\Form\FormQuery;
use App\Source\V1\Queries\Structure\UugQuery;
use App\Source\V1\Queries\Structure\WorkstationQuery;
use App\Source\V1\Services\Attachment\AttachmentService;
use App\Source\V1\Services\Suppliant\SupliantService;
use Illuminate\Support\Facades\DB;

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