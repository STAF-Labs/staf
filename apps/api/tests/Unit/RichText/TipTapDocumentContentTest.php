<?php

namespace Tests\Unit\RichText;

use App\Rules\FilledTipTapDocument;
use App\Services\RichText\TipTapDocumentContent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TipTapDocumentContentTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed}>
     */
    public static function emptyDocuments(): iterable
    {
        yield 'null' => [null];
        yield 'empty array' => [[]];
        yield 'empty doc' => [[
            'type' => 'doc',
            'content' => [],
        ]];
        yield 'empty paragraph' => [[
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph'],
            ],
        ]];
        yield 'spaces only' => [[
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => "  \n\t  "],
                    ],
                ],
            ],
        ]];
        yield 'unicode separators only' => [[
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => "\u{00A0}\u{200B}"],
                    ],
                ],
            ],
        ]];
        yield 'image without text' => [[
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'image',
                    'attrs' => ['src' => 'https://example.com/image.png'],
                ],
            ],
        ]];
    }

    #[DataProvider('emptyDocuments')]
    public function test_it_detects_empty_documents(mixed $document): void
    {
        $this->assertFalse((new TipTapDocumentContent)->hasText($document));
    }

    public function test_it_detects_text_in_a_paragraph(): void
    {
        $document = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Описание проекта'],
                    ],
                ],
            ],
        ];

        $this->assertTrue((new TipTapDocumentContent)->hasText($document));
    }

    public function test_it_detects_text_in_a_json_string(): void
    {
        $document = json_encode([
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Описание игры'],
                    ],
                ],
            ],
        ]);

        $this->assertTrue((new TipTapDocumentContent)->hasText($document));
    }

    public function test_it_detects_nested_text(): void
    {
        $document = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'bulletList',
                    'content' => [
                        [
                            'type' => 'listItem',
                            'content' => [
                                [
                                    'type' => 'paragraph',
                                    'content' => [
                                        ['type' => 'text', 'text' => 'Пункт списка'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertTrue((new TipTapDocumentContent)->hasText($document));
    }

    public function test_validation_rule_fails_empty_documents(): void
    {
        $failures = [];
        $rule = new FilledTipTapDocument(new TipTapDocumentContent);

        $rule->validate('summary', [
            'type' => 'doc',
            'content' => [],
        ], function (string $message) use (&$failures): void {
            $failures[] = $message;
        });

        $this->assertSame(['Поле :attribute должно содержать текст.'], $failures);
    }

    public function test_validation_rule_passes_documents_with_text(): void
    {
        $failures = [];
        $rule = new FilledTipTapDocument(new TipTapDocumentContent);

        $rule->validate('summary', [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Краткое описание'],
                    ],
                ],
            ],
        ], function (string $message) use (&$failures): void {
            $failures[] = $message;
        });

        $this->assertSame([], $failures);
    }
}
