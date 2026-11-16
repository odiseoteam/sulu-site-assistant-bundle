<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Unit\Content;

use Odiseo\SuluSiteAssistantBundle\Content\Indexing\TemplateTextExtractor;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;

final class TemplateTextExtractorTest extends TestCase
{
    public function testTextOutsideBlocksIsOneSectionAndEachBlockItemAnother(): void
    {
        $extractor = new TemplateTextExtractor($this->provider());

        $sections = $extractor->sections('page', 'service', 'en', [
            'title' => 'Sylius Development',
            'intro' => '<p>We build <b>marketplaces</b>.</p>',
            'cta_url' => 'https://example.test',
            'faq' => [
                ['type' => 'question', 'question' => 'Do you do B2B?', 'answer' => '<p>Yes, catalogs and quotes.</p>'],
                ['type' => 'question', 'question' => 'Empty answer', 'answer' => ''],
            ],
        ]);

        self::assertCount(3, $sections);
        self::assertNull($sections[0]->heading);
        self::assertSame("Sylius Development\nWe build marketplaces.", $sections[0]->text);
        self::assertSame('Do you do B2B?', $sections[1]->heading);
        self::assertSame('Yes, catalogs and quotes.', $sections[1]->text);
        self::assertSame('Empty answer', $sections[2]->text);
    }

    public function testAnUnknownTemplateHasNoText(): void
    {
        self::assertSame([], (new TemplateTextExtractor($this->provider()))->sections('page', 'nope', 'en', ['title' => 'x']));
    }

    private function provider(): MetadataProviderInterface
    {
        $question = new FormMetadata();
        $question->setKey('question');
        $question->addItem(self::field('question', 'text_line'));
        $question->addItem(self::field('answer', 'text_editor'));

        $faq = self::field('faq', 'block');
        $faq->addType($question);

        $form = new FormMetadata();
        $form->setKey('service');
        $form->addItem(self::field('title', 'text_line'));
        $form->addItem(self::field('intro', 'text_editor'));
        $form->addItem(self::field('cta_url', 'url'));
        $form->addItem($faq);

        $typed = new TypedFormMetadata();
        $typed->addForm('service', $form);

        $provider = $this->createStub(MetadataProviderInterface::class);
        $provider->method('getMetadata')->willReturn($typed);

        return $provider;
    }

    private static function field(string $name, string $type): FieldMetadata
    {
        $field = new FieldMetadata($name);
        $field->setType($type);

        return $field;
    }
}
