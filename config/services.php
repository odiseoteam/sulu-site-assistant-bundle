<?php

declare(strict_types=1);

use Odiseo\AiConversationalAgentBundle\Agent\ContextProviderInterface;
use Odiseo\AiConversationalAgentBundle\Bridge\Doctrine\ConversationInitializerInterface;
use Odiseo\AiConversationalAgentBundle\Bridge\Symfony\OdiseoAiConversationalAgentBundle;
use Odiseo\AiConversationalAgentBundle\Budget\SpendScopeResolverInterface;
use Odiseo\AiConversationalAgentBundle\Capability\CapabilityRegistry;
use Odiseo\AiConversationalAgentBundle\Capability\Limits;
use Odiseo\AiConversationalAgentBundle\Config\ConfigOverridesInterface;
use Odiseo\AiConversationalAgentBundle\Content\VersionStoreInterface;
use Odiseo\AiConversationalAgentBundle\Eval\EvalEnvironmentInterface;
use Odiseo\AiConversationalAgentBundle\Execution\ExecutorWording;
use Odiseo\AiConversationalAgentBundle\Fencing\Fence;
use Odiseo\AiConversationalAgentBundle\Host\ConsoleEnvironmentInterface;
use Odiseo\AiConversationalAgentBundle\Host\PrincipalResolverInterface;
use Odiseo\AiConversationalAgentBundle\Host\TurnHookInterface;
use Odiseo\AiConversationalAgentBundle\Memory\MemoryRetentionInterface;
use Odiseo\AiConversationalAgentBundle\Provider\ApiKeyResolverInterface;
use Odiseo\AiConversationalAgentBundle\Session\CurrentSession;
use Odiseo\SuluSiteAssistantBundle\Capability\ContentCapability;
use Odiseo\SuluSiteAssistantBundle\Capability\ContentRecords;
use Odiseo\SuluSiteAssistantBundle\Capability\HandoffCapability;
use Odiseo\SuluSiteAssistantBundle\Capability\KnowledgeCapability;
use Odiseo\SuluSiteAssistantBundle\Capability\PresentationCapability;
use Odiseo\SuluSiteAssistantBundle\Command\SearchCommand;
use Odiseo\SuluSiteAssistantBundle\Config\SiteAssistantConfig;
use Odiseo\SuluSiteAssistantBundle\Content\ContentIndexInterface;
use Odiseo\SuluSiteAssistantBundle\Content\Indexing\ContentIndexListener;
use Odiseo\SuluSiteAssistantBundle\Content\Indexing\ContentReindexProvider;
use Odiseo\SuluSiteAssistantBundle\Content\Indexing\TemplateTextExtractor;
use Odiseo\SuluSiteAssistantBundle\Content\SealContentIndex;
use Odiseo\SuluSiteAssistantBundle\Controller\ContactLinkController;
use Odiseo\SuluSiteAssistantBundle\Controller\ShowcaseController;
use Odiseo\SuluSiteAssistantBundle\Conversation\ActivityTurnHook;
use Odiseo\SuluSiteAssistantBundle\Conversation\ConversationActivity;
use Odiseo\SuluSiteAssistantBundle\Conversation\ConversationInitializer;
use Odiseo\SuluSiteAssistantBundle\Conversation\Transcript;
use Odiseo\SuluSiteAssistantBundle\DependencyInjection\Compiler\ValidateHandoffRoutesPass;
use Odiseo\SuluSiteAssistantBundle\Doctrine\MappedSuperclassListener;
use Odiseo\SuluSiteAssistantBundle\Editing\DocumentSection;
use Odiseo\SuluSiteAssistantBundle\Editing\Editor;
use Odiseo\SuluSiteAssistantBundle\Editing\RuleSection;
use Odiseo\SuluSiteAssistantBundle\Encryption\ApiKeyEncrypterInterface;
use Odiseo\SuluSiteAssistantBundle\Encryption\SettingsEncryptionListener;
use Odiseo\SuluSiteAssistantBundle\Encryption\SodiumApiKeyEncrypter;
use Odiseo\SuluSiteAssistantBundle\Eval\HandoffGrader;
use Odiseo\SuluSiteAssistantBundle\Eval\SiteEvalEnvironment;
use Odiseo\SuluSiteAssistantBundle\EventListener\AgentExceptionListener;
use Odiseo\SuluSiteAssistantBundle\EventListener\AssistantAccessListener;
use Odiseo\SuluSiteAssistantBundle\EventListener\AssistantAvailabilityListener;
use Odiseo\SuluSiteAssistantBundle\Handoff\Condition\ContactCondition;
use Odiseo\SuluSiteAssistantBundle\Handoff\Condition\HoursCondition;
use Odiseo\SuluSiteAssistantBundle\Handoff\Condition\LocaleCondition;
use Odiseo\SuluSiteAssistantBundle\Handoff\ConversationContactResolver;
use Odiseo\SuluSiteAssistantBundle\Handoff\HandoffContactResolverInterface;
use Odiseo\SuluSiteAssistantBundle\Handoff\HandoffDesk;
use Odiseo\SuluSiteAssistantBundle\Handoff\HandoffDeskInterface;
use Odiseo\SuluSiteAssistantBundle\Handoff\Message\NotifyTeamHandler;
use Odiseo\SuluSiteAssistantBundle\Handoff\Notice\EmailHandoffNotice;
use Odiseo\SuluSiteAssistantBundle\Handoff\Path\TeamContactPath;
use Odiseo\SuluSiteAssistantBundle\Handoff\Path\VisitorWritesPath;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffPolicy;
use Odiseo\SuluSiteAssistantBundle\Prompt\SiteContextProvider;
use Odiseo\SuluSiteAssistantBundle\Repository\ConversationRepository;
use Odiseo\SuluSiteAssistantBundle\Repository\HandoffRepository;
use Odiseo\SuluSiteAssistantBundle\Repository\SettingsRepository;
use Odiseo\SuluSiteAssistantBundle\Settings\AssistantAccess;
use Odiseo\SuluSiteAssistantBundle\Settings\SettingsApiKeys;
use Odiseo\SuluSiteAssistantBundle\Settings\SettingsConfigOverrides;
use Odiseo\SuluSiteAssistantBundle\Settings\SettingsMemoryRetention;
use Odiseo\SuluSiteAssistantBundle\Settings\SharedCapGate;
use Odiseo\SuluSiteAssistantBundle\Settings\WebspaceSettings;
use Odiseo\SuluSiteAssistantBundle\Settings\WebspaceSpendScope;
use Odiseo\SuluSiteAssistantBundle\Showcase\ShowcaseBuilder;
use Odiseo\SuluSiteAssistantBundle\Site\CurrentWebspace;
use Odiseo\SuluSiteAssistantBundle\Site\MediaUrlResolver;
use Odiseo\SuluSiteAssistantBundle\Site\PageUrlResolver;
use Odiseo\SuluSiteAssistantBundle\Site\Webspaces;
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
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

use Symfony\Component\Mailer\MailerInterface;

// Every service is registered under an `odiseo_sulu_site_assistant.*` id with explicit arguments;
// the interfaces a site autowires or replaces are aliases.
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $id = 'odiseo_sulu_site_assistant.';
    $core = 'odiseo_ai_conversational_agent.';
    $services = $container->services();

    /** @var array{resource_keys: list<string>, excluded_templates: list<string>, types: array<string, list<string>>, search_notes: string, image_format: string} $content */
    $content = $builder->getParameter($id . 'content');
    /** @var array{content_terms: list<string>, content_cues: list<string>, knowledge_terms: list<string>, knowledge_cues: list<string>} $grounding */
    $grounding = $builder->getParameter($id . 'grounding');
    /** @var array{template: string, cookie_path: string, new_chat_button: bool, link_hosts: list<string>} $widget */
    $widget = $builder->getParameter($id . 'widget');
    /** @var array{links: array<string, array{label: string, url: string, detail?: ?string}>} $contact */
    $contact = $builder->getParameter($id . 'contact');
    /** @var array{restricted: bool, token: string, query_parameter: string} $access */
    $access = $builder->getParameter($id . 'access');
    /** @var array{notice: array{email: array{recipients: list<string>, from: ?string, admin_url: ?string, locale: string}}} $handoff */
    $handoff = $builder->getParameter($id . 'handoff');
    /** @var array{chip_notes: array<string, string>} $presentation */
    $presentation = $builder->getParameter($id . 'presentation');
    /** @var array{secret: string} $encryption */
    $encryption = $builder->getParameter($id . 'encryption');
    /** @var array{enabled: bool|string, fixture: string, template: string} $showcase */
    $showcase = $builder->getParameter($id . 'showcase');

    $services->set($id . 'config', SiteAssistantConfig::class)->args([
        '$contentTypes' => $content['types'],
        '$searchNotes' => $content['search_notes'],
        '$contentTerms' => $grounding['content_terms'],
        '$contentCues' => $grounding['content_cues'],
        '$knowledgeTerms' => $grounding['knowledge_terms'],
        '$knowledgeCues' => $grounding['knowledge_cues'],
        '$maxSearchResults' => param($id . 'max_search_results'),
        '$chipNotes' => $presentation['chip_notes'],
    ]);
    $services->alias(SiteAssistantConfig::class, $id . 'config');

    // -- Site ---------------------------------------------------------------------------

    $services->set($id . 'site.webspaces', Webspaces::class)
        ->args([service('sulu_core.webspace.webspace_manager'), param($id . 'webspace')]);
    $services->alias(Webspaces::class, $id . 'site.webspaces');

    $services->set($id . 'site.current_webspace', CurrentWebspace::class)
        ->args([
            service($id . 'site.webspaces'),
            service('request_stack'),
            service(CurrentSession::class),
            service('doctrine.orm.entity_manager'),
            param($id . 'model.conversation.class'),
            service('sulu_core.webspace.request_analyzer')->nullOnInvalid(),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);
    $services->alias(CurrentWebspace::class, $id . 'site.current_webspace');

    $services->set($id . 'site.page_url_resolver', PageUrlResolver::class)
        ->args([service('sulu_core.webspace.webspace_manager'), param('kernel.environment')]);
    $services->set($id . 'site.media_url_resolver', MediaUrlResolver::class)
        ->args([service('sulu_media.media_manager'), $content['image_format']]);

    // -- Visitor ------------------------------------------------------------------------

    $services->set($id . 'visitor.context', VisitorContext::class)
        ->args([service('request_stack'), service($id . 'site.webspaces'), service($id . 'site.current_webspace'), param('kernel.secret')]);
    $services->alias(VisitorContext::class, $id . 'visitor.context');
    $services->alias(PrincipalResolverInterface::class, $id . 'visitor.context');

    $services->set($id . 'visitor.cookie_listener', VisitorCookieListener::class)
        ->args([service($id . 'visitor.context'), $widget['cookie_path']])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse']);

    $services->set($id . 'visitor.console', ConsoleVisitor::class)
        ->args([service('request_stack'), service($id . 'site.webspaces')]);
    $services->alias(ConsoleVisitor::class, $id . 'visitor.console');
    $services->alias(ConsoleEnvironmentInterface::class, $id . 'visitor.console');

    // -- Repositories -------------------------------------------------------------------

    foreach (['conversation' => ConversationRepository::class, 'handoff' => HandoffRepository::class, 'settings' => SettingsRepository::class] as $name => $class) {
        $services->set($id . 'repository.' . $name, $class)
            ->args([service('doctrine.orm.entity_manager'), param($id . 'model.' . $name . '.class')]);
        $services->alias($class, $id . 'repository.' . $name);
    }

    // -- Settings saved in the admin ----------------------------------------------------

    $services->set($id . 'settings.webspace', WebspaceSettings::class)
        ->args([service($id . 'site.current_webspace'), service($id . 'repository.settings')])
        ->tag('kernel.reset', ['method' => 'reset']);
    $services->alias(WebspaceSettings::class, $id . 'settings.webspace');

    $services->set($id . 'settings.config_overrides', SettingsConfigOverrides::class)
        ->args([service($id . 'settings.webspace')]);
    $services->alias(ConfigOverridesInterface::class, $id . 'settings.config_overrides');

    $services->set($id . 'encryption.api_key', SodiumApiKeyEncrypter::class)
        ->args([$encryption['secret']]);
    $services->alias(ApiKeyEncrypterInterface::class, $id . 'encryption.api_key');

    $services->set($id . 'encryption.settings_listener', SettingsEncryptionListener::class)
        ->args([service($id . 'encryption.api_key')])
        ->tag('doctrine.event_listener', ['event' => 'onFlush']);

    $services->set($id . 'settings.api_keys', SettingsApiKeys::class)
        ->args([service($id . 'settings.webspace'), service($id . 'encryption.api_key')]);
    $services->alias(ApiKeyResolverInterface::class, $id . 'settings.api_keys');

    $services->set($id . 'settings.spend_scope', WebspaceSpendScope::class)
        ->args([service($id . 'site.current_webspace')]);
    $services->alias(SpendScopeResolverInterface::class, $id . 'settings.spend_scope');

    $services->set($id . 'settings.memory_retention', SettingsMemoryRetention::class)
        ->args([service($id . 'repository.settings'), service($id . 'site.webspaces')]);
    $services->alias(MemoryRetentionInterface::class, $id . 'settings.memory_retention');

    // The widget is not rendered while the day or month cap is reached; checked once a minute.
    $services->set($id . 'settings.shared_cap', SharedCapGate::class)
        ->args([service($core . 'budget.policy'), service($id . 'site.current_webspace'), service('cache.app'), param($core . 'timezone')]);

    $services->set($id . 'settings.access', AssistantAccess::class)
        ->args([service('request_stack'), param('kernel.secret'), $access['restricted'], $access['token'], $access['query_parameter']]);

    $services->set($id . 'listener.access', AssistantAccessListener::class)
        ->args([service($id . 'settings.access')])
        ->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => 64]);

    // After the router (32), so the route is known.
    $services->set($id . 'listener.availability', AssistantAvailabilityListener::class)
        ->args([service($id . 'settings.webspace'), service($id . 'settings.access')])
        ->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => 8]);

    // Before the framework's error listener (-128), so the widget gets JSON, never a page.
    $services->set($id . 'listener.agent_exception', AgentExceptionListener::class)
        ->tag('kernel.event_listener', ['event' => 'kernel.exception', 'method' => 'onKernelException', 'priority' => 8]);

    // -- Content ------------------------------------------------------------------------

    $services->set($id . 'content.index', SealContentIndex::class)
        ->args([service('cmsig_seal.engine.default')]);
    $services->alias(ContentIndexInterface::class, $id . 'content.index');

    $services->set($id . 'command.search', SearchCommand::class)
        ->args([service($id . 'content.index'), service($id . 'site.webspaces')])
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
            ['conversation', 'message', 'memory_fact', 'spend_entry', 'content_version', 'handoff', 'settings'],
        )])
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    $services->set($id . 'conversation.activity', ConversationActivity::class)
        ->args([service('doctrine.orm.entity_manager'), service('logger'), param($id . 'model.conversation.class')])
        ->tag('monolog.logger', ['channel' => 'site_assistant']);

    $services->set($id . 'conversation.transcript', Transcript::class)
        ->args([service('doctrine.orm.entity_manager'), param($id . 'model.message.class'), service($id . 'content.index')->nullOnInvalid()]);

    $services->set($id . 'conversation.initializer', ConversationInitializer::class)
        ->args([service($id . 'visitor.context')]);
    $services->alias(ConversationInitializerInterface::class, $id . 'conversation.initializer');

    $services->set($id . 'conversation.turn_hook', ActivityTurnHook::class)
        ->args([service($id . 'conversation.activity'), service('request_stack')]);
    $services->alias(TurnHookInterface::class, $id . 'conversation.turn_hook');

    $services->set($id . 'prompt.context_provider', SiteContextProvider::class)
        ->args([service($id . 'visitor.context')]);
    $services->alias(ContextProviderInterface::class, $id . 'prompt.context_provider');

    // The executor's wording speaks of a visitor, not a customer.
    $services->set($core . 'execution.wording', ExecutorWording::class)->args([
        '$displayedText' => 'Displayed to the visitor.',
        '$unavailableText' => '{name} is temporarily unavailable. Work with what you already have or let the visitor know.',
        '$tooManyComponentsText' => 'This turn has already presented {count} components; say the rest in text or end the turn.',
        '$statusReader' => 'the visitor',
    ]);

    // -- Capabilities -------------------------------------------------------------------

    $services->set($id . 'capability.content_records', ContentRecords::class)
        ->args([service($id . 'site.media_url_resolver')]);

    $services->set($id . 'capability.content', ContentCapability::class)
        ->args([
            service($id . 'content.index'),
            service($id . 'site.page_url_resolver'),
            service($id . 'visitor.context'),
            service($id . 'site.webspaces'),
            service($id . 'config'),
        ])
        ->tag(OdiseoAiConversationalAgentBundle::CAPABILITY_TAG);

    $services->set($id . 'capability.knowledge', KnowledgeCapability::class)
        ->args([service($core . 'knowledge.base'), service($id . 'config')])
        ->tag(OdiseoAiConversationalAgentBundle::CAPABILITY_TAG);

    $services->set($id . 'capability.presentation', PresentationCapability::class)
        ->args([service($id . 'capability.content_records'), service($id . 'config')])
        ->tag(OdiseoAiConversationalAgentBundle::CAPABILITY_TAG);

    // -- Handoff ------------------------------------------------------------------------

    $services->set($id . 'handoff.policy', HandoffPolicy::class)
        ->factory([HandoffPolicy::class, 'fromArray'])
        ->args([param($id . 'handoff')]);
    $services->alias(HandoffPolicy::class, $id . 'handoff.policy');

    $services->set($id . 'handoff.contact_resolver', ConversationContactResolver::class)
        ->args([service($id . 'repository.conversation'), service($id . 'repository.handoff')]);
    $services->alias(HandoffContactResolverInterface::class, $id . 'handoff.contact_resolver');

    $services->set($id . 'handoff.desk', HandoffDesk::class)
        ->args([
            service($id . 'handoff.policy'),
            tagged_iterator(ValidateHandoffRoutesPass::PATH_TAG, 'path'),
            tagged_iterator(ValidateHandoffRoutesPass::CONDITION_TAG, 'condition'),
            service($id . 'repository.handoff'),
            service($id . 'repository.conversation'),
            service('doctrine.orm.entity_manager'),
            service($id . 'visitor.context'),
            service(HandoffContactResolverInterface::class),
            service('event_dispatcher'),
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'site_assistant']);
    $services->alias(HandoffDeskInterface::class, $id . 'handoff.desk');

    $services->set($id . 'capability.handoff', HandoffCapability::class)
        ->args([service($id . 'handoff.desk'), service($id . 'handoff.policy')])
        ->tag(OdiseoAiConversationalAgentBundle::CAPABILITY_TAG);

    $services->set($id . 'handoff.path.visitor_writes', VisitorWritesPath::class)
        ->tag(ValidateHandoffRoutesPass::PATH_TAG, ['path' => VisitorWritesPath::NAME]);
    $services->set($id . 'handoff.path.team_contact', TeamContactPath::class)
        ->args([service('messenger.default_bus')])
        ->tag(ValidateHandoffRoutesPass::PATH_TAG, ['path' => TeamContactPath::NAME]);

    $services->set($id . 'handoff.condition.hours', HoursCondition::class)
        ->tag(ValidateHandoffRoutesPass::CONDITION_TAG, ['condition' => 'hours']);
    $services->set($id . 'handoff.condition.contact', ContactCondition::class)
        ->tag(ValidateHandoffRoutesPass::CONDITION_TAG, ['condition' => 'contact']);
    $services->set($id . 'handoff.condition.locale', LocaleCondition::class)
        ->tag(ValidateHandoffRoutesPass::CONDITION_TAG, ['condition' => 'locale']);

    $email = $handoff['notice']['email'];
    if ([] !== $email['recipients']) {
        $services->set($id . 'handoff.notice.email', EmailHandoffNotice::class)
            ->args([
                service(MailerInterface::class),
                service('translator'),
                $email['recipients'],
                $email['from'],
                $email['admin_url'],
                '@OdiseoSuluSiteAssistant/email/handoff_notice.html.twig',
                $email['locale'],
            ])
            ->tag(ValidateHandoffRoutesPass::NOTICE_TAG);
    }

    $services->set($id . 'handoff.notify_team_handler', NotifyTeamHandler::class)
        ->args([service($id . 'repository.handoff'), tagged_iterator(ValidateHandoffRoutesPass::NOTICE_TAG), service('doctrine.orm.entity_manager')])
        ->tag('messenger.message_handler');

    // -- Rules and knowledge edited in the admin ----------------------------------------

    $services->set($id . 'editing.rule', RuleSection::class)
        ->args([
            service(VersionStoreInterface::class),
            service($core . 'rule.configured_source'),
            param($core . 'limits.max_rules'),
            param($core . 'limits.max_rule_chars'),
        ]);
    $services->set($id . 'editing.document', DocumentSection::class)
        ->args([
            service(VersionStoreInterface::class),
            service($core . 'knowledge.markdown_source')->nullOnInvalid(),
            param($core . 'limits.max_guides'),
            param($core . 'limits.max_guide_when_chars'),
        ]);
    $services->set($id . 'editing.editor', Editor::class)
        ->args([service(VersionStoreInterface::class)]);

    // -- Website surface ----------------------------------------------------------------

    $services->set($id . 'controller.contact_link', ContactLinkController::class)
        ->public()
        ->args([
            service($core . 'session.resolver'),
            service($id . 'conversation.activity'),
            service('limiter.odiseo_site_assistant_contact_link'),
            \array_keys($contact['links']),
        ])
        ->tag('controller.service_arguments');

    $services->set($id . 'twig.widget_extension', WidgetExtension::class)
        ->args([
            service($id . 'settings.webspace'),
            service($id . 'site.current_webspace'),
            service($id . 'site.webspaces'),
            service($id . 'settings.access'),
            service($id . 'settings.shared_cap'),
            service($id . 'handoff.policy'),
            service('translator'),
            service($id . 'content.index'),
            service($id . 'site.page_url_resolver'),
            $widget['template'],
            \array_keys($content['types']),
            $contact['links'],
            $widget['link_hosts'],
            $widget['new_chat_button'],
        ])
        ->tag('twig.extension');

    $services->set($id . 'showcase.builder', ShowcaseBuilder::class)
        ->args([
            service(CapabilityRegistry::class),
            service($id . 'content.index'),
            service($id . 'site.page_url_resolver'),
            service($id . 'visitor.context'),
            service($id . 'config'),
            service(Fence::class),
            service(Limits::class),
            param($core . 'timezone'),
            $showcase['fixture'],
        ]);

    $services->set($id . 'controller.showcase', ShowcaseController::class)
        ->public()
        ->args([service($id . 'showcase.builder'), service('twig'), $showcase['template'], $showcase['enabled']])
        ->tag('controller.service_arguments');

    // -- Evals (`agent:eval`) -----------------------------------------------------------

    $services->set($id . 'eval.handoff_grader', HandoffGrader::class)
        ->tag(OdiseoAiConversationalAgentBundle::EVAL_GRADER_TAG);

    $services->set($id . 'eval.environment', SiteEvalEnvironment::class)
        ->args([
            service($id . 'visitor.console'),
            service($id . 'content.index'),
            service($id . 'site.page_url_resolver'),
            service($id . 'config'),
        ]);
    $services->alias(EvalEnvironmentInterface::class, $id . 'eval.environment');

    $container->import(__DIR__ . '/services_admin.php');
};
