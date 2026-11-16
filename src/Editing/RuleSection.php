<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Editing;

use Odiseo\AiConversationalAgentBundle\Content\VersionStoreInterface;
use Odiseo\AiConversationalAgentBundle\Rule\RuleCategory;
use Odiseo\AiConversationalAgentBundle\Rule\RuleSourceInterface;
use Odiseo\AiConversationalAgentBundle\Rule\VersionedRuleSource;
use Odiseo\AiConversationalAgentBundle\Support\Scalar;

/** The house rules: the configured ones and the ones written in the admin, with their caps. */
final class RuleSection extends AbstractEditableSection
{
    public function __construct(
        VersionStoreInterface $versions,
        private readonly RuleSourceInterface $configured,
        private readonly int $maxRules = 20,
        private readonly int $maxRuleChars = 400,
    ) {
        parent::__construct($versions);
    }

    public function name(): string
    {
        return 'rule';
    }

    public function kind(): string
    {
        return VersionedRuleSource::KIND;
    }

    public function groups(): array
    {
        $groups = [];
        foreach (RuleCategory::cases() as $category) {
            $groups[$category->value] = 'odiseo_site_assistant.rule_category.' . $category->value;
        }

        return $groups;
    }

    public function fields(): array
    {
        return [
            'title' => 'odiseo_site_assistant.field.title',
            'category' => 'odiseo_site_assistant.field.category',
            'text' => 'odiseo_site_assistant.field.text',
            'enabled' => 'odiseo_site_assistant.field.enabled',
        ];
    }

    public function editorFields(): array
    {
        return [
            ['name' => 'title', 'type' => 'text', 'label' => 'odiseo_site_assistant.field.title', 'max' => 120, 'required' => true],
            ['name' => 'category', 'type' => 'select', 'label' => 'odiseo_site_assistant.field.category', 'options' => $this->groups()],
            ['name' => 'text', 'type' => 'textarea', 'label' => 'odiseo_site_assistant.field.text', 'max' => $this->maxRuleChars, 'required' => true],
            ['name' => 'enabled', 'type' => 'toggle', 'label' => 'odiseo_site_assistant.field.enabled'],
        ];
    }

    public function normalize(array $submitted): array
    {
        return [
            'title' => \trim(Scalar::string($submitted['title'] ?? null)),
            'category' => (RuleCategory::tryFrom(Scalar::string($submitted['category'] ?? null)) ?? RuleCategory::Other)->value,
            'text' => \trim(Scalar::string($submitted['text'] ?? null)),
            'enabled' => true === ($submitted['enabled'] ?? false),
        ];
    }

    public function maxRules(): int
    {
        return $this->maxRules;
    }

    public function problems(string $key, array $data, bool $isNew): array
    {
        $problems = [];
        if ($isNew && null !== $this->item($key)) {
            $problems[] = ['odiseo_site_assistant.editing.code_taken', ['%code%' => $key]];
        }
        $text = Scalar::string($data['text'] ?? null);
        if ('' === $text) {
            $problems[] = ['odiseo_site_assistant.editing.text_required', []];
        } elseif (\mb_strlen($text) > $this->maxRuleChars) {
            $problems[] = ['odiseo_site_assistant.editing.rule_too_long', ['%count%' => \mb_strlen($text), '%max%' => $this->maxRuleChars]];
        }

        if (true === ($data['enabled'] ?? true)) {
            $enabled = 1;
            foreach ($this->items() as $item) {
                if ($item->key !== $key && $item->enabled && $item->isPublished()) {
                    ++$enabled;
                }
            }
            if ($enabled > $this->maxRules) {
                $problems[] = ['odiseo_site_assistant.editing.too_many_rules', ['%max%' => $this->maxRules]];
            }
        }

        return $problems;
    }

    protected function configuredItems(): array
    {
        $items = [];
        foreach ($this->configured->rules() as $rule) {
            $items[$rule->id] = VersionedRuleSource::toData($rule);
        }

        return $items;
    }

    protected function titleOf(string $key, array $data): string
    {
        return Scalar::string($data['title'] ?? null) ?: $key;
    }

    protected function groupOf(string $key, array $data): string
    {
        return (RuleCategory::tryFrom(Scalar::string($data['category'] ?? null)) ?? RuleCategory::Other)->value;
    }
}
