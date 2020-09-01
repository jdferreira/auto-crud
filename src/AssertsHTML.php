<?php

namespace Ferreira\AutoCrud;

use DOMXPath;
use DOMDocument;
use Illuminate\Foundation\Testing\TestResponse;

trait AssertsHTML
{
    /**
     * Asserts that the given XPath expression, when evaluated on the given DOM
     * document, finds at least one element.
     *
     * If the `$value` argument is given, it's effect depends on its type:
     * - if the argument is missing or `null`, the method asserts that at least
     *   one element satisfies the XPath query
     * - if an integer, the method asserts that it is equal to the number of
     *   elements satisfying the XPath query
     * - if a string, the method asserts that the text content of the nodes
     *   that satisfy the XPath query are equal to that string
     * - if a callable, the method calls the callable with each element
     *   satisfying the XPath query, running any assertions within
     *
     * @param string $xpathExpression
     * @param \DOMDocument $doc
     * @param int|callable $value
     */
    private function assertHTML(string $xpathExpression, DOMDocument $doc, $value = null)
    {
        if (is_string($value)) {
            $value = function ($node) use ($value) {
                $this->assertEquals($value, $node->textContent);
            };
        }

        $xpath = new DOMXPath($doc);

        $nodelist = $xpath->query($xpathExpression);

        if ($value === null) {
            $this->assertGreaterThan(0, $nodelist->count());
        } else {
            if (is_int($value)) {
                $this->assertEquals($value, $nodelist->count());
            } else {
                if (is_callable($value)) {
                    $this->assertGreaterThan(0, $nodelist->count());

                    collect($nodelist)->each($value);
                }
            }
        }
    }

    /**
     * Converts a response's text to a DOM document.
     *
     * @param \Illuminate\Foundation\Testing\TestResponse $response
     *
     * @return DOMDocument
     */
    private function getDOMDocument(TestResponse $response): DOMDocument
    {
        return tap(new DOMDocument(), function ($doc) use ($response) {
            $doc->loadHTML($response->getContent());

            $doc->preserveWhiteSpace = false;
        });
    }

    /**
     * Returns an XPath query string that searches for elements with the given
     * `tag` and `name` attribute. Optionally, the method accepts the `value`
     * attribute and whether the HTML attribute `required` is present.
     *
     * @param string $tag
     * @param string $name
     * @param mixed $value
     * @param null|bool $required
     *
     * @return string
     */
    public function getXPath(string $tag, string $name, $value = null, bool $required = null): string
    {
        $conditions = [];

        $name = htmlentities($name, ENT_QUOTES | ENT_HTML5);
        $conditions[] = "@name='$name'";

        if ($value !== null) {
            $value = htmlentities($value, ENT_QUOTES | ENT_HTML5);

            $attr = $tag !== 'textarea' ? '@value' : 'text()';

            $conditions[] = "$attr='$value'";
        }

        if ($required !== null) {
            if ($required) {
                $conditions[] = '@required';
            } else {
                $conditions[] = 'not(@required)';
            }
        }

        $conditions = implode(' and ', $conditions);

        return "//{$tag}[$conditions]";
    }
}
