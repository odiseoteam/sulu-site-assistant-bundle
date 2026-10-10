<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Content\Indexing;

use Odiseo\SuluSiteAssistantBundle\Content\ContentSection;
use Odiseo\SuluSiteAssistantBundle\Content\Text;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;

/**
 * Reads the text of a template's properties through its form metadata, so it works on any
 * template without search tags. Text outside blocks is one section; each block item is another,
 * headed by its first single-line text.
 */
final class TemplateTextExtractor
{
    private const TEXT_TYPES = ['text_line', 'text_area', 'text_editor'];

    /** @var array<string, array<string, array<string, FormMetadata>>> form key → locale → template → form */
    private array $forms = [];

    public function __construct(private readonly MetadataProviderInterface $formMetadataProvider)
    {
    }

    /**
     * @param array<string, mixed> $templateData
     *
     * @return list<ContentSection>
     */
    public function sections(string $formKey, string $templateKey, string $locale, array $templateData): array
    {
        $form = $this->form($formKey, $locale)[$templateKey] ?? null;
        if (null === $form) {
            return [];
        }

        $sections = [];
        $loose = [];
        foreach ($form->getFlatFieldMetadata() as $field) {
            $value = $templateData[$field->getName()] ?? null;
            if ('block' === $field->getType() && \is_array($value)) {
                foreach ($value as $item) {
                    $section = \is_array($item) ? $this->blockSection($field, $item) : null;
                    if (null !== $section) {
                        $sections[] = $section;
                    }
                }
            } elseif (null !== ($text = $this->text($field, $value))) {
                $loose[] = $text;
            }
        }

        if ([] !== $loose) {
            \array_unshift($sections, new ContentSection(null, \implode("\n", $loose)));
        }

        return $sections;
    }

    /**
     * The first image set on a top-level media property, for templates without an excerpt image.
     *
     * @param array<string, mixed> $templateData
     */
    public function firstMediaId(string $formKey, string $templateKey, string $locale, array $templateData): ?int
    {
        $form = $this->form($formKey, $locale)[$templateKey] ?? null;
        foreach (null !== $form ? $form->getFlatFieldMetadata() : [] as $field) {
            $value = $templateData[$field->getName()] ?? null;
            if (!\is_array($value) || !\in_array($field->getType(), ['single_media_selection', 'media_selection'], true)) {
                continue;
            }
            $id = $value['id'] ?? (\is_array($value['ids'] ?? null) ? ($value['ids'][0] ?? null) : null);
            if (\is_numeric($id)) {
                return (int) $id;
            }
        }

        return null;
    }

    /** @param array<mixed> $item */
    private function blockSection(FieldMetadata $block, array $item): ?ContentSection
    {
        $type = \is_string($item['type'] ?? null) ? $item['type'] : $block->getDefaultType();
        $typeForm = null !== $type ? ($block->getTypes()[$type] ?? null) : null;
        if (!$typeForm instanceof FormMetadata) {
            return null;
        }

        $heading = null;
        $texts = [];
        foreach ($this->texts($typeForm, $item) as [$fieldType, $text]) {
            if (null === $heading && 'text_line' === $fieldType && \mb_strlen($text) <= 120) {
                $heading = $text;
                continue;
            }
            $texts[] = $text;
        }

        if ([] === $texts) {
            return null === $heading ? null : new ContentSection(null, $heading);
        }

        return new ContentSection($heading, \implode("\n", $texts));
    }

    /**
     * Every text in a block item, nested blocks included, in template order.
     *
     * @param array<mixed> $item
     *
     * @return list<array{string, string}>
     */
    private function texts(FormMetadata $form, array $item): array
    {
        $texts = [];
        foreach ($form->getFlatFieldMetadata() as $field) {
            $value = $item[$field->getName()] ?? null;
            if ('block' === $field->getType() && \is_array($value)) {
                foreach ($value as $nested) {
                    $nestedType = \is_array($nested) && \is_string($nested['type'] ?? null) ? $nested['type'] : $field->getDefaultType();
                    $nestedForm = null !== $nestedType ? ($field->getTypes()[$nestedType] ?? null) : null;
                    if ($nestedForm instanceof FormMetadata && \is_array($nested)) {
                        \array_push($texts, ...$this->texts($nestedForm, $nested));
                    }
                }
            } elseif (null !== ($text = $this->text($field, $value))) {
                $texts[] = [$field->getType(), $text];
            }
        }

        return $texts;
    }

    private function text(FieldMetadata $field, mixed $value): ?string
    {
        if (!\in_array($field->getType(), self::TEXT_TYPES, true) || !\is_string($value)) {
            return null;
        }
        $text = 'text_editor' === $field->getType() ? Text::fromHtml($value) : Text::squash($value);

        return '' === $text ? null : $text;
    }

    /** @return array<string, FormMetadata> */
    private function form(string $formKey, string $locale): array
    {
        if (!isset($this->forms[$formKey][$locale])) {
            $metadata = $this->formMetadataProvider->getMetadata($formKey, $locale, []);
            $this->forms[$formKey][$locale] = $metadata instanceof TypedFormMetadata ? $metadata->getForms() : [];
        }

        return $this->forms[$formKey][$locale];
    }
}
