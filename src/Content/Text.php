<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Content;

/** Plain text out of editor HTML, and the folding used to match words across accents and case. */
final class Text
{
    private const STOPWORDS = [
        'a', 'an', 'and', 'are', 'can', 'de', 'del', 'do', 'el', 'en', 'es', 'for', 'how', 'in', 'is',
        'la', 'las', 'los', 'me', 'mi', 'my', 'of', 'on', 'or', 'para', 'por', 'que', 'se', 'the', 'to',
        'un', 'una', 'what', 'with', 'y', 'you',
    ];

    public static function fromHtml(string $html): string
    {
        // Block boundaries become line breaks so paragraphs and list items stay apart.
        $html = (string) \preg_replace('~<(br|/p|/li|/h[1-6]|/div|/blockquote|/tr)\b[^>]*>~i', "\n", $html);
        $text = \html_entity_decode(\strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return self::squash($text);
    }

    public static function squash(string $text): string
    {
        $text = (string) \preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = (string) \preg_replace('/ *\n[ \n]*/', "\n", $text);

        return \trim($text);
    }

    public static function fold(string $text): string
    {
        $folded = \transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);

        return \is_string($folded) ? $folded : \mb_strtolower($text);
    }

    /** @return list<string> */
    public static function terms(string $text): array
    {
        $words = \preg_split('/[^a-z0-9]+/', self::fold($text), -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        return \array_values(\array_unique(\array_filter(
            $words,
            static fn (string $w): bool => \mb_strlen($w) > 1 && !\in_array($w, self::STOPWORDS, true),
        )));
    }

    public static function truncate(string $text, int $maxChars): string
    {
        if (\mb_strlen($text) <= $maxChars) {
            return $text;
        }
        $cut = \mb_substr($text, 0, $maxChars);
        $space = \mb_strrpos($cut, ' ');

        return \rtrim(false !== $space && $space > $maxChars * 0.6 ? \mb_substr($cut, 0, $space) : $cut, ' ,.;:') . '…';
    }

    /**
     * The section that shares most terms with the query, cut to size. Null when none share any.
     *
     * @param list<ContentSection> $sections
     */
    public static function bestPassage(array $sections, string $query, int $maxChars): ?string
    {
        $terms = self::terms($query);
        if ([] === $terms) {
            return null;
        }

        $best = null;
        $bestScore = 0;
        foreach ($sections as $section) {
            $haystack = ' ' . \implode(' ', self::terms(($section->heading ?? '') . ' ' . $section->text)) . ' ';
            $score = 0;
            foreach ($terms as $term) {
                $score += \str_contains($haystack, ' ' . $term) ? 1 : 0;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $section;
            }
        }

        if (null === $best) {
            return null;
        }

        $text = null !== $best->heading ? $best->heading . ': ' . $best->text : $best->text;

        return self::truncate(\str_replace("\n", ' ', $text), $maxChars);
    }
}
