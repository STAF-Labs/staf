<?php

namespace App\Services\Admin\Licence;

use Composer\Spdx\SpdxLicenses;

class SpdxLicenceCatalog
{
    public function __construct(private readonly SpdxLicenses $spdxLicenses) {}

    /**
     * @return list<array{id: string, name: string, is_osi_approved: bool, reference_url: string|null}>
     */
    public function all(): array
    {
        return collect($this->spdxLicenses->getLicenses())
            ->reject(fn (array $licence): bool => $licence[3])
            ->map(fn (array $licence): array => [
                'id' => $licence[0],
                'name' => $licence[1],
                'is_osi_approved' => $licence[2],
                'reference_url' => "https://spdx.org/licenses/{$licence[0]}.html",
            ])
            ->push(
                [
                    'id' => 'LicenseRef-Proprietary',
                    'name' => 'Все права защищены',
                    'is_osi_approved' => false,
                    'reference_url' => null,
                ],
                [
                    'id' => 'LicenseRef-Custom',
                    'name' => 'Собственная лицензия',
                    'is_osi_approved' => false,
                    'reference_url' => null,
                ],
            )
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function identifiers(): array
    {
        return array_column($this->all(), 'id');
    }
}
