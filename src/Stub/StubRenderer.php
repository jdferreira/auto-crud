<?php

namespace Ferreira\AutoCrud\Stub;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class StubRenderer
{
    /**
     * The stub content.
     *
     * @var string
     */
    private $stub;

    /**
     * Whether a virtual EOL character was added to the stub and needs to be removed after rendering.
     *
     * @var bool
     */
    private $virtualEndOfLine = false;

    /**
     * The various StubPart instances that make up the stub.
     *
     * @var StubPart[]
     */
    private $parts;

    public function __construct(string $stub)
    {
        $this->stub = $stub;

        if (! Str::endsWith($stub, "\n")) {
            // $this->stub .= "\n";
            // $this->virtualEndOfLine = true;
        }

        $this->parts = $this->split();
    }

    public static function render(string $stub, array $replacements): string
    {
        return (new self($stub))->replace($replacements);
    }

    public function replace(array $replacements): string
    {
        $parts = [];
        $toRemove = 0;

        foreach ($this->parts as $part) {
            if ($part->isLiteral()) {
                $parts[] = substr($part->getPayload(), $toRemove);
                $toRemove = 0;
            } elseif ($part->isPlaceholder()) {
                $replacement = Arr::get($replacements, $part->getPayload(), '');

                if (! is_array($replacement)) {
                    $replacement = explode("\n", $replacement);
                }

                $first = true;

                foreach ($replacement as &$line) {
                    $line = rtrim($line, "\r");

                    if ($first) {
                        $first = false;
                    } else {
                        $line = $part->getIndentation() . $line;
                    }
                }

                $replacement = implode("\n", $replacement);

                if (strlen($replacement) === 0) {
                    // We need to remove the characters before and signal that
                    // the next few characters also need to be removed
                    if (count($parts) == 0) {
                        continue;
                    }

                    $idx = count($parts) - 1;

                    if ($part->getAmountToRemoveBefore() > 0) {
                        $parts[$idx] = substr($parts[$idx], 0, -$part->getAmountToRemoveBefore());
                    }

                    $toRemove = $part->getAmountToRemoveAfter();
                } else {
                    $parts[] = $replacement;
                }
            }
        }

        $rendered = implode('', $parts);

        // if ($this->virtualEndOfLine) {
        //     $rendered = substr($rendered, 0, -1);
        // }

        return $rendered;
    }

    /**
     * Split the stub into stub parts.
     *
     * @return StubPart[]
     */
    private function split(): array
    {
        $previousEnd = 0;
        $parts = [];

        while (($pos = strpos($this->stub, '{{', $previousEnd)) !== false) {
            if ($pos > $previousEnd) {
                $parts[] = StubPart::literal(
                    substr($this->stub, $previousEnd, $pos - $previousEnd)
                );
            }

            // Three (or `n`) consecutive curly braces are the escaped version
            // of two (or `n-1`) curly braces in the output. This is handled by
            // emitting a literal part if the very next characters are also
            // curly braces.
            //
            // Note that the A modifier makes it so that the regular expression
            // matches only exactly at `$pos + 2`, as we are only interested in
            // the curly braces right after the (apparently) opening placeholder
            // syntax
            if (preg_match('/\{+/A', $this->stub, $matches, 0, $pos + 2) === 1) {
                $extraBraces = $matches[0];

                $parts[] = StubPart::literal('{' . $extraBraces);

                $previousEnd = $pos + 2 + strlen($extraBraces);

                continue;
            }

            // We found a `{{` in the stub. If the contents between this and the
            // next `}}` is a single word, this is a placeholder that needs to
            // be replaced
            $end = strpos($this->stub, '}}', $pos);

            if ($end === false) {
                throw new StubRenderingException('Unexpected end of file');
            }

            // Advance the two `}` characters
            $end += 2;

            $inner = substr($this->stub, $pos + 2, $end - $pos - 4);

            if (strpos($inner, "\n") !== false) {
                throw new StubRenderingException('Unexpected end of line');
            } elseif (preg_match('/^\s*[a-zA-Z_][a-zA-Z0-9_]*\s*$/', $inner) === 0) {
                throw new StubRenderingException('Placeholders must be valid identifiers');
            }

            $parts[] = StubPart::placeholder(trim($inner))
                ->setIndentation($this->computeIndentation($pos))
                ->setAmountToRemove(...$this->computeAmountToRemove($pos, $end));

            $previousEnd = $end;
        }

        if (strlen($part = substr($this->stub, $previousEnd)) > 0) {
            $parts[] = StubPart::literal($part);
        }

        return $parts;
    }

    private function computeIndentation(int $pos): string
    {
        // Find where the line begins and where the first non whitespace
        // character after the beginning of the line is
        $lastNonSpace = $pos;

        while (true) {
            if ($pos === 0 || substr($this->stub, $pos - 1, 1) === "\n") {
                // Reached the start of the line at position $pos
                break;
            }

            $pos--;

            if (! $this->isWhitespace($pos)) {
                $lastNonSpace = $pos;
            }
        }

        // Return the substring between the two positions
        return substr($this->stub, $pos, $lastNonSpace - $pos);
    }

    private function computeAmountToRemove($pos, $end)
    {
        $originalPos = $pos;
        $originalEnd = $end;

        // Let's backtrack all whitespace. If we're at the beginning of a line
        // we can remove this whitespace.
        while ($pos > 0 && $this->isWhitespace($pos - 1)) {
            $pos--;
        }

        if ($pos === 0 || substr($this->stub, $pos - 1, 1) === "\n") {
            $removeBefore = true;
            $before = $originalPos - $pos;
        } else {
            $removeBefore = false;
            $before = 0;
        }

        // And now in the forward direction
        while ($end < strlen($this->stub) && $this->isWhitespace($end)) {
            $end++;
        }

        if ($end === strlen($this->stub)) {
            $removeAfter = true;
            $after = $end - $originalEnd;
        } elseif (substr($this->stub, $end, 1) === "\n") {
            $removeAfter = true;
            $after = $end - $originalEnd + 1;
        } else {
            $removeAfter = false;
            $after = 0;
        }

        if (! $removeBefore || ! $removeAfter) {
            $before = $after = 0;
        }

        return [$before, $after];
    }

    private function isWhitespace(int $pos): bool
    {
        return in_array(
            substr($this->stub, $pos, 1),
            [' ', "\t"],
            true
        );
    }

    /**
     * Get the parts for this stub, which are the literal strings and the placeholders.
     *
     * @return StubPart[]
     */
    public function parts(): array
    {
        return $this->parts;
    }
}
