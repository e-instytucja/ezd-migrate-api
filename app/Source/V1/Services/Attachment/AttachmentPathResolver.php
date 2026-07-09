<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Attachment;

use App\Source\V1\Queries\Attachment\AttachmentQuery;
use DateTime;
use Exception;
use RuntimeException;

final class AttachmentPathResolver
{
    public function __construct(
        private readonly AttachmentQuery $attachmentQuery,
    ) {
    }

    public function resolve(object $attachmentRow, string $basePath): string
    {
        $basePath = rtrim($basePath, "\\/ ");

        if ($basePath === '') {
            throw new RuntimeException('Invalid attachment path data');
        }

        $path = $this->resolvePath($attachmentRow, $basePath);

        return str_replace('//', '/', $path);
    }

    private function resolvePath(object $attachmentRow, string $basePath): string
    {
        if (!empty($attachmentRow->reference_id)) {
            $sourceRow = $this->attachmentQuery->getAttachmentRow((string) $attachmentRow->reference_id);
            if ($sourceRow === null) {
                throw new RuntimeException('Attachment details not found');
            }

            return $this->resolvePath($sourceRow, $basePath);
        }

        $filename = basename((string) ($attachmentRow->zalacznik_filename ?? ''));
        $datePath = trim($this->buildPathFromDate((string) ($attachmentRow->zalacznik_createdate ?? '')), "\\/");

        if ($filename === '' || $datePath === '') {
            throw new RuntimeException('Invalid attachment path data');
        }

        if (!empty($attachmentRow->global_id)) {
            $globalId = basename((string) $attachmentRow->global_id);

            if (empty($attachmentRow->parent_uid)) {
                return $basePath
                    . DIRECTORY_SEPARATOR . 'front_office'
                    . DIRECTORY_SEPARATOR . $datePath
                    . DIRECTORY_SEPARATOR . $globalId
                    . DIRECTORY_SEPARATOR . $filename;
            }

            $parentUid = basename((string) $attachmentRow->parent_uid);

            return $basePath
                . DIRECTORY_SEPARATOR . 'front_office'
                . DIRECTORY_SEPARATOR . $datePath
                . DIRECTORY_SEPARATOR . $globalId
                . DIRECTORY_SEPARATOR . $parentUid
                . DIRECTORY_SEPARATOR . $filename;
        }

        if (!empty($attachmentRow->zalacznik_obcy_uid)) {
            $foreignUid = basename((string) $attachmentRow->zalacznik_obcy_uid);

            if (!empty($attachmentRow->parent_uid)) {
                $parentUid = basename((string) $attachmentRow->parent_uid);

                return $basePath
                    . DIRECTORY_SEPARATOR . $datePath
                    . DIRECTORY_SEPARATOR . $foreignUid
                    . DIRECTORY_SEPARATOR . $parentUid
                    . DIRECTORY_SEPARATOR . $filename;
            }

            return $basePath
                . DIRECTORY_SEPARATOR . $datePath
                . DIRECTORY_SEPARATOR . $foreignUid
                . DIRECTORY_SEPARATOR . $filename;
        }

        if (!empty($attachmentRow->parent_uid)) {
            $parentRow = $this->attachmentQuery->getAttachmentRow((string) $attachmentRow->parent_uid);
            if ($parentRow === null) {
                throw new RuntimeException('Attachment details not found');
            }

            $foreignUid = basename((string) ($attachmentRow->zalacznik_obcy_uid ?? ''));
            $parentUid = basename((string) $parentRow->zalacznik_uid);

            if ($foreignUid === '' || $parentUid === '') {
                throw new RuntimeException('Invalid attachment path data');
            }

            return $basePath
                . DIRECTORY_SEPARATOR . $datePath
                . DIRECTORY_SEPARATOR . $foreignUid
                . DIRECTORY_SEPARATOR . $parentUid
                . DIRECTORY_SEPARATOR . $filename;
        }

        throw new RuntimeException('Invalid attachment path data');
    }

    private function buildPathFromDate(string $dataUtworzenia): string
    {
        $dataUtworzenia = trim($dataUtworzenia, "' ");

        try {
            $date = new DateTime($dataUtworzenia);
        } catch (Exception $e) {
            return '';
        }

        return sprintf(
            '%s%s%s',
            $date->format('Y'),
            DIRECTORY_SEPARATOR . $date->format('m'),
            DIRECTORY_SEPARATOR . $date->format('d')
        );
    }
}
