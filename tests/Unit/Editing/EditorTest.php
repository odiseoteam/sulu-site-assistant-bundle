<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Unit\Editing;

use Odiseo\AiConversationalAgentBundle\Content\InMemoryVersionStore;
use Odiseo\AiConversationalAgentBundle\Rule\ConfiguredRuleSource;
use Odiseo\SuluSiteAssistantBundle\Editing\Editor;
use Odiseo\SuluSiteAssistantBundle\Editing\Origin;
use Odiseo\SuluSiteAssistantBundle\Editing\RuleSection;
use Odiseo\SuluSiteAssistantBundle\Editing\TextDiff;
use PHPUnit\Framework\TestCase;

final class EditorTest extends TestCase
{
    private InMemoryVersionStore $versions;

    private RuleSection $rules;

    private Editor $editor;

    protected function setUp(): void
    {
        $this->versions = new InMemoryVersionStore();
        $this->rules = new RuleSection($this->versions, new ConfiguredRuleSource([
            'no-discounts' => ['title' => 'No discounts', 'category' => 'sales', 'text' => 'Do not offer discounts.', 'enabled' => true],
        ]), maxRules: 2, maxRuleChars: 60);
        $this->editor = new Editor($this->versions);
    }

    public function testAConfiguredRuleIsVersionOneUntilEdited(): void
    {
        $item = $this->rules->item('no-discounts');

        self::assertNotNull($item);
        self::assertSame(Origin::File, $item->origin);
        self::assertSame(1, $item->number);
        self::assertSame('sales', $item->group);
    }

    public function testADraftDoesNotChangeWhatIsInUseUntilItIsPublished(): void
    {
        $data = $this->rules->normalize(['title' => 'No discounts', 'category' => 'sales', 'text' => 'Never offer discounts or free work.', 'enabled' => true]);
        $this->editor->saveDraft($this->rules, 'no-discounts', $data, 'ana');

        $item = $this->rules->item('no-discounts');
        self::assertNotNull($item?->draft);
        self::assertSame('Do not offer discounts.', $item->data['text']);
        self::assertSame('Never offer discounts or free work.', $item->editableData()['text']);

        $published = $this->editor->publish($this->rules, 'no-discounts', $data, 'ana', 'Stricter');
        self::assertInstanceOf(\Odiseo\AiConversationalAgentBundle\Content\Version::class, $published);
        $item = $this->rules->item('no-discounts');
        self::assertSame(Origin::FileEdited, $item?->origin);
        self::assertSame('Never offer discounts or free work.', $item->data['text']);
        self::assertSame([2, 1], \array_map(static fn ($version): int => $version->number, $this->editor->history($this->rules, 'no-discounts')));
    }

    public function testGoingBackToTheConfiguredRule(): void
    {
        $this->editor->publish($this->rules, 'no-discounts', $this->rules->normalize(['title' => 'X', 'text' => 'Changed.', 'enabled' => true]), 'ana', null);
        $this->editor->reset($this->rules, 'no-discounts', 'ana');

        self::assertSame(Origin::File, $this->rules->item('no-discounts')?->origin);
        self::assertSame('Do not offer discounts.', $this->rules->item('no-discounts')->data['text']);
    }

    public function testAnOldVersionIsCopiedToTheDraft(): void
    {
        $this->editor->publish($this->rules, 'no-discounts', $this->rules->normalize(['title' => 'X', 'text' => 'Changed.', 'enabled' => true]), 'ana', null);

        self::assertTrue($this->editor->restore($this->rules, 'no-discounts', 1, 'ana'));
        self::assertSame('Do not offer discounts.', $this->rules->item('no-discounts')?->editableData()['text']);
        self::assertFalse($this->editor->restore($this->rules, 'no-discounts', 9, 'ana'));
    }

    public function testTheCapsStopAPublication(): void
    {
        $long = $this->editor->publish($this->rules, 'new', $this->rules->normalize(['title' => 'Long', 'text' => \str_repeat('a', 61), 'enabled' => true]), 'ana', null, true);
        self::assertIsArray($long);
        self::assertSame('odiseo_site_assistant.editing.rule_too_long', $long[0][0]);

        $this->editor->publish($this->rules, 'second', $this->rules->normalize(['title' => 'Second', 'text' => 'Two.', 'enabled' => true]), 'ana', null, true);
        $third = $this->editor->publish($this->rules, 'third', $this->rules->normalize(['title' => 'Third', 'text' => 'Three.', 'enabled' => true]), 'ana', null, true);
        self::assertIsArray($third);
        self::assertSame('odiseo_site_assistant.editing.too_many_rules', $third[0][0]);

        $taken = $this->editor->publish($this->rules, 'second', $this->rules->normalize(['title' => 'Again', 'text' => 'Two.', 'enabled' => false]), 'ana', null, true);
        self::assertIsArray($taken);
        self::assertSame('odiseo_site_assistant.editing.code_taken', $taken[0][0]);
    }

    public function testTheDiffMarksWhatChanged(): void
    {
        self::assertSame('Do not <del>offer</del><ins>give</ins> discounts.', TextDiff::html('Do not offer discounts.', 'Do not give discounts.'));
        self::assertSame('&lt;b&gt;', TextDiff::html('<b>', '<b>'));
    }

    public function testTheEditorFieldsFollowTheCaps(): void
    {
        $fields = \array_column($this->rules->editorFields(), null, 'name');

        self::assertSame(60, $fields['text']['max'] ?? null);
        self::assertSame('select', $fields['category']['type']);
    }
}
