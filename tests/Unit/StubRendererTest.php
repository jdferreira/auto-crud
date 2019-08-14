<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Stub\StubPart;
use Ferreira\AutoCrud\Stub\StubRenderer;
use Ferreira\AutoCrud\Stub\StubRenderingException;

class StubRendererTest extends TestCase
{
    public function stub($type)
    {
        return $this->files->get(__DIR__ . "/inputs/$type.stub");
    }

    public function output($type)
    {
        return $this->files->get(__DIR__ . "/outputs/$type.rendered");
    }

    private function assertPartsEqual($expected, $code)
    {
        $rendered = (new StubRenderer($code))->parts();

        $this->assertEquals($expected, $rendered);
    }

    /** @test */
    public function it_recognizes_placeholders()
    {
        $this->assertPartsEqual(
            [
                StubPart::placeholder('a'),
            ],
            '{{ a }}'
        );
    }

    /** @test */
    public function it_detects_amount_to_remove_before()
    {
        $this->assertPartsEqual(
            [
                StubPart::literal('  '),
                StubPart::placeholder('a')->setIndentation('  ')->setAmountToRemove(2, 0),
            ],
            '  {{ a }}'
        );
    }

    /** @test */
    public function it_detects_amount_to_remove_after()
    {
        $this->assertPartsEqual(
            [
                StubPart::placeholder('a')->setAmountToRemove(0, 2),
                StubPart::literal('  '),
            ],
            '{{ a }}  '
        );
    }

    /** @test */
    public function it_detects_that_it_can_remove_newlines_after_placeholder()
    {
        $this->assertPartsEqual(
            [
                StubPart::literal('  '),
                StubPart::placeholder('a')->setIndentation('  ')->setAmountToRemove(2, 1),
                StubPart::literal("\n"),
            ],
            "  {{ a }}\n"
        );

        $this->assertPartsEqual(
            [
                StubPart::literal('  '),
                StubPart::placeholder('a')->setIndentation('  ')->setAmountToRemove(2, 3),
                StubPart::literal("  \n"),
            ],
            "  {{ a }}  \n"
        );
    }

    /** @test */
    public function it_handles_consecutive_placeholders()
    {
        $this->assertPartsEqual(
            [
                StubPart::placeholder('a'),
                StubPart::placeholder('b'),
            ],
            '{{ a }}{{ b }}'
        );
    }

    /** @test */
    public function it_splits_a_stub()
    {
        $this->assertPartsEqual(
            [
                StubPart::literal("<?php\n\nclass "),
                StubPart::placeholder('name'),
                StubPart::literal("\n{\n    "),
                StubPart::placeholder('other')->setIndentation('    ')->setAmountToRemove(4, 1),
                StubPart::literal("\n\n    protected \$another = "),
                StubPart::placeholder('another')->setIndentation('    '),
                StubPart::literal(";\n\n    "),
                StubPart::placeholder('final')->setIndentation('    ')->setAmountToRemove(4, 1),
                StubPart::literal("\n}\n\n"),
                StubPart::placeholder('comment')->setAmountToRemove(0, 1),
                StubPart::literal("\n"),
            ],
            $this->stub('sample')
        );
    }

    /** @test */
    public function it_throws_on_malformed_stub()
    {
        $badInputs = [
            '{{ name',
            '{{ name ',
            '{{ name }',
            '{{ name with spaces }}',
            "{{ name \n",
            "{{ name \n }}",
        ];

        foreach ($badInputs as $input) {
            $this->assertException(
                StubRenderingException::class,
                function () use ($input) {
                    new StubRenderer($input);
                },
                'Input ' . var_export($input, true) . ' failed to throw'
            );
        }
    }

    /** @test */
    public function it_assumes_missing_replacements_are_empty_strings()
    {
        $this->assertEquals('', StubRenderer::render('{{ a }}', []));
    }

    /** @test */
    public function it_replaces_placeholders()
    {
        $rendered = StubRenderer::render($this->stub('sample'), [
            'name' => 'TheClass',
            'other' => 'private $other = \'id\';',
            'another' => '\'another\'',
            'final' => '// Comment',
            'comment' => '// Outside comment',
        ]);

        $this->assertEquals(
            $this->output('sample.complete'),
            $rendered
        );
    }

    /** @test */
    public function it_indents_multiline_replacements()
    {
        $rendered = StubRenderer::render($this->stub('sample'), [
            'name' => 'TheClass',
            'other' => 'private $other1 = 1;' . "\n" . 'private $other2 = 2;',
            'another' => '\'another\'',
            'final' => '// Comment',
            'comment' => '// Outside comment',
        ]);

        $this->assertEquals(
            $this->output('sample.multiline'),
            $rendered
        );
    }

    /** @test */
    public function it_converts_array_replacements_into_multiline_values()
    {
        $rendered = StubRenderer::render($this->stub('sample'), [
            'name' => 'TheClass',
            'other' => [
                'private $other1 = 1;',
                'private $other2 = 2;',
            ],
            'another' => '\'another\'',
            'final' => '// Comment',
            'comment' => '// Outside comment',
        ]);

        $this->assertEquals(
            $this->output('sample.multiline'),
            $rendered
        );
    }

    /** @test */
    public function it_removes_lines_containing_a_single_empty_placeholder()
    {
        $this->assertEquals(
            $this->output('sample.empty'),
            StubRenderer::render($this->stub('sample'), [
                'name' => 'TheClass',
                'other' => '',
                'another' => '\'another\'',
                'final' => '// Final',
                'comment' => '',
            ])
        );

        $this->assertEquals(
            $this->output('empty'),
            StubRenderer::render($this->stub('empty'), [
                'name' => 'Name',
                'placeholder' => '',
            ])
        );
    }
}
