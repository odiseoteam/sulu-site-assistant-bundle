<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\DependencyInjection;

use Odiseo\SuluSiteAssistantBundle\Entity\Conversation;
use Odiseo\SuluSiteAssistantBundle\Entity\MemoryFact;
use Odiseo\SuluSiteAssistantBundle\Entity\Message;
use Odiseo\SuluSiteAssistantBundle\Entity\SpendEntry;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The site's settings. Defaults are neutral: no lexicon means no forced search, no contact
 * route means a handoff card without buttons. Setting a list replaces the default.
 */
final class Configuration implements ConfigurationInterface
{
    public const CORE_SECTIONS = ['identity', 'models', 'budgets', 'memory', 'latency', 'fence', 'sessions', 'conversations'];

    public const OWN_LIMITS = ['max_search_results'];

    /** Name → default model; each is also the core store's class. */
    public const OBJECTS = [
        'conversation' => Conversation::class,
        'message' => Message::class,
        'memory_fact' => MemoryFact::class,
        'spend_entry' => SpendEntry::class,
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('odiseo_sulu_site_assistant');
        /** @var ArrayNodeDefinition $root */
        $root = $treeBuilder->getRootNode();
        $children = $root->children();

        $children->scalarNode('webspace')
            ->info('Webspace the assistant speaks for; null takes the first one.')
            ->defaultNull();

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

        $grounding = $children->arrayNode('grounding')
            ->info('Lexicon in the visitor\'s language: a message that names a term and a cue starts from a site search.')
            ->addDefaultsIfNotSet()
            ->children();
        $grounding->arrayNode('terms')->scalarPrototype();
        $grounding->arrayNode('cues')->scalarPrototype();

        $handoff = $children->arrayNode('handoff')
            ->info('Ways to reach a person, shown on the handoff card. Configuration only, never the model\'s.')
            ->addDefaultsIfNotSet()
            ->children();
        $handoff->scalarNode('contact_page')->info('UUID of the contact page; linked in the visitor\'s language.')->defaultNull();
        $handoff->scalarNode('email')->defaultNull();
        $handoff->scalarNode('booking_url')->defaultNull();

        $widget = $children->arrayNode('widget')->addDefaultsIfNotSet()->children();
        $widget->scalarNode('template')->defaultValue('@OdiseoSuluSiteAssistant/widget.html.twig');
        $widget->scalarNode('cookie_path')->info('Path of the visitor cookie: where the agent\'s routes are mounted.')->defaultValue('/agent');

        $limits = $children->arrayNode('limits')->addDefaultsIfNotSet()->ignoreExtraKeys(false)->children();
        $limits->integerNode('max_search_results')->min(1)->defaultValue(6);

        $objects = $children->arrayNode('objects')->addDefaultsIfNotSet()->children();
        foreach (self::OBJECTS as $name => $model) {
            $objects->arrayNode($name)->addDefaultsIfNotSet()->children()
                ->scalarNode('model')->defaultValue($model)->cannotBeEmpty();
        }

        // Runtime settings, forwarded as-is to the core, which validates them. A site never
        // writes `odiseo_ai_conversational_agent:` itself.
        foreach (self::CORE_SECTIONS as $section) {
            $children->variableNode($section)
                ->info(\sprintf('Forwarded as is to odiseo_ai_conversational_agent.%s; see the core bundle for its keys.', $section))
                ->defaultValue([]);
        }
        $children->scalarNode('evals_dir')
            ->info('Forwarded to odiseo_ai_conversational_agent.evals_dir.')
            ->defaultNull();

        return $treeBuilder;
    }
}
