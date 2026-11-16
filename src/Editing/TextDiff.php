<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Editing;

/**
 * The changes between two texts, as HTML with <del> and <ins>: by word for a short text, by line
 * for a long one, and as two plain blocks when even the lines are too many to compare.
 */
final class TextDiff
{
    private const MAX_CELLS = 400_000;

    public static function html(string $old, string $new): string
    {
        if ($old === $new) {
            return self::escape($new);
        }

        $words = static fn (string $text): array => \preg_split('/(\s+)/u', $text, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = static fn (string $text): array => \preg_split('/(?<=\n)/u', $text, -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ([$words, $lines] as $split) {
            $a = $split($old);
            $b = $split($new);
            if (\count($a) * \count($b) <= self::MAX_CELLS) {
                return self::render(self::operations($a, $b));
            }
        }

        return '<del>' . self::escape($old) . '</del><ins>' . self::escape($new) . '</ins>';
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     *
     * @return list<array{0: string, 1: string}> ['=', '-' or '+', token]
     */
    private static function operations(array $a, array $b): array
    {
        $n = \count($a);
        $m = \count($b);
        $lcs = \array_fill(0, $n + 1, \array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; --$i) {
            for ($j = $m - 1; $j >= 0; --$j) {
                $lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : \max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $operations = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $operations[] = ['=', $a[$i]];
                ++$i;
                ++$j;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $operations[] = ['-', $a[$i++]];
            } else {
                $operations[] = ['+', $b[$j++]];
            }
        }
        while ($i < $n) {
            $operations[] = ['-', $a[$i++]];
        }
        while ($j < $m) {
            $operations[] = ['+', $b[$j++]];
        }

        return $operations;
    }

    /** @param list<array{0: string, 1: string}> $operations */
    private static function render(array $operations): string
    {
        $html = '';
        $open = null;
        foreach ($operations as [$operation, $token]) {
            $tag = match ($operation) {
                '-' => 'del',
                '+' => 'ins',
                default => null,
            };
            if ($tag !== $open) {
                $html .= (null === $open ? '' : '</' . $open . '>') . (null === $tag ? '' : '<' . $tag . '>');
                $open = $tag;
            }
            $html .= self::escape($token);
        }

        return $html . (null === $open ? '' : '</' . $open . '>');
    }

    private static function escape(string $text): string
    {
        return \htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
