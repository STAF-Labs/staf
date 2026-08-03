<?php

namespace App\Services\Admin\Game;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class GameDimensionImportParser
{
    /** @var list<string> */
    private const FILTER_HEADERS = [
        'filter_key',
        'name',
        'selection_mode',
        'applies_to',
        'is_filterable',
        'is_required',
        'is_active',
    ];

    /** @var list<string> */
    private const VALUE_HEADERS = [
        'filter_key',
        'value_key',
        'name',
        'parent_key',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array{
     *     valid: bool,
     *     message: string,
     *     filters: list<array{row: int, filter_key: string, name: string, selection_mode: string, applies_to: string, is_filterable: bool, is_required: bool, is_active: bool}>,
     *     values: list<array{row: int, filter_key: string, value_key: string, name: string, parent_key: string|null, sort_order: int, is_active: bool}>
     * }
     */
    public function parse(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            return $this->invalid('Не удалось прочитать загруженный файл.');
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (Throwable) {
            return $this->invalid('Не удалось прочитать файл. Проверьте, что это Excel .xlsx.');
        }

        try {
            $filtersSheet = $spreadsheet->getSheetByName('filters');
            $valuesSheet = $spreadsheet->getSheetByName('values');

            if (! $filtersSheet instanceof Worksheet || ! $valuesSheet instanceof Worksheet) {
                return $this->invalid('Книга должна содержать листы filters и values.');
            }

            $filtersTable = $this->table($filtersSheet);
            $valuesTable = $this->table($valuesSheet);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        $filtersHeaderError = $this->headerError('filters', $filtersTable['headers'], self::FILTER_HEADERS);

        if ($filtersHeaderError !== null) {
            return $this->invalid($filtersHeaderError);
        }

        $valuesHeaderError = $this->headerError('values', $valuesTable['headers'], self::VALUE_HEADERS);

        if ($valuesHeaderError !== null) {
            return $this->invalid($valuesHeaderError);
        }

        $filtersResult = $this->filters($filtersTable['rows']);

        if (is_string($filtersResult)) {
            return $this->invalid($filtersResult);
        }

        $valuesResult = $this->values($valuesTable['rows'], $filtersResult);

        if (is_string($valuesResult)) {
            return $this->invalid($valuesResult);
        }

        return [
            'valid' => true,
            'message' => 'Файл подходит для импорта.',
            'filters' => $filtersResult,
            'values' => $valuesResult,
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<array{number: int, values: list<string>}>}
     */
    private function table(Worksheet $sheet): array
    {
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn(1));
        $highestRow = $sheet->getHighestDataRow();
        $headers = [];
        $rows = [];

        for ($column = 1; $column <= $highestColumn; $column++) {
            $headers[] = trim((string) $sheet->getCell(
                Coordinate::stringFromColumnIndex($column).'1'
            )->getValue());
        }

        for ($row = 2; $row <= $highestRow; $row++) {
            $values = [];

            for ($column = 1; $column <= $highestColumn; $column++) {
                $values[] = trim((string) $sheet->getCell(
                    Coordinate::stringFromColumnIndex($column).$row
                )->getValue());
            }

            $rows[] = [
                'number' => $row,
                'values' => $values,
            ];
        }

        return [
            'headers' => $this->trimTrailingEmpty($headers),
            'rows' => $rows,
        ];
    }

    /** @param list<string> $actual @param list<string> $expected */
    private function headerError(string $sheet, array $actual, array $expected): ?string
    {
        $normalized = array_map(
            fn (string $header): string => mb_strtolower(trim($header), 'UTF-8'),
            $actual
        );

        if ($normalized !== $expected) {
            return "Лист {$sheet}: ожидаются колонки ".implode(',', $expected).'.';
        }

        return null;
    }

    /**
     * @param  list<array{number: int, values: list<string>}>  $rows
     * @return list<array{row: int, filter_key: string, name: string, selection_mode: string, applies_to: string, is_filterable: bool, is_required: bool, is_active: bool}>|string
     */
    private function filters(array $rows): array|string
    {
        $filters = [];
        $seenKeys = [];
        $seenNames = [];

        foreach ($rows as $row) {
            $values = $this->trimTrailingEmpty($row['values']);

            if ($values === []) {
                continue;
            }

            if (count($values) !== count(self::FILTER_HEADERS)) {
                return "Лист filters, строка {$row['number']}: заполните все колонки.";
            }

            [
                $key,
                $name,
                $mode,
                $appliesTo,
                $filterableRaw,
                $requiredRaw,
                $activeRaw,
            ] = $values;
            $key = $this->normalizedKey($key);
            $name = trim($name);
            $mode = mb_strtolower(trim($mode), 'UTF-8');
            $appliesTo = mb_strtolower(trim($appliesTo), 'UTF-8');

            if (! $this->validKey($key)) {
                return "Лист filters, строка {$row['number']}: filter_key должен содержать только латинские буквы, цифры, _ или -.";
            }

            if (isset($seenKeys[$key])) {
                return "Лист filters, строка {$row['number']}: filter_key {$key} уже есть в файле.";
            }

            if ($name === '' || mb_strlen($name) > 64) {
                return "Лист filters, строка {$row['number']}: name обязателен и должен быть не длиннее 64 символов.";
            }

            $normalizedName = $this->normalizedName($name);

            if (isset($seenNames[$normalizedName])) {
                return "Лист filters, строка {$row['number']}: фильтр {$name} уже есть в файле.";
            }

            if (! in_array($mode, ['single', 'multiple'], true)) {
                return "Лист filters, строка {$row['number']}: selection_mode должен быть single или multiple.";
            }

            if (! in_array($appliesTo, ['project', 'release'], true)) {
                return "Лист filters, строка {$row['number']}: applies_to должен быть project или release.";
            }

            $filterable = $this->booleanValue($filterableRaw);
            $required = $this->booleanValue($requiredRaw);
            $active = $this->booleanValue($activeRaw);

            if ($filterable === null || $required === null || $active === null) {
                return "Лист filters, строка {$row['number']}: логические поля должны содержать true, false, 1, 0, yes, no, да или нет.";
            }

            $seenKeys[$key] = true;
            $seenNames[$normalizedName] = true;
            $filters[] = [
                'row' => $row['number'],
                'filter_key' => $key,
                'name' => $name,
                'selection_mode' => $mode,
                'applies_to' => $appliesTo,
                'is_filterable' => $filterable,
                'is_required' => $required,
                'is_active' => $active,
            ];
        }

        if ($filters === []) {
            return 'Лист filters не содержит фильтров для импорта.';
        }

        return $filters;
    }

    /**
     * @param  list<array{number: int, values: list<string>}>  $rows
     * @param  list<array{row: int, filter_key: string, name: string, selection_mode: string, applies_to: string, is_filterable: bool, is_required: bool, is_active: bool}>  $filters
     * @return list<array{row: int, filter_key: string, value_key: string, name: string, parent_key: string|null, sort_order: int, is_active: bool}>|string
     */
    private function values(array $rows, array $filters): array|string
    {
        $filterKeys = array_fill_keys(array_column($filters, 'filter_key'), true);
        $values = [];
        $seenKeys = [];
        $seenNames = [];

        foreach ($rows as $row) {
            $rowValues = $this->trimTrailingEmpty($row['values']);

            if ($rowValues === []) {
                continue;
            }

            if (count($rowValues) !== count(self::VALUE_HEADERS)) {
                return "Лист values, строка {$row['number']}: заполните все колонки, кроме parent_key.";
            }

            [$filterKey, $valueKey, $name, $parentKey, $sortOrderRaw, $activeRaw] = $rowValues;
            $filterKey = $this->normalizedKey($filterKey);
            $valueKey = $this->normalizedKey($valueKey);
            $parentKey = trim($parentKey) === '' ? null : $this->normalizedKey($parentKey);
            $name = trim($name);
            $compositeKey = $filterKey.':'.$valueKey;

            if (! isset($filterKeys[$filterKey])) {
                return "Лист values, строка {$row['number']}: filter_key {$filterKey} отсутствует на листе filters.";
            }

            if (! $this->validKey($valueKey)) {
                return "Лист values, строка {$row['number']}: value_key должен содержать только латинские буквы, цифры, _ или -.";
            }

            if ($parentKey !== null && ! $this->validKey($parentKey)) {
                return "Лист values, строка {$row['number']}: parent_key имеет неверный формат.";
            }

            if (isset($seenKeys[$compositeKey])) {
                return "Лист values, строка {$row['number']}: value_key {$valueKey} уже используется фильтром {$filterKey}.";
            }

            if ($name === '' || mb_strlen($name) > 64) {
                return "Лист values, строка {$row['number']}: name обязателен и должен быть не длиннее 64 символов.";
            }

            $normalizedName = $filterKey.':'.$this->normalizedName($name);

            if (isset($seenNames[$normalizedName])) {
                return "Лист values, строка {$row['number']}: значение {$name} уже есть у фильтра {$filterKey}.";
            }

            if (filter_var($sortOrderRaw, FILTER_VALIDATE_INT) === false || (int) $sortOrderRaw < 0) {
                return "Лист values, строка {$row['number']}: sort_order должен быть целым неотрицательным числом.";
            }

            $active = $this->booleanValue($activeRaw);

            if ($active === null) {
                return "Лист values, строка {$row['number']}: is_active имеет неверное значение.";
            }

            $seenKeys[$compositeKey] = true;
            $seenNames[$normalizedName] = true;
            $values[] = [
                'row' => $row['number'],
                'filter_key' => $filterKey,
                'value_key' => $valueKey,
                'name' => $name,
                'parent_key' => $parentKey,
                'sort_order' => (int) $sortOrderRaw,
                'is_active' => $active,
            ];
        }

        $hierarchyError = $this->hierarchyError($values, $seenKeys);

        return $hierarchyError ?? $values;
    }

    /**
     * @param  list<array{row: int, filter_key: string, value_key: string, name: string, parent_key: string|null, sort_order: int, is_active: bool}>  $values
     * @param  array<string, bool>  $keys
     */
    private function hierarchyError(array $values, array $keys): ?string
    {
        $parents = [];
        $rows = [];

        foreach ($values as $value) {
            $key = $value['filter_key'].':'.$value['value_key'];
            $parent = $value['parent_key'];
            $parents[$key] = $parent === null ? null : $value['filter_key'].':'.$parent;
            $rows[$key] = $value['row'];

            if ($parents[$key] !== null && ! isset($keys[$parents[$key]])) {
                return "Лист values, строка {$value['row']}: parent_key {$parent} отсутствует у фильтра {$value['filter_key']}.";
            }
        }

        foreach (array_keys($parents) as $start) {
            $visited = [];
            $current = $start;

            while ($current !== null) {
                if (isset($visited[$current])) {
                    return "Лист values, строка {$rows[$start]}: обнаружен цикл в parent_key.";
                }

                $visited[$current] = true;
                $current = $parents[$current] ?? null;
            }
        }

        return null;
    }

    private function booleanValue(string $value): ?bool
    {
        return match (mb_strtolower(trim($value), 'UTF-8')) {
            'true', '1', 'yes', 'да' => true,
            'false', '0', 'no', 'нет' => false,
            default => null,
        };
    }

    private function normalizedKey(string $key): string
    {
        return mb_strtolower(trim($key), 'UTF-8');
    }

    private function normalizedName(string $name): string
    {
        return mb_strtolower(trim($name), 'UTF-8');
    }

    private function validKey(string $key): bool
    {
        return $key !== '' && strlen($key) <= 100 && preg_match('/^[a-z0-9_-]+$/', $key) === 1;
    }

    /** @param list<string> $values @return list<string> */
    private function trimTrailingEmpty(array $values): array
    {
        while ($values !== [] && trim((string) $values[array_key_last($values)]) === '') {
            array_pop($values);
        }

        return $values;
    }

    /** @return array{valid: false, message: string, filters: array{}, values: array{}} */
    private function invalid(string $message): array
    {
        return [
            'valid' => false,
            'message' => $message,
            'filters' => [],
            'values' => [],
        ];
    }
}
