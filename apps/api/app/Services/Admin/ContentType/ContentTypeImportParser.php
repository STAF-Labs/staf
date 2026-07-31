<?php

namespace App\Services\Admin\ContentType;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class ContentTypeImportParser
{
    /**
     * @return array{valid: bool, message: string}
     */
    public function validateHeaders(UploadedFile $file): array
    {
        $result = $this->parse($file);

        return [
            'valid' => $result['valid'],
            'message' => $result['message'],
        ];
    }

    /**
     * @return array{valid: bool, message: string, rows: list<array{row: int, name: string, is_public: bool}>}
     */
    public function parse(UploadedFile $file): array
    {
        try {
            $table = $this->table($file);
        } catch (ReaderException) {
            return [
                'valid' => false,
                'message' => 'Не удалось прочитать файл. Проверьте, что это CSV или Excel .xlsx.',
                'rows' => [],
            ];
        }

        $headerValidation = $this->validateHeaderNames($table['headers']);

        if (! $headerValidation['valid']) {
            return [
                ...$headerValidation,
                'rows' => [],
            ];
        }

        return $this->validateRows($table['rows'], count($this->trimTrailingEmptyHeaders($table['headers'])));
    }

    /**
     * @return array{headers: list<string>, rows: list<array{number: int, values: list<string>}>}
     *
     * @throws ReaderException
     */
    private function table(UploadedFile $file): array
    {
        if (mb_strtolower($file->getClientOriginalExtension()) === 'csv') {
            return $this->csvTable($file);
        }

        return $this->spreadsheetTable($file);
    }

    /**
     * @return array{headers: list<string>, rows: list<array{number: int, values: list<string>}>}
     */
    private function csvTable(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            return ['headers' => [], 'rows' => []];
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = [];
        $rows = [];
        $delimiter = ',';
        $lineNumber = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;

                if (trim($line) === '') {
                    continue;
                }

                if ($headers === []) {
                    $delimiter = $this->detectDelimiter($line);
                    $headers = $this->trimValues(str_getcsv($line, $delimiter));

                    continue;
                }

                $rows[] = [
                    'number' => $lineNumber,
                    'values' => $this->trimValues(str_getcsv($line, $delimiter)),
                ];
            }
        } finally {
            fclose($handle);
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<array{number: int, values: list<string>}>}
     *
     * @throws ReaderException
     */
    private function spreadsheetTable(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            return ['headers' => [], 'rows' => []];
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn(1));
        $highestRow = $sheet->getHighestDataRow();
        $headers = [];
        $rows = [];

        for ($column = 1; $column <= $highestColumn; $column++) {
            $headers[] = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($column).'1')->getValue());
        }

        for ($row = 2; $row <= $highestRow; $row++) {
            $values = [];

            for ($column = 1; $column <= $highestColumn; $column++) {
                $values[] = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($column).$row)->getValue());
            }

            $rows[] = [
                'number' => $row,
                'values' => $values,
            ];
        }

        $spreadsheet->disconnectWorksheets();

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function detectDelimiter(string $line): string
    {
        $delimiters = [',', ';', "\t"];
        $scores = [];

        foreach ($delimiters as $delimiter) {
            $scores[$delimiter] = substr_count($line, $delimiter);
        }

        arsort($scores);

        return (string) array_key_first($scores);
    }

    /**
     * @param  list<string>  $headers
     * @return array{valid: bool, message: string}
     */
    private function validateHeaderNames(array $headers): array
    {
        $normalizedHeaders = array_map(
            fn (string $header): string => mb_strtolower(trim($header)),
            $this->trimTrailingEmptyHeaders($headers)
        );

        if ($normalizedHeaders === []) {
            return [
                'valid' => false,
                'message' => 'Файл пустой. Первая строка должна содержать заголовки name,is_public.',
            ];
        }

        if (count($normalizedHeaders) > 2) {
            return [
                'valid' => false,
                'message' => 'Неверная структура файла. Ожидаются только колонки name и is_public.',
            ];
        }

        if ($normalizedHeaders[0] !== 'name') {
            return [
                'valid' => false,
                'message' => 'Неверная структура файла. Первая колонка должна называться name.',
            ];
        }

        if (isset($normalizedHeaders[1]) && $normalizedHeaders[1] !== 'is_public') {
            return [
                'valid' => false,
                'message' => 'Неверная структура файла. Вторая колонка должна называться is_public.',
            ];
        }

        return [
            'valid' => true,
            'message' => 'Файл подходит для импорта.',
        ];
    }

    /**
     * @param  list<array{number: int, values: list<string>}>  $rows
     * @return array{valid: bool, message: string, rows: list<array{row: int, name: string, is_public: bool}>}
     */
    private function validateRows(array $rows, int $expectedColumns): array
    {
        $importRows = [];
        $seenNames = [];

        foreach ($rows as $row) {
            $values = $this->trimTrailingEmptyHeaders($row['values']);

            if ($values === []) {
                continue;
            }

            if (count($values) > $expectedColumns) {
                return [
                    'valid' => false,
                    'message' => "Строка {$row['number']}: лишние колонки.",
                    'rows' => [],
                ];
            }

            $name = $values[0] ?? '';

            if ($name === '') {
                return [
                    'valid' => false,
                    'message' => "Строка {$row['number']}: заполните name.",
                    'rows' => [],
                ];
            }

            if (mb_strlen($name) > 64) {
                return [
                    'valid' => false,
                    'message' => "Строка {$row['number']}: name должен быть не длиннее 64 символов.",
                    'rows' => [],
                ];
            }

            $normalizedName = mb_strtolower($name);

            if (in_array($normalizedName, $seenNames, true)) {
                return [
                    'valid' => false,
                    'message' => "Строка {$row['number']}: тип контента {$name} уже есть в файле.",
                    'rows' => [],
                ];
            }

            $seenNames[] = $normalizedName;
            $isPublic = $this->booleanValue($values[1] ?? '');

            if ($isPublic === null) {
                return [
                    'valid' => false,
                    'message' => "Строка {$row['number']}: is_public должен быть true, false, 1, 0, yes, no, да или нет.",
                    'rows' => [],
                ];
            }

            $importRows[] = [
                'row' => $row['number'],
                'name' => $name,
                'is_public' => $isPublic,
            ];
        }

        if ($importRows === []) {
            return [
                'valid' => false,
                'message' => 'Файл не содержит значений для импорта.',
                'rows' => [],
            ];
        }

        return [
            'valid' => true,
            'message' => 'Файл подходит для импорта.',
            'rows' => $importRows,
        ];
    }

    private function booleanValue(string $value): ?bool
    {
        $normalizedValue = mb_strtolower(trim($value));

        if ($normalizedValue === '') {
            return true;
        }

        return match ($normalizedValue) {
            'true', '1', 'yes', 'да' => true,
            'false', '0', 'no', 'нет' => false,
            default => null,
        };
    }

    /**
     * @param  array<int, string|null>  $values
     * @return list<string>
     */
    private function trimValues(array $values): array
    {
        return array_map(
            fn (?string $value): string => $value === null ? '' : trim($value),
            $values
        );
    }

    /**
     * @param  list<string>  $headers
     * @return list<string>
     */
    private function trimTrailingEmptyHeaders(array $headers): array
    {
        while ($headers !== [] && trim((string) $headers[array_key_last($headers)]) === '') {
            array_pop($headers);
        }

        return $headers;
    }
}
