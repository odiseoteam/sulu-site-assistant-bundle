<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

/**
 * "Site assistant" in the admin: statistics, conversations with their transcripts, the cases
 * handed to the team, the house rules and the knowledge with their versions, and the settings of
 * each webspace. Each part has its own permission.
 */
final class SiteAssistantAdmin extends Admin
{
    public const CONVERSATIONS_CONTEXT = 'sulu.site_assistant.conversations';

    public const HANDOFFS_CONTEXT = 'sulu.site_assistant.handoffs';

    public const KNOWLEDGE_CONTEXT = 'sulu.site_assistant.knowledge';

    public const SETTINGS_CONTEXT = 'sulu.site_assistant.settings';

    public const CONVERSATIONS_KEY = 'site_assistant_conversations';

    public const HANDOFFS_KEY = 'site_assistant_handoffs';

    public const RULES_KEY = 'site_assistant_rules';

    public const DOCUMENTS_KEY = 'site_assistant_documents';

    public const SETTINGS_KEY = 'site_assistant_settings';

    public const STATISTICS_KEY = 'site_assistant_statistics';

    public const STATISTICS_VIEW = 'odiseo_site_assistant.statistics';

    public const CONVERSATIONS_VIEW = 'odiseo_site_assistant.conversations';

    public const CONVERSATION_VIEW = 'odiseo_site_assistant.conversation';

    public const HANDOFFS_VIEW = 'odiseo_site_assistant.handoffs';

    public const HANDOFF_VIEW = 'odiseo_site_assistant.handoff';

    public const RULES_VIEW = 'odiseo_site_assistant.rules';

    public const RULE_VIEW = 'odiseo_site_assistant.rule';

    public const DOCUMENTS_VIEW = 'odiseo_site_assistant.documents';

    public const DOCUMENT_VIEW = 'odiseo_site_assistant.document';

    public const SETTINGS_LIST_VIEW = 'odiseo_site_assistant.settings_list';

    public const SETTINGS_TABS_VIEW = 'odiseo_site_assistant.settings';

    public const SETTINGS_FORM_VIEW = 'odiseo_site_assistant.settings.form';

    /** @param list<string> $locales the admin's content locales, for the settings' texts */
    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly array $locales = [],
    ) {
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        $children = [];
        if ($this->can(self::CONVERSATIONS_CONTEXT)) {
            $children[] = $this->item('odiseo_site_assistant.statistics', self::STATISTICS_VIEW, 10);
            $children[] = $this->item('odiseo_site_assistant.conversations', self::CONVERSATIONS_VIEW, 20);
        }
        if ($this->can(self::HANDOFFS_CONTEXT)) {
            $children[] = $this->item('odiseo_site_assistant.handoffs', self::HANDOFFS_VIEW, 30);
        }
        if ($this->can(self::KNOWLEDGE_CONTEXT)) {
            $children[] = $this->item('odiseo_site_assistant.rules', self::RULES_VIEW, 40);
            $children[] = $this->item('odiseo_site_assistant.documents', self::DOCUMENTS_VIEW, 50);
        }
        if ($this->can(self::SETTINGS_CONTEXT)) {
            $children[] = $this->item('odiseo_site_assistant.settings', self::SETTINGS_LIST_VIEW, 60);
        }
        if ([] === $children) {
            return;
        }

        $assistant = new NavigationItem('odiseo_site_assistant.site_assistant');
        $assistant->setPosition(45);
        $assistant->setIcon('su-comment');
        foreach ($children as $child) {
            $assistant->addChild($child);
        }
        $navigationItemCollection->add($assistant);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if ($this->can(self::CONVERSATIONS_CONTEXT)) {
            $viewCollection->add(
                $this->viewBuilderFactory->createViewBuilder(self::STATISTICS_VIEW, '/site-assistant/statistics', self::STATISTICS_VIEW),
            );
            $viewCollection->add(
                $this->viewBuilderFactory->createListViewBuilder(self::CONVERSATIONS_VIEW, '/site-assistant/conversations')
                    ->setResourceKey(self::CONVERSATIONS_KEY)
                    ->setListKey(self::CONVERSATIONS_KEY)
                    ->setTitle('odiseo_site_assistant.conversations')
                    ->addListAdapters(['table'])
                    ->setEditView(self::CONVERSATION_VIEW)
                    ->addToolbarActions($this->can(self::CONVERSATIONS_CONTEXT, PermissionTypes::DELETE) ? [new ToolbarAction('sulu_admin.delete')] : []),
            );
            $viewCollection->add(
                $this->viewBuilderFactory->createViewBuilder(self::CONVERSATION_VIEW, '/site-assistant/conversations/:id', self::CONVERSATION_VIEW)
                    ->setOption('backView', self::CONVERSATIONS_VIEW)
                    ->setOption('handoffView', self::HANDOFF_VIEW)
                    ->setOption('canDelete', $this->can(self::CONVERSATIONS_CONTEXT, PermissionTypes::DELETE)),
            );
        }

        if ($this->can(self::HANDOFFS_CONTEXT)) {
            $viewCollection->add(
                $this->viewBuilderFactory->createListViewBuilder(self::HANDOFFS_VIEW, '/site-assistant/handoffs')
                    ->setResourceKey(self::HANDOFFS_KEY)
                    ->setListKey(self::HANDOFFS_KEY)
                    ->setTitle('odiseo_site_assistant.handoffs')
                    ->addListAdapters(['table'])
                    ->setEditView(self::HANDOFF_VIEW),
            );
            $viewCollection->add(
                $this->viewBuilderFactory->createViewBuilder(self::HANDOFF_VIEW, '/site-assistant/handoffs/:id', self::HANDOFF_VIEW)
                    ->setOption('backView', self::HANDOFFS_VIEW)
                    ->setOption('conversationView', $this->can(self::CONVERSATIONS_CONTEXT) ? self::CONVERSATION_VIEW : null)
                    ->setOption('canEdit', $this->can(self::HANDOFFS_CONTEXT, PermissionTypes::EDIT)),
            );
        }

        if ($this->can(self::KNOWLEDGE_CONTEXT)) {
            foreach ([
                [self::RULES_VIEW, self::RULE_VIEW, self::RULES_KEY, 'rules', 'odiseo_site_assistant.rules'],
                [self::DOCUMENTS_VIEW, self::DOCUMENT_VIEW, self::DOCUMENTS_KEY, 'documents', 'odiseo_site_assistant.documents'],
            ] as [$listView, $itemView, $key, $path, $title]) {
                $actions = $this->can(self::KNOWLEDGE_CONTEXT, PermissionTypes::ADD) ? [new ToolbarAction('sulu_admin.add')] : [];
                $viewCollection->add(
                    $this->viewBuilderFactory->createListViewBuilder($listView, '/site-assistant/' . $path)
                        ->setResourceKey($key)
                        ->setListKey($key)
                        ->setTitle($title)
                        ->addListAdapters(['table'])
                        ->setAddView($itemView)
                        ->setEditView($itemView)
                        ->disableSearching()
                        ->disablePagination()
                        ->addToolbarActions($actions),
                );
                $viewCollection->add(
                    $this->viewBuilderFactory->createViewBuilder($itemView, '/site-assistant/' . $path . '/:id?', 'odiseo_site_assistant.editable_item')
                        ->setOption('resourceKey', $key)
                        ->setOption('backView', $listView)
                        ->setOption('canEdit', $this->can(self::KNOWLEDGE_CONTEXT, PermissionTypes::EDIT))
                        ->setOption('canAdd', $this->can(self::KNOWLEDGE_CONTEXT, PermissionTypes::ADD)),
                );
            }
        }

        if ($this->can(self::SETTINGS_CONTEXT)) {
            $viewCollection->add(
                $this->viewBuilderFactory->createListViewBuilder(self::SETTINGS_LIST_VIEW, '/site-assistant/settings/:locale')
                    ->setResourceKey(self::SETTINGS_KEY)
                    ->setListKey(self::SETTINGS_KEY)
                    ->setTitle('odiseo_site_assistant.settings')
                    ->addListAdapters(['table'])
                    ->disableSearching()
                    ->disableSelection()
                    ->disablePagination()
                    ->addLocales($this->locales)
                    ->setEditView(self::SETTINGS_FORM_VIEW),
            );
            $tabs = $this->viewBuilderFactory->createResourceTabViewBuilder(self::SETTINGS_TABS_VIEW, '/site-assistant/settings/:locale/:id')
                ->setResourceKey(self::SETTINGS_KEY)
                ->setBackView(self::SETTINGS_LIST_VIEW)
                ->setTitleProperty('webspaceName');
            if ([] !== $this->locales) {
                $tabs->addLocales($this->locales);
            }
            $viewCollection->add($tabs);
            $viewCollection->add(
                $this->viewBuilderFactory->createFormViewBuilder(self::SETTINGS_FORM_VIEW, '/details')
                    ->setResourceKey(self::SETTINGS_KEY)
                    ->setFormKey(self::SETTINGS_KEY)
                    ->setTabTitle('sulu_admin.details')
                    ->addToolbarActions($this->can(self::SETTINGS_CONTEXT, PermissionTypes::EDIT) ? [new ToolbarAction('sulu_admin.save')] : [])
                    ->setParent(self::SETTINGS_TABS_VIEW),
            );
        }
    }

    public function getSecurityContexts(): array
    {
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'Site assistant' => [
                    self::CONVERSATIONS_CONTEXT => [PermissionTypes::VIEW, PermissionTypes::DELETE],
                    self::HANDOFFS_CONTEXT => [PermissionTypes::VIEW, PermissionTypes::EDIT],
                    self::KNOWLEDGE_CONTEXT => [PermissionTypes::VIEW, PermissionTypes::ADD, PermissionTypes::EDIT],
                    self::SETTINGS_CONTEXT => [PermissionTypes::VIEW, PermissionTypes::EDIT],
                ],
            ],
        ];
    }

    private function can(string $context, string $permission = PermissionTypes::VIEW): bool
    {
        return $this->securityChecker->hasPermission($context, $permission);
    }

    private function item(string $label, string $view, int $position): NavigationItem
    {
        $item = new NavigationItem($label);
        $item->setView($view);
        $item->setPosition($position);

        return $item;
    }
}
