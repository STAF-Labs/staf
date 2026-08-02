<?php

namespace App\Http\Requests\Admin\Game;

use App\Models\Game\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameContentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'content_type_id' => [
                'required',
                'integer',
                Rule::exists('content_types', 'id'),
                Rule::unique('game_content_types', 'content_type_id')
                    ->where('game_id', $this->game()?->id),
            ],
        ];
    }

    private function game(): ?Game
    {
        $game = $this->route('game');

        return $game instanceof Game ? $game : null;
    }
}
