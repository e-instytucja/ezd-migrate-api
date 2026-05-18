<?php
declare(strict_types=1);
namespace App\Source\V1\Queries\Dictionary;

use Illuminate\Support\Facades\DB;

class DoctionaryQuery
{
    public function getDictionaryValue(int $dictionaryContentId): string
    {
        return DB::table('eurzad_dictionary_content')
            ->where('id', $dictionaryContentId)
            ->value('nazwa');
    }
}