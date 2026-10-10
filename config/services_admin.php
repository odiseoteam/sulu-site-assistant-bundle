<?php

declare(strict_types=1);

use Odiseo\SuluSiteAssistantBundle\Admin\SiteAssistantAdmin;
use Odiseo\SuluSiteAssistantBundle\Controller\Admin\ConversationController;
use Odiseo\SuluSiteAssistantBundle\Controller\Admin\StatisticsController;
use Odiseo\SuluSiteAssistantBundle\Statistics\StatisticsProvider;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

// The admin section: only in the admin context.
return static function (ContainerConfigurator $container): void {
    $id = 'odiseo_sulu_site_assistant.admin.';
    $services = $container->services();

    $services->set($id . 'admin', SiteAssistantAdmin::class)
        ->args([service(ViewBuilderFactoryInterface::class), service(SecurityCheckerInterface::class)])
        ->tag('sulu.admin')
        ->tag('sulu.context', ['context' => 'admin']);

    $services->set($id . 'statistics_provider', StatisticsProvider::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            param('odiseo_sulu_site_assistant.model.conversation.class'),
            param('odiseo_sulu_site_assistant.model.spend_entry.class'),
        ]);

    $services->set($id . 'conversation_controller', ConversationController::class)
        ->public()
        ->args([
            service('doctrine.orm.entity_manager'),
            service(FieldDescriptorFactoryInterface::class),
            service(DoctrineListBuilderFactoryInterface::class),
            service('sulu_core.doctrine_rest_helper'),
            service(NormalizerInterface::class),
            param('odiseo_sulu_site_assistant.model.conversation.class'),
            param('odiseo_sulu_site_assistant.model.message.class'),
            param('odiseo_sulu_site_assistant.model.memory_fact.class'),
            param('odiseo_sulu_site_assistant.model.spend_entry.class'),
        ])
        ->tag('sulu.context', ['context' => 'admin']);

    $services->set($id . 'statistics_controller', StatisticsController::class)
        ->public()
        ->args([service($id . 'statistics_provider')])
        ->tag('sulu.context', ['context' => 'admin']);
};
