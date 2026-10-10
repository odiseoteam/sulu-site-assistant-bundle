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

/** "Site assistant" in the admin: conversations, one conversation's transcript, and statistics. */
final class SiteAssistantAdmin extends Admin
{
    public const SECURITY_CONTEXT = 'sulu.site_assistant.conversations';
    public const RESOURCE_KEY = 'site_assistant_conversations';
    public const LIST_VIEW = 'odiseo_site_assistant.conversations';
    public const DETAIL_VIEW = 'odiseo_site_assistant.conversation';
    public const STATISTICS_VIEW = 'odiseo_site_assistant.statistics';

    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly SecurityCheckerInterface $securityChecker,
    ) {
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        if (!$this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            return;
        }

        $assistant = new NavigationItem('odiseo_site_assistant.site_assistant');
        $assistant->setPosition(45);
        $assistant->setIcon('su-comment');

        $statistics = new NavigationItem('odiseo_site_assistant.statistics');
        $statistics->setPosition(10);
        $statistics->setView(self::STATISTICS_VIEW);
        $assistant->addChild($statistics);

        $conversations = new NavigationItem('odiseo_site_assistant.conversations');
        $conversations->setPosition(20);
        $conversations->setView(self::LIST_VIEW);
        $assistant->addChild($conversations);

        $navigationItemCollection->add($assistant);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            return;
        }

        $toolbarActions = $this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::DELETE)
            ? [new ToolbarAction('sulu_admin.delete')]
            : [];

        $viewCollection->add(
            $this->viewBuilderFactory->createListViewBuilder(self::LIST_VIEW, '/site-assistant/conversations')
                ->setResourceKey(self::RESOURCE_KEY)
                ->setListKey(self::RESOURCE_KEY)
                ->setTitle('odiseo_site_assistant.conversations')
                ->addListAdapters(['table'])
                ->setEditView(self::DETAIL_VIEW)
                ->addToolbarActions($toolbarActions),
        );

        $viewCollection->add(
            $this->viewBuilderFactory->createViewBuilder(self::DETAIL_VIEW, '/site-assistant/conversations/:id', self::DETAIL_VIEW)
                ->setOption('backView', self::LIST_VIEW),
        );

        $viewCollection->add(
            $this->viewBuilderFactory->createViewBuilder(self::STATISTICS_VIEW, '/site-assistant/statistics', self::STATISTICS_VIEW),
        );
    }

    public function getSecurityContexts(): array
    {
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'Site assistant' => [
                    self::SECURITY_CONTEXT => [PermissionTypes::VIEW, PermissionTypes::DELETE],
                ],
            ],
        ];
    }
}
