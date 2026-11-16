<?php

declare(strict_types=1);

use Odiseo\SuluSiteAssistantBundle\Admin\SiteAssistantAdmin;
use Odiseo\SuluSiteAssistantBundle\Controller\Admin\ConversationController;
use Odiseo\SuluSiteAssistantBundle\Controller\Admin\EditableController;
use Odiseo\SuluSiteAssistantBundle\Controller\Admin\HandoffController;
use Odiseo\SuluSiteAssistantBundle\Controller\Admin\SettingsController;
use Odiseo\SuluSiteAssistantBundle\Controller\Admin\StatisticsController;
use Odiseo\SuluSiteAssistantBundle\Statistics\StatisticsProvider;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_locator;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

// The "Site assistant" section of the admin and its API, routed only in the admin context.
return static function (ContainerConfigurator $container): void {
    $id = 'odiseo_sulu_site_assistant.admin.';
    $site = 'odiseo_sulu_site_assistant.';
    $services = $container->services();

    $services->set($id . 'admin', SiteAssistantAdmin::class)
        ->args([
            service(ViewBuilderFactoryInterface::class),
            service(SecurityCheckerInterface::class),
            param('sulu_core.locales'),
        ])
        ->tag('sulu.admin')
        ->tag('sulu.context', ['context' => 'admin']);

    $services->set($id . 'statistics_provider', StatisticsProvider::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            param($site . 'model.conversation.class'),
            param($site . 'model.spend_entry.class'),
            param($site . 'model.handoff.class'),
        ]);

    $services->set($id . 'conversation_controller', ConversationController::class)
        ->public()
        ->args([
            service('doctrine.orm.entity_manager'),
            service(FieldDescriptorFactoryInterface::class),
            service(DoctrineListBuilderFactoryInterface::class),
            service(RestHelperInterface::class),
            service(NormalizerInterface::class),
            service($site . 'repository.conversation'),
            service($site . 'repository.handoff'),
            service($site . 'conversation.transcript'),
            param($site . 'model.conversation.class'),
            param($site . 'model.memory_fact.class'),
            param($site . 'model.spend_entry.class'),
        ])
        ->tag('sulu.context', ['context' => 'admin']);

    $services->set($id . 'handoff_controller', HandoffController::class)
        ->public()
        ->args([
            service('doctrine.orm.entity_manager'),
            service(FieldDescriptorFactoryInterface::class),
            service(DoctrineListBuilderFactoryInterface::class),
            service(RestHelperInterface::class),
            service(NormalizerInterface::class),
            service($site . 'repository.handoff'),
            service('event_dispatcher'),
            param($site . 'model.handoff.class'),
        ])
        ->tag('sulu.context', ['context' => 'admin']);

    $services->set($id . 'statistics_controller', StatisticsController::class)
        ->public()
        ->args([service($id . 'statistics_provider'), service($site . 'site.webspaces'), param('odiseo_ai_conversational_agent.timezone')])
        ->tag('sulu.context', ['context' => 'admin']);

    $services->set($id . 'settings_controller', SettingsController::class)
        ->public()
        ->args([
            service($site . 'repository.settings'),
            service($site . 'site.webspaces'),
            service('doctrine.orm.entity_manager'),
            service($site . 'encryption.api_key'),
            service('security.helper')->nullOnInvalid(),
            [
                'turnModel' => param('odiseo_ai_conversational_agent.models.turn'),
                'turnEffort' => param('odiseo_ai_conversational_agent.models.thinking_effort'),
                'memoryModel' => param('odiseo_ai_conversational_agent.models.memory'),
                'sessionBudgetUsd' => param('odiseo_ai_conversational_agent.budgets.session_usd'),
                'dailyBudgetUsd' => param('odiseo_ai_conversational_agent.budgets.daily_usd'),
                'monthlyBudgetUsd' => param('odiseo_ai_conversational_agent.budgets.monthly_usd'),
                'brandName' => param('odiseo_ai_conversational_agent.identity.brand_name'),
                'assistantName' => param('odiseo_ai_conversational_agent.identity.assistant_name'),
                'brandVoice' => param('odiseo_ai_conversational_agent.identity.brand_voice'),
                'memoryEnabled' => param('odiseo_ai_conversational_agent.memory.enabled'),
                'memoryRetentionDays' => param('odiseo_ai_conversational_agent.memory.retention_days'),
            ],
        ])
        ->tag('sulu.context', ['context' => 'admin']);

    $services->set($id . 'editable_controller', EditableController::class)
        ->public()
        ->args([
            service_locator(['rule' => service($site . 'editing.rule'), 'document' => service($site . 'editing.document')]),
            service($site . 'editing.editor'),
            service('translator'),
            service('security.helper')->nullOnInvalid(),
        ])
        ->tag('sulu.context', ['context' => 'admin']);
};
