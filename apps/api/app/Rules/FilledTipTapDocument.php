<?php

namespace App\Rules;

use App\Services\RichText\TipTapDocumentContent;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class FilledTipTapDocument implements ValidationRule
{
    public function __construct(
        private readonly ?TipTapDocumentContent $content = null,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $content = $this->content ?? app(TipTapDocumentContent::class);

        if (! $content->hasText($value)) {
            $fail('Поле :attribute должно содержать текст.');
        }
    }
}
