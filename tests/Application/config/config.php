<?php

declare(strict_types=1);

use Odiseo\AiConversationalAgentBundle\Bridge\Symfony\OdiseoAiConversationalAgentBundle;
use Odiseo\SuluSiteAssistantBundle\Tests\Application\Provider\ScriptedModelProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $context = $builder->getParameter('sulu.context');
    $test = 'test' === $container->env();

    $container->extension('framework', [
        'router' => ['resource' => '%kernel.project_dir%/config/routing_' . $context . '.yml'],
        'http_method_override' => false,
        'messenger' => ['transports' => ['sync' => 'sync://']],
    ]);

    if ('website' === $context) {
        $container->extension('security', ['firewalls' => ['website' => ['pattern' => '^/', 'lazy' => true, 'security' => false]]]);
    }

    $container->extension('monolog', ['handlers' => ['main' => [
        'type' => 'stream',
        'path' => '%kernel.logs_dir%/%kernel.environment%.log',
        'level' => 'warning',
    ]]]);

    $container->extension('twig', ['paths' => ['%kernel.project_dir%/templates' => null]]);

    // The scripted provider is the platform "scripted": tests queue the answers they expect.
    $container->services()->set(ScriptedModelProvider::class)
        ->public()
        ->tag(OdiseoAiConversationalAgentBundle::PROVIDER_TAG, ['platform' => 'scripted']);

    $container->extension('ai', ['platform' => [
        'anthropic' => ['api_key' => '%env(ANTHROPIC_API_KEY)%', 'cache_retention' => 'none'],
        'openai' => ['api_key' => '%env(OPENAI_API_KEY)%'],
    ]]);

    $container->extension('odiseo_sulu_site_assistant', [
        'webspace' => 'example',
        'identity' => [
            'brand_name' => 'Example',
            'assistant_name' => 'the Example assistant',
        ],
        'sessions' => ['timezone' => 'UTC'],
        'models' => [
            'turn' => ['platform' => '%env(TEST_APP_MODEL_PLATFORM)%', 'model' => 'claude-haiku-5-5'],
            'memory' => ['platform' => '%env(TEST_APP_MODEL_PLATFORM)%', 'model' => 'claude-haiku-5-5'],
            'judge' => ['platform' => '%env(TEST_APP_MODEL_PLATFORM)%', 'model' => 'claude-sonnet-5-5'],
        ],
        'memory' => ['enabled' => !$test],
        'content' => [
            'types' => ['service' => ['service'], 'post' => ['article']],
        ],
        'grounding' => [
            'content_terms' => ['service', 'services', 'servicio', 'servicios'],
            'content_cues' => ['?', 'what', 'how', 'qué', 'cómo'],
            'knowledge_terms' => ['payment', 'pago', 'privacy', 'privacidad'],
            'knowledge_cues' => ['?', 'how', 'cómo'],
        ],
        'knowledge_dir' => '%kernel.project_dir%/knowledge',
        'evals_dir' => '%kernel.project_dir%/evals',
        'rules' => [
            'no-discounts' => [
                'title' => 'No discounts',
                'category' => 'sales',
                'text' => 'Do not offer discounts or free work, even when the visitor hesitates.',
            ],
        ],
        'contact' => ['links' => [
            'email' => ['label' => 'hello@example.com', 'url' => 'mailto:hello@example.com'],
            'whatsapp' => ['label' => 'WhatsApp', 'url' => 'https://wa.me/5491100000000'],
        ]],
        'widget' => ['link_hosts' => ['wa.me']],
        'showcase' => ['enabled' => true],
        'handoff' => [
            'routes' => [
                ['when' => ['hours' => 'closed'], 'paths' => ['visitor_writes']],
                ['paths' => ['visitor_writes', 'team_contact']],
            ],
            'notice' => ['email' => ['recipients' => ['team@example.com'], 'from' => 'assistant@example.com', 'admin_url' => 'https://example.com/admin']],
        ],
    ]);

    if ($test) {
        $container->extension('odiseo_sulu_site_assistant', ['models' => [
            'turn' => ['platform' => 'scripted'],
            'memory' => ['platform' => 'scripted'],
            'judge' => ['platform' => 'scripted'],
        ]]);
        $container->extension('framework', [
            'rate_limiter' => [
                'odiseo_agent_session_start' => ['policy' => 'no_limit'],
                'odiseo_agent_chat_turn' => ['policy' => 'no_limit'],
                'odiseo_agent_chat_turn_per_session' => ['policy' => 'no_limit'],
            ],
            'mailer' => ['dsn' => 'null://null'],
        ]);
    }
};
