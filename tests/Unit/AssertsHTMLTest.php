<?php

namespace Tests\Unit;

use DOMNode;
use DOMDocument;
use Tests\TestCase;
use Illuminate\Http\Response;
use Ferreira\AutoCrud\AssertsHTML;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\ExpectationFailedException;

class AssertsHTMLTest extends TestCase
{
    use AssertsHTML;

    /**
     * @var DOMDocument
     */
    private $document;

    public function setUp(): void
    {
        parent::setUp();

        $content = implode("\n", [
            '<!DOCTYPE html>',
            '<html>',
            '    <head>',
            '        <title>Document</title>',
            '    </head>',
            '    <body>',
            '        <div></div>',
            '        <div id="one"></div>',
            '    </body>',
            '</html>',
        ]);

        $this->document = $this->getDOMDocument(
            new TestResponse(new Response($content))
        );
    }

    /** @test */
    public function it_can_create_a_dom_document_from_a_test_response()
    {
        $this->assertInstanceOf(DOMDocument::class, $this->document);
    }

    /** @test */
    public function it_accepts_integers_as_assertion_values()
    {
        $this->assertHTML('//div', $this->document, 2);
        $this->assertHTML('//div[@id="one"]', $this->document, 1);
        $this->assertHTML('//div[@id="two"]', $this->document, 0);
    }

    /** @test */
    public function it_interprets_absence_of_value_as_at_least_one_element_found()
    {
        $this->assertHTML('//body/div', $this->document);
    }

    /** @test */
    public function it_accepts_strings_as_assertion_values()
    {
        $this->assertHTML('/html/*/title', $this->document, 'Document');
    }

    /** @test */
    public function it_accepts_closures_as_assertion_values()
    {
        $this->assertHTML('//body', $this->document, function (DOMNode $node) {
            $elements = collect($node->childNodes)->filter(function (DOMNode $node) {
                return $node->nodeType === XML_ELEMENT_NODE;
            })->count();

            $this->assertEquals(2, $elements);
        });
    }

    /** @test */
    public function it_fails_when_using_closures_as_assertion_values_if_no_element_is_found()
    {
        $this->assertException(ExpectationFailedException::class, function () {
            $this->assertHTML('//span', $this->document, function () {
            });
        });
    }
}
