<?php

declare(strict_types=1);

namespace App\Source\V1\Enum\Concerns;

trait PresentsInApiValue
{
    /**
     * @return array{name: string, label: string}
     */
    public function toApi(): array
    {
        return [
            'name' => $this->value,
            'label' => $this->label(),
        ];
    }

    /**
     * @return array{id: string, label: string}
     */
    public function toFilterOption(): array
    {
        return [
            'id' => $this->value,
            'label' => $this->label(),
        ];
    }
}
