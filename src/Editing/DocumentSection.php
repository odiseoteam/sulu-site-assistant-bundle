<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Editing;

use Odiseo\AiConversationalAgentBundle\Content\VersionStoreInterface;
use Odiseo\AiConversationalAgentBundle\Knowledge\KnowledgeKind;
use Odiseo\AiConversationalAgentBundle\Knowledge\KnowledgeSourceInterface;
use Odiseo\AiConversationalAgentBundle\Knowledge\VersionedKnowledgeSource;
use Odiseo\AiConversationalAgentBundle\Support\Scalar;

/**
 * The knowledge documents: the Markdown files and the ones written in the admin. The site's pages
 * are edited as pages and read through search_content; they are not listed here.
 */
final class DocumentSection extends AbstractEditableSection
{
    public function __construct(
        VersionStoreInterface $versions,
        private readonly ?KnowledgeSourceInterface $files = null,
        private readonly int $maxGuides = 20,
        private readonly int $maxWhenChars = 200,
    ) {
        parent::__construct($versions);
    }

    public function name(): string
    {
        return 'document';
    }

    public function kind(): string
    {
        return VersionedKnowledgeSource::KIND;
    }

    public function groups(): array
    {
        $groups = [];
        foreach (KnowledgeKind::cases() as $kind) {
            $groups[$kind->value] = 'odiseo_site_assistant.document_kind.' . $kind->value;
        }

        return $groups;
    }

    public function fields(): array
    {
        return [
            'title' => 'odiseo_site_assistant.field.title',
            'kind' => 'odiseo_site_assistant.field.kind',
            'when' => 'odiseo_site_assistant.field.when',
            'body' => 'odiseo_site_assistant.field.body',
            'enabled' => 'odiseo_site_assistant.field.enabled',
        ];
    }

    public function editorFields(): array
    {
        return [
            ['name' => 'title', 'type' => 'text', 'label' => 'odiseo_site_assistant.field.title', 'max' => 120, 'required' => true],
            ['name' => 'kind', 'type' => 'select', 'label' => 'odiseo_site_assistant.field.kind', 'options' => $this->groups()],
            ['name' => 'when', 'type' => 'text', 'label' => 'odiseo_site_assistant.field.when', 'max' => $this->maxWhenChars],
            ['name' => 'body', 'type' => 'markdown', 'label' => 'odiseo_site_assistant.field.body', 'required' => true],
            ['name' => 'enabled', 'type' => 'toggle', 'label' => 'odiseo_site_assistant.field.enabled'],
        ];
    }

    /** The url has no field: the editor keeps the one the document had, from its file or a version. */
    public function normalize(array $submitted): array
    {
        $url = \trim(Scalar::string($submitted['url'] ?? null));

        return [
            'title' => \trim(Scalar::string($submitted['title'] ?? null)),
            'kind' => (KnowledgeKind::tryFrom(Scalar::string($submitted['kind'] ?? null)) ?? KnowledgeKind::Policy)->value,
            'when' => \trim(Scalar::string($submitted['when'] ?? null)),
            'body' => \trim(\str_replace("\r\n", "\n", Scalar::string($submitted['body'] ?? null))),
            'url' => '' === $url ? null : $url,
            'enabled' => true === ($submitted['enabled'] ?? false),
        ];
    }

    public function problems(string $key, array $data, bool $isNew): array
    {
        $problems = [];
        if ($isNew && null !== $this->item($key)) {
            $problems[] = ['odiseo_site_assistant.editing.code_taken', ['%code%' => $key]];
        }
        if ('' === Scalar::string($data['body'] ?? null)) {
            $problems[] = ['odiseo_site_assistant.editing.body_required', []];
        }

        $isGuide = KnowledgeKind::Guide->value === ($data['kind'] ?? null);
        $when = Scalar::string($data['when'] ?? null);
        if ($isGuide && \mb_strlen($when) > $this->maxWhenChars) {
            $problems[] = ['odiseo_site_assistant.editing.when_too_long', ['%count%' => \mb_strlen($when), '%max%' => $this->maxWhenChars]];
        }
        if ($isGuide && true === ($data['enabled'] ?? true)) {
            $guides = 1;
            foreach ($this->items() as $item) {
                if ($item->key !== $key && $item->enabled && $item->isPublished() && KnowledgeKind::Guide->value === $item->group) {
                    ++$guides;
                }
            }
            if ($guides > $this->maxGuides) {
                $problems[] = ['odiseo_site_assistant.editing.too_many_guides', ['%max%' => $this->maxGuides]];
            }
        }

        return $problems;
    }

    protected function configuredItems(): array
    {
        $items = [];
        foreach ($this->files?->documents() ?? [] as $document) {
            $items[$document->id] = VersionedKnowledgeSource::toData($document);
        }

        return $items;
    }

    protected function titleOf(string $key, array $data): string
    {
        return Scalar::string($data['title'] ?? null) ?: $key;
    }

    protected function groupOf(string $key, array $data): string
    {
        return (KnowledgeKind::tryFrom(Scalar::string($data['kind'] ?? null)) ?? KnowledgeKind::Policy)->value;
    }
}
