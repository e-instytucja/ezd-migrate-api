<?php

namespace App\Source\V1\Enum;

/**
 * Class DocumentQueryContext
 *
 * @package Docflow\ESBService\Helper\Document\Collection
 */
class DocumentQueryContext
{
    const CASE_UID                                    = 0;
    const DOCUMENT_UIDS                               = 1;
    const CASE_UID_MAIN_DOCUMENT_ATTACHED_TO_CASE     = 2;
    const CASE_UID_MAIN_DOCUMENT_ATTACHED_TO_DOCUMENT = 3;
}
