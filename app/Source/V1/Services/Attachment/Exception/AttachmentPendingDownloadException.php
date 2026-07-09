<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Attachment\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class AttachmentPendingDownloadException extends HttpException
{
    public function __construct()
    {
        parent::__construct(
            409,
            'Brak pliku na dysku. Plik oczekuje na pobranie przez SIDAS MADKOM'
        );
    }
}
