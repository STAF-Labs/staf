<?php

namespace App\Services\Admin\Project;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ProjectUploadLimits
{
    public function maxRequestBytes(): int
    {
        return (int) config('projects.uploads.max_request_bytes', 50 * 1024 * 1024);
    }

    public function maxRequestMegabytes(): int
    {
        return (int) ceil($this->maxRequestBytes() / 1024 / 1024);
    }

    /**
     * @param  list<string>  $fields
     */
    public function uploadedBytes(Request $request, array $fields): int
    {
        $bytes = 0;

        foreach ($fields as $field) {
            $bytes += $this->fileBytes($request->file($field));
        }

        return $bytes;
    }

    private function fileBytes(mixed $file): int
    {
        if ($file instanceof UploadedFile) {
            return $file->getSize() ?: 0;
        }

        if (is_array($file)) {
            return array_sum(array_map(fn (mixed $item): int => $this->fileBytes($item), $file));
        }

        return 0;
    }

    /**
     * @param  list<string>  $fields
     */
    public function exceedsLimit(Request $request, array $fields): bool
    {
        return $this->uploadedBytes($request, $fields) > $this->maxRequestBytes();
    }

    public function errorMessage(): string
    {
        return "Общий размер файлов в одном сохранении не должен превышать {$this->maxRequestMegabytes()} МБ. Удалите часть файлов или уменьшите размер изображений.";
    }
}
