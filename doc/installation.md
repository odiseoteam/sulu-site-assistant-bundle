[Docs index](../README.md) · [Configuration →](configuration.md)

# Installation

## 1. Require the bundle

```bash
composer require odiseoteam/sulu-site-assistant-bundle symfony/ai-bundle symfony/ai-anthropic-platform
```

For OpenAI's models, require `symfony/ai-open-ai-platform` too (or instead).

## 2. Register the bundles

In `config/bundles.php`, the core before this one, in every context:

```php
Symfony\AI\AiBundle\AiBundle::class => ['all' => true],
Odiseo\AiConversationalAgentBundle\Bridge\Symfony\OdiseoAiConversationalAgentBundle::class => ['all' => true],
Odiseo\SuluSiteAssistantBundle\OdiseoSuluSiteAssistantBundle::class => ['all' => true],
```

## 3. Import the routes

The agent's routes go on the website kernel, the admin API under `/admin/api`:

```yaml
# config/routes/sulu_website.yaml
odiseo_sulu_site_assistant:
    resource: "@OdiseoSuluSiteAssistantBundle/config/routes/website.yaml"

# config/routes/sulu_admin.yaml
odiseo_sulu_site_assistant_api:
    resource: "@OdiseoSuluSiteAssistantBundle/config/routes/admin.yaml"
    prefix: /admin/api
```

The agent answers under `/agent/*`. The visitor's cookie is scoped to that path
(`widget.cookie_path`); a site that mounts the routes elsewhere sets it too.

## 4. Configure

`config/packages/odiseo_sulu_site_assistant.yaml` is the only file the assistant needs. The
runtime sections (`identity`, `models`, `budgets`, `memory`, `rules`, `sessions`…) are passed on
to the core bundle; you never write `odiseo_ai_conversational_agent:` yourself.

```yaml
odiseo_sulu_site_assistant:
    webspace: example
    identity:
        brand_name: 'Example'
        assistant_name: 'the Example assistant'
    sessions:
        timezone: 'Europe/Vienna'
    content:
        excluded_templates: [search]
        types:
            service: [service]
            case: [case-study]
            post: [article]
    contact:
        links:
            email: { label: 'hello@example.com', url: 'mailto:hello@example.com' }
            contact: { label: 'app.contact_us', url: 'page:6f1c9d2e-4b1a-4c3e-9d6f-1a2b3c4d5e6f' }
    handoff:
        routes:
            - { paths: [visitor_writes, team_contact] }
        notice:
            email:
                recipients: ['team@example.com']
                admin_url: 'https://example.com/admin'
```

[Configuration](configuration.md) lists every option; `bin/adminconsole config:dump-reference
odiseo_sulu_site_assistant` prints them with their defaults.

## 5. Connect the model

```yaml
# config/packages/ai.yaml
ai:
    platform:
        anthropic:
            api_key: '%env(ANTHROPIC_API_KEY)%'
```

Set `ANTHROPIC_API_KEY` in `.env.local`. Without a key every turn answers `not_configured` and the
widget shows the contact links. A key saved for a webspace in the admin wins over this one.

To run a role on OpenAI, add the platform and name it:

```yaml
ai:
    platform:
        openai:
            api_key: '%env(OPENAI_API_KEY)%'

odiseo_sulu_site_assistant:
    models:
        turn: { platform: openai, model: gpt-5.6-luna }
```

## 6. Create the tables and build the index

```bash
bin/adminconsole doctrine:migrations:migrate
bin/websiteconsole cmsig:seal:reindex --index=site_assistant
```

The migrations ship for MySQL, MariaDB and PostgreSQL. Publishing, unpublishing or removing a
page or an article keeps the index up to date from then on.

## 7. Put the widget on the pages

```bash
bin/websiteconsole assets:install
```

```twig
{{ site_assistant_widget() }}
{# with starter questions for this page: #}
{{ site_assistant_widget({chips: ['Do you work with Sylius?', 'How do we start?']}) }}
```

Put it on the pages that should have it, usually in the base template. It renders nothing on a
webspace whose settings turn the assistant off, nor while a shared spend cap is reached;
`site_assistant_enabled()` tells your template when to show its own fallback. The widget reads no
session, so the page stays cacheable.

## 8. Add the admin views

The admin's custom views (statistics, a conversation, a case, the rule and document editor) are
React components your admin build compiles. In `assets/admin/package.json`:

```json
"sulu-site-assistant-bundle": "file:../../vendor/odiseoteam/sulu-site-assistant-bundle/assets/admin"
```

Import them in `assets/admin/app.js`:

```js
import 'sulu-site-assistant-bundle';
```

Rebuild the admin (`bin/adminconsole sulu:admin:update-build` or your own build) and grant the
**Site assistant** permissions to the roles that should see it: conversations, handoffs,
knowledge and settings are separate.

## 9. Serve the agent

Each turn streams for several seconds. Two things matter in production:

- **No buffering on `/agent/*`.** With FrankenPHP or Caddy, `flush_interval -1` and no
  compression for that path; with nginx, `proxy_buffering off` (the core sends
  `X-Accel-Buffering: no`).
- **Workers of their own on a site with traffic.** A second PHP container that the main one
  reverse-proxies `/agent/*` to keeps a long turn from holding a worker the pages need.

The handoff's email goes out with the turn unless you route its message to a transport:

```yaml
framework:
    messenger:
        routing:
            'Odiseo\SuluSiteAssistantBundle\Handoff\Message\NotifyTeam': async
```

Run `bin/adminconsole agent:prune` daily: it deletes conversations, memory and spend past their
retention.

## Privacy

Every message the visitor writes, and the parts of your pages the assistant reads, are sent to
the model's provider (Anthropic or OpenAI) to produce the answer. Conversations are stored in your
database for `conversations.retention_days` (180 by default) and listed in the admin; what the
assistant remembers about a visitor is kept for `memory.retention_days`. Contact details reach
the database only when the visitor writes them for a case. Mention the assistant in your privacy
policy, and a link to it fits under the composer (see [Customizing](customizing.md)).
