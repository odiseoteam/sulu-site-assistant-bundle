<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\DependencyInjection;

use Odiseo\SuluSiteAssistantBundle\Config\SiteAssistantConfig;
use Odiseo\SuluSiteAssistantBundle\Entity\ContentVersion;
use Odiseo\SuluSiteAssistantBundle\Entity\ContentVersionInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\Conversation;
use Odiseo\SuluSiteAssistantBundle\Entity\ConversationInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\Handoff;
use Odiseo\SuluSiteAssistantBundle\Entity\HandoffInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\MemoryFact;
use Odiseo\SuluSiteAssistantBundle\Entity\MemoryFactInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\Message;
use Odiseo\SuluSiteAssistantBundle\Entity\MessageInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\Settings;
use Odiseo\SuluSiteAssistantBundle\Entity\SettingsInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\SpendEntry;
use Odiseo\SuluSiteAssistantBundle\Entity\SpendEntryInterface;
use Odiseo\SuluSiteAssistantBundle\Model\ContactPolicy;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffCategory;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffMode;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffPolicy;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffSchedule;
use Odiseo\SuluSiteAssistantBundle\Model\PathChoice;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The site's settings. Defaults are neutral: no lexicon means no forced search, no route means no
 * handoff. Setting a list replaces the default.
 */
final class Configuration implements ConfigurationInterface
{
    public const CORE_SECTIONS = ['identity', 'models', 'prices', 'capabilities', 'budgets', 'memory', 'latency', 'fence', 'rules', 'sessions', 'conversations'];

    public const AUDIENCE = 'a visitor on the organisation\'s website';

    public const OWN_LIMITS = ['max_search_results'];

    /** Name → [default model, interface]. The ones in CORE_OBJECTS are also the core store's classes. */
    public const OBJECTS = [
        'conversation' => [Conversation::class, ConversationInterface::class],
        'message' => [Message::class, MessageInterface::class],
        'memory_fact' => [MemoryFact::class, MemoryFactInterface::class],
        'spend_entry' => [SpendEntry::class, SpendEntryInterface::class],
        'content_version' => [ContentVersion::class, ContentVersionInterface::class],
        'handoff' => [Handoff::class, HandoffInterface::class],
        'settings' => [Settings::class, SettingsInterface::class],
    ];

    public const CORE_OBJECTS = ['conversation', 'message', 'memory_fact', 'spend_entry', 'content_version'];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('odiseo_sulu_site_assistant');
        /** @var ArrayNodeDefinition $root */
        $root = $treeBuilder->getRootNode();
        $children = $root->children();

        $children->scalarNode('webspace')
            ->info('The webspace the assistant falls back to when a request names none; null takes the first one.')
            ->defaultNull();

        $this->addContentSection($children);
        $this->addGroundingSection($children);
        $this->addWidgetSection($children);
        $this->addAccessSection($children);
        $this->addHandoffSection($root);

        $chipNotes = $children->arrayNode('presentation')->addDefaultsIfNotSet()->children()
            ->arrayNode('chip_notes')
                ->info('Added to each card\'s tool description: what the card already shows and which chips fit beside it. Replace a note when the widget\'s card changes.')
                ->addDefaultsIfNotSet()
                ->children();
        foreach (SiteAssistantConfig::CHIP_NOTES as $card => $note) {
            $chipNotes->scalarNode($card)->defaultValue($note);
        }

        $children->arrayNode('encryption')->addDefaultsIfNotSet()->children()
            ->scalarNode('secret')
                ->info('Encrypts the API keys saved in the admin. Changing it leaves the stored keys unreadable until they are saved again.')
                ->defaultValue('%kernel.secret%')
                ->cannotBeEmpty();

        $showcase = $children->arrayNode('showcase')->addDefaultsIfNotSet()->children();
        $showcase->scalarNode('enabled')
            ->info('Serves /agent/showcase: every card over the site\'s own pages, with no model call. On in debug by default.')
            ->defaultValue('%kernel.debug%');
        $showcase->scalarNode('fixture')
            ->info('JSON with the content ids and cards to show; without one the first pages of the index are used.')
            ->defaultValue('');
        $showcase->scalarNode('template')->defaultValue('@OdiseoSuluSiteAssistant/showcase.html.twig');

        // Also accepts the core's keys (max_components_per_turn, etc.) and forwards them.
        $limits = $children->arrayNode('limits')->addDefaultsIfNotSet()->ignoreExtraKeys(false)->children();
        $limits->integerNode('max_search_results')->min(1)->defaultValue(6);

        $objects = $children->arrayNode('objects')
            ->info('The entities, by name. A site can swap a model for its own subclass and migrate the difference.')
            ->addDefaultsIfNotSet()
            ->children();
        foreach (self::OBJECTS as $name => [$model]) {
            $objects->arrayNode($name)->addDefaultsIfNotSet()->children()
                ->scalarNode('model')->defaultValue($model)->cannotBeEmpty();
        }

        // Runtime settings, forwarded as is to the core, which validates them. A site never
        // writes `odiseo_ai_conversational_agent:` itself.
        foreach (self::CORE_SECTIONS as $section) {
            $children->variableNode($section)
                ->info(\sprintf('Forwarded as is to odiseo_ai_conversational_agent.%s; see the core bundle for its keys.', $section))
                ->defaultValue([])
                ->validate()
                    ->ifTrue(static fn (mixed $value): bool => 'identity' === $section && \is_array($value) && \array_key_exists('audience', $value))
                    ->thenInvalid('identity.audience is set by the bundle: the assistant talks with a visitor of the site.')
                ->end();
        }
        $children->scalarNode('evals_dir')
            ->info('Eval case files for agent:eval; forwarded to odiseo_ai_conversational_agent.evals_dir.')
            ->defaultValue('%kernel.project_dir%/config/site_assistant/evals');
        $children->scalarNode('knowledge_dir')
            ->info('Markdown policies and guides for search_knowledge; forwarded to odiseo_ai_conversational_agent.knowledge_dir. Null leaves the files out.')
            ->defaultValue('%kernel.project_dir%/config/site_assistant/knowledge');

        return $treeBuilder;
    }

    private function addContentSection(NodeBuilder $children): void
    {
        $content = $children->arrayNode('content')->addDefaultsIfNotSet()->children();
        $content->arrayNode('resource_keys')
            ->info('What goes into the assistant\'s index.')
            ->scalarPrototype()->end()
            ->defaultValue(['pages', 'articles']);
        $content->arrayNode('excluded_templates')
            ->info('Templates never indexed (a search results page, a landing without text).')
            ->scalarPrototype();
        $content->arrayNode('types')
            ->info('Kinds of page the visitor may ask for, as name → template keys, e.g. {service: [service], case: [case-study]}.')
            ->useAttributeAsKey('name')
            ->arrayPrototype()->scalarPrototype()->end()->end();
        $content->scalarNode('search_notes')
            ->info('One line added to the prompt about how this site is searched.')
            ->defaultValue('');
        $content->scalarNode('image_format')
            ->info('Sulu image format of the card thumbnails.')
            ->defaultValue('sulu-400x400');
    }

    private function addGroundingSection(NodeBuilder $children): void
    {
        $grounding = $children->arrayNode('grounding')
            ->info('Lexicon in the visitors\' languages: a message that names a term and a cue starts from the matching search.')
            ->addDefaultsIfNotSet()
            ->children();
        foreach (['content_terms', 'content_cues', 'knowledge_terms', 'knowledge_cues'] as $list) {
            $grounding->arrayNode($list)->scalarPrototype();
        }
    }

    private function addWidgetSection(NodeBuilder $children): void
    {
        $widget = $children->arrayNode('widget')->addDefaultsIfNotSet()->children();
        $widget->scalarNode('template')->defaultValue('@OdiseoSuluSiteAssistant/widget.html.twig');
        $widget->scalarNode('cookie_path')->info('Path of the visitor cookie: where the agent\'s routes are mounted.')->defaultValue('/agent');
        $widget->booleanNode('new_chat_button')
            ->info('Shows the button that starts a new conversation in the widget\'s header.')
            ->defaultTrue();
        $widget->arrayNode('link_hosts')
            ->info('Hosts, besides the site\'s, whose links in a reply are clickable (subdomains included), such as wa.me. Any other link stays text.')
            ->scalarPrototype();

        $children->arrayNode('contact')
            ->info('Where the widget sends a person: the handoff card\'s buttons, and the way on when the assistant cannot answer.')
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('links')
                    ->info('Buttons by name, such as whatsapp: { label: WhatsApp, url: \'https://wa.me/5491100000000\' }. The url is https:, mailto:, tel: or page:<uuid> for a page of the site in the visitor\'s language. The label and detail go through the translator. The first one is the main button.')
                    ->useAttributeAsKey('name')
                    ->validate()
                        ->ifTrue(static fn (array $links): bool => [] !== \array_filter(\array_keys($links), static fn (int|string $name): bool => 1 !== \preg_match('/^[a-z0-9_]+$/', (string) $name)))
                        ->thenInvalid('A contact link name takes only lowercase letters, digits and underscores.')
                    ->end()
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('label')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('url')->isRequired()->cannotBeEmpty()
                                ->validate()
                                    ->ifTrue(static fn (mixed $url): bool => !\is_string($url) || 1 !== \preg_match('/^(https:\/\/|mailto:|tel:|page:[0-9a-f-]{36}$)/i', $url))
                                    ->thenInvalid('A contact link starts with https://, mailto:, tel: or is page:<uuid>; got %s.')
                                ->end()
                            ->end()
                            ->scalarNode('detail')->info('A line under the button, such as the address of a mailto: link.')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addAccessSection(NodeBuilder $children): void
    {
        $access = $children->arrayNode('access')
            ->info('Restricted access, for a launch: only a browser that came in with ?<query_parameter>=<token> sees the assistant.')
            ->addDefaultsIfNotSet()
            ->children();
        $access->booleanNode('restricted')->info('False opens the assistant to everyone.')->defaultFalse();
        $access->scalarNode('token')->info('Secret that opens access; empty keeps the assistant closed while restricted.')->defaultValue('');
        $access->scalarNode('query_parameter')->cannotBeEmpty()->defaultValue('agent');
    }

    /** Topics are shared by every webspace; a webspace sets its own hours and routes. Without routes there is no handoff. */
    private function addHandoffSection(ArrayNodeDefinition $node): void
    {
        $handoff = $node->children()->arrayNode('handoff')
            ->info('Passing a case to a person. Nothing happens until a route is set.')
            ->addDefaultsIfNotSet();
        $children = $handoff->children();
        $children->scalarNode('reference_prefix')
            ->info('Goes before the code in the reference the visitor and the team use.')
            ->defaultValue(HandoffPolicy::DEFAULT_REFERENCE_PREFIX);
        $this->addHours($children);
        $this->addRoutes($children, 'For every topic without routes of its own.');

        $email = $children->arrayNode('notice')->addDefaultsIfNotSet()->children()
            ->arrayNode('email')
                ->info('The email the team gets for a case on the team_contact path.')
                ->addDefaultsIfNotSet()
                ->children();
        $email->arrayNode('recipients')
            ->info('Addresses the notice goes to. Without any, no email is sent.')
            ->defaultValue([])
            ->scalarPrototype()
                ->validate()
                    ->ifTrue(static fn (mixed $address): bool => !\is_string($address) || false === \filter_var($address, \FILTER_VALIDATE_EMAIL))
                    ->thenInvalid('A handoff notice recipient must be an email address; got %s.')
                ->end()
            ->end();
        $email->scalarNode('from')->info('The sender; the mailer\'s default envelope when empty.')->defaultNull();
        $email->scalarNode('admin_url')->info('The admin\'s address, such as https://example.com/admin: the email links the case there.')->defaultNull();
        $email->scalarNode('locale')->info('The language of the email.')->defaultValue('en');

        $topic = $children->arrayNode('topics')
            ->info(\sprintf('By id. These come with the bundle and can be changed here: %s. A new one needs description, category and mode.', \implode(', ', \array_keys(HandoffPolicy::BASE_TOPICS))))
            ->useAttributeAsKey('id')
            ->arrayPrototype()
            ->children();
        $topic->scalarNode('description')->info('What the assistant reads to pick this topic.')->defaultNull();
        $topic->enumNode('category')->values([null, ...\array_column(HandoffCategory::cases(), 'value')])->defaultNull();
        $topic->enumNode('mode')->values([null, ...\array_column(HandoffMode::cases(), 'value')])->defaultNull();
        $topic->booleanNode('enabled')->defaultTrue();
        $this->addRoutes($topic, 'The routes of this topic, read from the top; the first that applies wins.');

        $webspace = $children->arrayNode('webspaces')
            ->info('By webspace key: what changes in that webspace.')
            ->useAttributeAsKey('key')
            ->arrayPrototype()
            ->children();
        $this->addHours($webspace);
        $this->addRoutes($webspace, 'For every topic of this webspace without routes of its own.');
        $webspaceTopic = $webspace->arrayNode('topics')
            ->useAttributeAsKey('id')
            ->arrayPrototype()
            ->children();
        $webspaceTopic->booleanNode('enabled')->defaultTrue();
        $this->addRoutes($webspaceTopic, 'The routes of this topic in this webspace.');
    }

    private function addHours(NodeBuilder $parent): void
    {
        $parent->arrayNode('hours')
            ->info('Opening hours by day, such as mon: [\'09:00-13:00\', \'14:00-18:00\'], in sessions.timezone. Empty counts as always open.')
            ->useAttributeAsKey('day')
            ->validate()
                ->ifTrue(static fn (array $days): bool => [] !== \array_diff(\array_map('strval', \array_keys($days)), HandoffSchedule::DAYS))
                ->thenInvalid(\sprintf('A handoff day is one of: %s.', \implode(', ', HandoffSchedule::DAYS)))
            ->end()
            ->arrayPrototype()
                ->scalarPrototype()
                    ->validate()
                        ->ifTrue(static fn (mixed $range): bool => !\is_string($range) || 1 !== \preg_match('/^([01]\d|2[0-3]):[0-5]\d-([01]\d|2[0-3]):[0-5]\d$/', $range))
                        ->thenInvalid('A handoff range looks like 10:00-13:00; got %s.')
                    ->end()
                ->end()
            ->end();
    }

    private function addRoutes(NodeBuilder $parent, string $info): void
    {
        $route = $parent->arrayNode('routes')->info($info)->arrayPrototype()->children();
        $route->variableNode('when')
            ->info('Conditions that must all hold, such as { hours: open, locale: es }. Without any, the route always applies.')
            ->defaultValue([])
            ->validate()
                ->ifTrue(static fn (mixed $when): bool => !\is_array($when))
                ->thenInvalid('A handoff route\'s when is a map of conditions.')
            ->end();
        $route->arrayNode('paths')->isRequired()->requiresAtLeastOneElement()->scalarPrototype();
        $route->enumNode('choice')
            ->info('With several paths: visitor asks them, priority takes the first one the known contact allows.')
            ->values(\array_column(PathChoice::cases(), 'value'))
            ->defaultValue(PathChoice::Visitor->value);
        $route->enumNode('contact')
            ->info('Overrides what the paths need: off ignores it, optional goes on without it. Empty means required.')
            ->values([null, ...\array_column(ContactPolicy::cases(), 'value')])
            ->defaultNull();
    }
}
