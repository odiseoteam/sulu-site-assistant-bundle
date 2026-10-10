<?php

declare(strict_types=1);

use Odiseo\AiConversationalAgentBundle\Agent\ContextProvider;
use Odiseo\AiConversationalAgentBundle\Bridge\Doctrine\ConversationInitializer as CoreConversationInitializer;
use Odiseo\AiConversationalAgentBundle\Execution\ExecutorWording;
use Odiseo\AiConversationalAgentBundle\Host\ConsoleEnvironment;
use Odiseo\AiConversationalAgentBundle\Host\PrincipalResolver;
use Odiseo\AiConversationalAgentBundle\Host\TurnHook;
use Odiseo\SuluSiteAssistantBundle\Capability\ContentCapability;
use Odiseo\SuluSiteAssistantBundle\Capability\ContentRecords;
use Odiseo\SuluSiteAssistantBundle\Capability\HandoffCapability;
use Odiseo\SuluSiteAssistantBundle\Capability\PresentationCapability;
use Odiseo\SuluSiteAssistantBundle\Command\SearchCommand;
use Odiseo\SuluSiteAssistantBundle\Content\ContentIndexInterface;
use Odiseo\SuluSiteAssistantBundle\Content\Indexing\ContentIndexListener;
use Odiseo\SuluSiteAssistantBundle\Content\Indexing\ContentReindexProvider;
use Odiseo\SuluSiteAssistantBundle\Content\Indexing\TemplateTextExtractor;
use Odiseo\SuluSiteAssistantBundle\Content\SealContentIndex;
use Odiseo\SuluSiteAssistantBundle\Controller\HandoffController;
use Odiseo\SuluSiteAssistantBundle\Conversation\ActivityTurnHook;
use Odiseo\SuluSiteAssistantBundle\Conversation\ConversationActivity;
use Odiseo\SuluSiteAssistantBundle\Conversation\ConversationInitializer;
use Odiseo\SuluSiteAssistantBundle\Doctrine\MappedSuperclassListener;
use Odiseo\SuluSiteAssistantBundle\Prompt\SiteContextProvider;
use Odiseo\SuluSiteAssistantBundle\Site\MediaUrlResolver;
use Odiseo\SuluSiteAssistantBundle\Site\PageUrlResolver;
use Odiseo\SuluSiteAssistantBundle\Site\SiteLocales;
use Odiseo\SuluSiteAssistantBundle\SiteAssistantConfig;
use Odiseo\SuluSiteAssistantBundle\Twig\WidgetExtension;
use Odiseo\SuluSiteAssistantBundle\Visitor\ConsoleVisitor;
use Odiseo\SuluSiteAssistantBundle\Visitor\VisitorContext;
use Odiseo\SuluSiteAssistantBundle\Visitor\VisitorCookieListener;
use Sulu\Article\Domain\Event\ArticleRemovedEvent;
use Sulu\Article\Domain\Event\ArticleTranslationRemovedEvent;
use Sulu\Article\Domain\Event\ArticleWorkflowTransitionAppliedEvent;
use Sulu\Page\Domain\Event\PageRemovedEvent;
use Sulu\Page\Domain\Event\PageTranslationRemovedEvent;
use Sulu\Page\Domain\Event\PageWorkflowTransitionAppliedEvent;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

// Every service is registered under an `odiseo_sulu_site_assistant.*` id with explicit arguments;
// the interfaces a site replaces are aliases.
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $id = 'odiseo_sulu_site_assistant.';
    $services = $container->services();

    /** @var array{resource_keys: list<string>, excluded_templates: list<string>, types: array<string, list<string>>, search_notes: string, image_format: string} $content */
    $content = $builder->getParameter($id . 'content');
    /** @var array{terms: list<string>, cues: list<string>} $grounding */
    $grounding = $builder->getParameter($id . 'grounding');
    /** @var array{contact_page: ?string, email: ?string, booking_url: ?string} $handoff */
    $handoff = $builder->getParameter($id . 'handoff');
    /** @var array{template: string, cookie_path: string} $widget */
    $widget = $builder->getParameter($id . 'widget');

    $services->set($id . 'config', SiteAssistantConfig::class)->args([
        '$contentTypes' => $content['types'],
        '$searchNotes' => $content['search_notes'],
        '$groundingTerms' => $grounding['terms'],
        '$groundingCues' => $grounding['cues'],
        '$maxSearchResults' => param($id . 'max_search_results'),
        '$contactPage' => $handoff['contact_page'],
        '$contactEmail' => $handoff['email'],
        '$bookingUrl' => $handoff['booking_url'],
        '$imageFormat' => $content['image_format'],
    ]);

    // -- Site ---------------------------------------------------------------------------

    $services->set($id . 'site.locales', SiteLocales::class)
        ->args([service('sulu_core.webspace.webspace_manager'), param($id . 'webspace')]);
    $services->set($id . 'site.page_url_resolver', PageUrlResolver::class)
        ->args([service('sulu_core.webspace.webspace_manager'), param('kernel.environment')]);
    $services->set($id . 'site.media_url_resolver', MediaUrlResolver::class)
        ->args([service('sulu_media.media_manager'), $content['image_format']]);

    // -- Visitor ------------------------------------------------------------------------

    $services->set($id . 'visitor.context', VisitorContext::class)
        ->args([service('request_stack'), service($id . 'site.locales'), param('kernel.secret')]);
    $services->alias(VisitorContext::class, $id . 'visitor.context');
    $services->alias(PrincipalResolver::class, $id . 'visitor.context');

    $services->set($id . 'visitor.cookie_listener', VisitorCookieListener::class)
        ->args([service($id . 'visitor.context'), $widget['cookie_path']])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse']);

    $services->set($id . 'visitor.console', ConsoleVisitor::class)
        ->args([service('request_stack'), service($id . 'site.locales')]);
    $services->alias(ConsoleEnvironment::class, $id . 'visitor.console');

    // -- Content ------------------------------------------------------------------------

    $services->set($id . 'content.index', SealContentIndex::class)
        ->args([service('cmsig_seal.engine.default')]);
    $services->alias(ContentIndexInterface::class, $id . 'content.index');

    $services->set($id . 'command.search', SearchCommand::class)
        ->args([service($id . 'content.index'), service($id . 'site.locales')])
        ->tag('console.command');

    $services->set($id . 'content.text_extractor', TemplateTextExtractor::class)
        ->args([service('sulu_admin.form_metadata_provider')]);

    $services->set($id . 'content.reindex_provider', ContentReindexProvider::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service($id . 'content.text_extractor'),
            $content['resource_keys'],
            $content['excluded_templates'],
        ])
        ->tag('cmsig_seal.reindex_provider');

    $listener = $services->set($id . 'content.index_listener', ContentIndexListener::class)
        ->args([service('sulu_message_bus')]);
    if (\in_array('pages', $content['resource_keys'], true) && \class_exists(PageWorkflowTransitionAppliedEvent::class)) {
        foreach ([PageWorkflowTransitionAppliedEvent::class, PageRemovedEvent::class, PageTranslationRemovedEvent::class] as $event) {
            $listener->tag('kernel.event_listener', ['event' => $event, 'method' => 'onPageChanged']);
        }
    }
    if (\in_array('articles', $content['resource_keys'], true) && \class_exists(ArticleWorkflowTransitionAppliedEvent::class)) {
        foreach ([ArticleWorkflowTransitionAppliedEvent::class, ArticleRemovedEvent::class, ArticleTranslationRemovedEvent::class] as $event) {
            $listener->tag('kernel.event_listener', ['event' => $event, 'method' => 'onArticleChanged']);
        }
    }

    // -- Conversation -------------------------------------------------------------------

    $services->set($id . 'doctrine.mapped_superclass_listener', MappedSuperclassListener::class)
        ->args([\array_map(
            static fn (string $name): string => \sprintf('%%%smodel.%s.class%%', $id, $name),
            ['conversation', 'message', 'memory_fact', 'spend_entry'],
        )])
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    $services->set($id . 'conversation.activity', ConversationActivity::class)
        ->args([service('doctrine.orm.entity_manager'), service('logger'), param($id . 'model.conversation.class')])
        ->tag('monolog.logger', ['channel' => 'site_assistant']);

    $services->set($id . 'conversation.initializer', ConversationInitializer::class)
        ->args([service($id . 'visitor.context'), service($id . 'site.locales')]);
    $services->alias(CoreConversationInitializer::class, $id . 'conversation.initializer');

    $services->set($id . 'conversation.turn_hook', ActivityTurnHook::class)
        ->args([service($id . 'conversation.activity'), service('request_stack')]);
    $services->alias(TurnHook::class, $id . 'conversation.turn_hook');

    $services->set($id . 'prompt.context_provider', SiteContextProvider::class)
        ->args([service($id . 'visitor.context')]);
    $services->alias(ContextProvider::class, $id . 'prompt.context_provider');

    // The executor's wording speaks of a visitor, not a customer.
    $services->set('odiseo_ai_conversational_agent.execution.wording', ExecutorWording::class)->args([
        'Displayed to the visitor.',
        '{name} is temporarily unavailable. Work with what you already have or let the visitor know.',
        'This turn has already presented {count} components; say the rest in text or end the turn.',
        'the visitor',
    ]);

    // -- Capabilities -------------------------------------------------------------------

    $services->set($id . 'capability.content_records', ContentRecords::class)
        ->args([service($id . 'site.media_url_resolver')]);

    $services->set($id . 'capability.content', ContentCapability::class)
        ->args([
            service($id . 'content.index'),
            service($id . 'site.page_url_resolver'),
            service($id . 'visitor.context'),
            service($id . 'site.locales'),
            service($id . 'config'),
        ])
        ->tag('odiseo_ai_conversational_agent.capability');

    $services->set($id . 'capability.presentation', PresentationCapability::class)
        ->args([service($id . 'capability.content_records')])
        ->tag('odiseo_ai_conversational_agent.capability');

    $services->set($id . 'capability.handoff', HandoffCapability::class)
        ->args([
            service($id . 'config'),
            service($id . 'content.index'),
            service($id . 'site.page_url_resolver'),
            service($id . 'visitor.context'),
            service($id . 'capability.content_records'),
            service($id . 'conversation.activity'),
        ])
        ->tag('odiseo_ai_conversational_agent.capability');

    // -- Website surface ----------------------------------------------------------------

    $services->set($id . 'controller.handoff', HandoffController::class)
        ->public()
        ->args([
            service('odiseo_ai_conversational_agent.session.resolver'),
            service($id . 'conversation.activity'),
            service('limiter.agent_handoff_click'),
        ])
        ->tag('controller.service_arguments');

    $services->set($id . 'twig.widget_extension', WidgetExtension::class)
        ->args([$widget['template'], \array_keys($content['types'])])
        ->tag('twig.extension');

    if (\is_file(__DIR__ . '/services_admin.php')) {
        $container->import(__DIR__ . '/services_admin.php');
    }
};
