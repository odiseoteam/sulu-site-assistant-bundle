<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Controller\Admin;

use Odiseo\AiConversationalAgentBundle\Content\Version;
use Odiseo\AiConversationalAgentBundle\Content\VersionState;
use Odiseo\AiConversationalAgentBundle\Support\Scalar;
use Odiseo\SuluSiteAssistantBundle\Admin\SiteAssistantAdmin;
use Odiseo\SuluSiteAssistantBundle\Editing\EditableItem;
use Odiseo\SuluSiteAssistantBundle\Editing\EditableSectionInterface;
use Odiseo\SuluSiteAssistantBundle\Editing\Editor;
use Odiseo\SuluSiteAssistantBundle\Editing\TextDiff;
use Psr\Container\ContainerInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The house rules and the knowledge documents, edited with versions: the list, one item with its
 * draft and history, saving a draft, publishing, going back to the configured item, copying an
 * old version to the draft, and what a version changed. The `section` route attribute picks
 * rules or documents.
 */
final class EditableController implements SecuredControllerInterface
{
    private const KEY_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    public function __construct(
        private readonly ContainerInterface $sections,
        private readonly Editor $editor,
        private readonly TranslatorInterface $translator,
        private readonly ?Security $security = null,
    ) {
    }

    public function cgetAction(string $section, Request $request): Response
    {
        $editable = $this->section($section);
        $groups = $editable->groups();
        $locale = $this->uiLocale($request);

        $rows = \array_map(fn (EditableItem $item): array => [
            'id' => $item->key,
            'title' => $item->title,
            'group' => $this->translator->trans($groups[$item->group] ?? $item->group, [], 'admin', $locale),
            'origin' => $this->translator->trans('odiseo_site_assistant.origin.' . $item->origin->value, [], 'admin', $locale),
            'version' => $item->number,
            'enabled' => $item->enabled,
            'draft' => null !== $item->draft,
            'changed' => $item->lastChange()?->at->format(\DATE_ATOM),
            'changer' => $item->lastChange()?->author,
        ], $editable->items());

        return new JsonResponse([
            '_embedded' => [self::resourceKey($section) => $rows],
            'total' => \count($rows),
            'page' => 1,
            'pages' => 1,
            'limit' => \max(1, \count($rows)),
        ]);
    }

    public function getAction(string $section, string $id): Response
    {
        $editable = $this->section($section);
        $item = $editable->item($id);

        return null === $item ? new JsonResponse(null, Response::HTTP_NOT_FOUND) : new JsonResponse($this->serialize($editable, $item));
    }

    /** A new item: its key and data in the body, saved as a draft or published. */
    public function postAction(string $section, Request $request): Response
    {
        $editable = $this->section($section);
        $body = Scalar::keyed($request->toArray());
        $key = \trim(Scalar::string($body['id'] ?? null));
        if (1 !== \preg_match(self::KEY_PATTERN, $key)) {
            return $this->problems([['odiseo_site_assistant.editing.code_invalid', []]], $request);
        }
        if (null !== $editable->item($key)) {
            return $this->problems([['odiseo_site_assistant.editing.code_taken', ['%code%' => $key]]], $request);
        }

        return $this->write($editable, $key, $body, (string) $request->query->get('action', 'draft'), true, $request);
    }

    public function putAction(string $section, string $id, Request $request): Response
    {
        $editable = $this->section($section);
        if (null === $editable->item($id)) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $body = '' === $request->getContent() ? [] : Scalar::keyed($request->toArray());
        $action = (string) $request->query->get('action', 'draft');
        $author = $this->author();

        switch ($action) {
            case 'discard':
                $this->editor->discardDraft($editable, $id);
                break;
            case 'reset':
                if (null === $this->editor->reset($editable, $id, $author, self::note($body))) {
                    return $this->problems([['odiseo_site_assistant.editing.no_default', []]], $request);
                }
                break;
            case 'restore':
                if (!$this->editor->restore($editable, $id, Scalar::int($body['version'] ?? $request->query->get('version')), $author)) {
                    return new JsonResponse(null, Response::HTTP_NOT_FOUND);
                }
                break;
            default:
                return $this->write($editable, $id, $body, $action, false, $request);
        }

        $item = $editable->item($id);

        return null === $item ? new JsonResponse(null, Response::HTTP_NO_CONTENT) : new JsonResponse($this->serialize($editable, $item));
    }

    /** One version as it was, and its changes against the version in use, field by field. */
    public function versionAction(string $section, string $id, int $number): Response
    {
        $editable = $this->section($section);
        $item = $editable->item($id);
        $version = null === $item ? null : $this->editor->version($editable, $id, $number);
        $data = null === $version ? null : $this->editor->dataOf($editable, $id, $number);
        if (null === $item || null === $version || null === $data) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $diff = [];
        foreach (\array_keys($editable->fields()) as $field) {
            $diff[$field] = TextDiff::html(self::text($item->data[$field] ?? null), self::text($data[$field] ?? null));
        }

        return new JsonResponse([
            'number' => $version->number,
            'data' => $data,
            'diff' => $diff,
        ] + self::version($version));
    }

    public function getSecurityContext(): string
    {
        return SiteAssistantAdmin::KNOWLEDGE_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        return null;
    }

    /** @param array<string, mixed> $body */
    private function write(EditableSectionInterface $editable, string $key, array $body, string $action, bool $isNew, Request $request): Response
    {
        $data = $editable->normalize(Scalar::keyed($body['data'] ?? $body));
        // The url has no field: a document keeps the one it had.
        if (\array_key_exists('url', $data) && null === $data['url']) {
            $data['url'] = $editable->item($key)?->data['url'] ?? null;
        }

        if ('publish' === $action) {
            $result = $this->editor->publish($editable, $key, $data, $this->author(), self::note($body), $isNew);
            if (\is_array($result)) {
                return $this->problems($result, $request);
            }
        } elseif ('draft' === $action) {
            $this->editor->saveDraft($editable, $key, $data, $this->author());
        } else {
            return new JsonResponse(['code' => 'unknown_action', 'message' => \sprintf('Unknown action "%s".', $action)], Response::HTTP_BAD_REQUEST);
        }

        $item = $editable->item($key);

        return null === $item ? new JsonResponse(null, Response::HTTP_NOT_FOUND) : new JsonResponse($this->serialize($editable, $item), $isNew ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /** @return array<string, mixed> */
    private function serialize(EditableSectionInterface $editable, EditableItem $item): array
    {
        return [
            'id' => $item->key,
            'title' => $item->title,
            'group' => $item->group,
            'origin' => $item->origin->value,
            'version' => $item->number,
            'enabled' => $item->enabled,
            'hasDefault' => $item->hasDefault,
            'data' => $item->editableData(),
            'published' => $item->data,
            'draft' => null === $item->draft ? null : self::version($item->draft),
            'history' => \array_map(self::version(...), $this->editor->history($editable, $item->key)),
            'fields' => $editable->editorFields(),
        ];
    }

    /** @return array{number: int, state: string, author: ?string, note: ?string, at: ?string, isDefault: bool} */
    private static function version(Version $version): array
    {
        return [
            'number' => $version->number,
            'state' => $version->state->value,
            'author' => $version->author,
            'note' => $version->note,
            // Version 1 of a configured item has no date: it comes from the configuration.
            'at' => VersionState::Published === $version->state && 0 === $version->at->getTimestamp() ? null : $version->at->format(\DATE_ATOM),
            'isDefault' => $version->isDefault(),
        ];
    }

    /** @param list<array{0: string, 1: array<string, string|int>}> $problems */
    private function problems(array $problems, Request $request): Response
    {
        $locale = $this->uiLocale($request);
        $messages = \array_map(fn (array $problem): string => $this->translator->trans($problem[0], $problem[1], 'admin', $locale), $problems);

        return new JsonResponse(['code' => 'not_publishable', 'message' => \implode(' ', $messages), 'problems' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function section(string $section): EditableSectionInterface
    {
        $name = \rtrim($section, 's');
        if (!$this->sections->has($name)) {
            throw new NotFoundHttpException(\sprintf('No editable section "%s".', $section));
        }

        /** @var EditableSectionInterface */
        return $this->sections->get($name);
    }

    private static function resourceKey(string $section): string
    {
        return 'rules' === $section ? SiteAssistantAdmin::RULES_KEY : SiteAssistantAdmin::DOCUMENTS_KEY;
    }

    private function author(): ?string
    {
        return $this->security?->getUser()?->getUserIdentifier();
    }

    private function uiLocale(Request $request): string
    {
        $user = $this->security?->getUser();
        if (null !== $user && \method_exists($user, 'getLocale')) {
            $locale = $user->getLocale();
            if (\is_string($locale) && '' !== $locale) {
                return $locale;
            }
        }

        return $request->getLocale();
    }

    /** @param array<string, mixed> $body */
    private static function note(array $body): ?string
    {
        $note = \trim(Scalar::string($body['note'] ?? null));

        return '' === $note ? null : \mb_substr($note, 0, 255);
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            \is_bool($value) => $value ? 'yes' : 'no',
            \is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
