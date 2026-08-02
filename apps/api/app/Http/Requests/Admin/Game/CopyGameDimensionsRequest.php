<?php

namespace App\Http\Requests\Admin\Game;

use App\Models\Game\ContentType\GameContentType;
use App\Models\Game\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CopyGameDimensionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'source_game_content_type_id' => [
                'required',
                'integer',
                Rule::exists('game_content_types', 'id')->where('game_id', $this->game()?->id),
                Rule::notIn([$this->targetGameContentType()?->id]),
            ],
            'dimension_ids' => ['required', 'array', 'min:1'],
            'dimension_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('dimensions', 'id')->where(
                    'game_content_type_id',
                    $this->integer('source_game_content_type_id')
                ),
            ],
        ];
    }

    private function game(): ?Game
    {
        $game = $this->route('game');

        return $game instanceof Game ? $game : null;
    }

    private function targetGameContentType(): ?GameContentType
    {
        $gameContentType = $this->route('gameContentType');

        return $gameContentType instanceof GameContentType ? $gameContentType : null;
    }
}
