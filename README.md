# Sulu Site Assistant

A conversational assistant for [Sulu](https://sulu.io) 3 websites. From a chat widget on your
pages it answers questions about what the site publishes — services, products, cases, posts,
terms — and hands the visitor to a person when they want a quote, a date or a conversation.
Every answer comes from your own pages.

Built on [`odiseoteam/ai-conversational-agent-bundle`](https://github.com/odiseoteam/ai-conversational-agent-bundle),
which runs the agent: tools, gates, sessions, streaming, memory and budgets. This bundle adds
the Sulu layer.

## What it does

| Capability | Tools | Backed by |
|---|---|---|
| Content | `search_content`, `get_content` | Its own SEAL index of every published page and article, split into sections |
| Presentation | `present_pages`, `present_comparison` | Cards joined from what the conversation read: title, link, summary, image |
| Handoff | `present_handoff` | The contact routes you configure: contact page, email, booking link |

The assistant never makes up a page: a card or a link can only name content a tool returned
in the same conversation. It never asks for contact details; the handoff card carries the ways
to reach you and the visitor picks one.

The index reads the text of every template through its form metadata, so it works without
tagging properties with `sulu.search.field`.

In the admin, a **Site assistant** section lists the conversations with their transcripts and
shows statistics for a period: conversations, handoffs shown and opened, and AI spend.

## Requirements

- PHP 8.2+, Sulu 3.0, Doctrine ORM (MySQL/MariaDB or PostgreSQL)
- An Anthropic API key, used through `symfony/ai-bundle` and `symfony/ai-anthropic-platform`
- No frontend build: the widget ships compiled

## Installation

```bash
composer require odiseoteam/sulu-site-assistant-bundle symfony/ai-bundle symfony/ai-anthropic-platform
```

1. Register the bundles in `config/bundles.php`, the core before this one:

    ```php
    Symfony\AI\AiBundle\AiBundle::class => ['all' => true],
    Odiseo\AiConversationalAgentBundle\Bridge\Symfony\OdiseoAiConversationalAgentBundle::class => ['all' => true],
    Odiseo\SuluSiteAssistantBundle\OdiseoSuluSiteAssistantBundle::class => ['all' => true],
    ```

2. Import the routes: the agent's in `config/routes/sulu_website.yaml`, the admin API in
   `config/routes/sulu_admin.yaml`:

    ```yaml
    # sulu_website.yaml
    odiseo_sulu_site_assistant:
        resource: "@OdiseoSuluSiteAssistantBundle/config/routes/website.yaml"

    # sulu_admin.yaml
    odiseo_sulu_site_assistant_api:
        resource: "@OdiseoSuluSiteAssistantBundle/config/routes/admin.yaml"
        prefix: /admin/api
    ```

3. Configure Claude in `config/packages/ai.yaml` and set `ANTHROPIC_API_KEY`:

    ```yaml
    ai:
        platform:
            anthropic:
                api_key: '%env(ANTHROPIC_API_KEY)%'
    ```

4. Configure the assistant in `config/packages/odiseo_sulu_site_assistant.yaml`. This is the
   only config file: the runtime sections (`identity`, `models`, `budgets`, `memory`,
   `latency`, `fence`, `sessions`, `conversations`, `evals_dir` and the core keys of `limits`)
   are passed on to the core bundle.

    ```yaml
    odiseo_sulu_site_assistant:
        webspace: example
        identity:
            brand_name: 'Example'
            assistant_name: 'the Example assistant'
            scope: 'what Example offers, how it works, and putting the visitor in touch'
        sessions:
            timezone: 'Europe/Vienna'
        content:
            excluded_templates: [search]
            # Kinds of page the visitor can ask for, as name → template keys.
            types:
                service: [service]
                case: [case-study]
                post: [article]
            image_format: 'sulu-400x400'
        handoff:
            contact_page: '<uuid of the contact page>'
            email: 'hello@example.com'
            booking_url: ~
        # Words in your visitors' languages: a message with a term and a cue starts from a search.
        grounding:
            terms: [service, pricing, case]
            cues: ['?', how, what, do you]
    ```

    `bin/console config:dump-reference odiseo_sulu_site_assistant` lists every option.

5. Create the tables and build the index:

    ```bash
    bin/adminconsole doctrine:migrations:migrate
    bin/adminconsole cmsig:seal:reindex --index=site_assistant
    ```

    Publishing, unpublishing or removing a page or article keeps the index up to date.

6. Publish the widget and put it on the pages that should have it:

    ```bash
    bin/websiteconsole assets:install
    ```

    ```twig
    {{ site_assistant_widget() }}
    {# or with starter questions for this page: #}
    {{ site_assistant_widget({chips: ['Do you ship abroad?', 'How do I start?']}) }}
    ```

    The widget reads no session, so the page stays cacheable. The visitor is identified by a
    signed cookie scoped to `/agent`.

7. Add the admin views to your admin build. In `assets/admin/package.json`:

    ```json
    "sulu-site-assistant-bundle": "file:../../vendor/odiseoteam/sulu-site-assistant-bundle/assets/admin"
    ```

    Import it in `assets/admin/app.js` (`import 'sulu-site-assistant-bundle';`), rebuild the
    admin, and grant the **Site assistant** permission to the roles that should see it.

## Styling

Colors, fonts, radii and motion come from `--sa-*` custom properties. Redefine them on
`.agent-widget` in your own stylesheet; the selector wins over the defaults whatever order the
stylesheets load in:

```css
.agent-widget {
    --sa-font: 'Inter', sans-serif;
    --sa-primary: #0f766e;
    --sa-primary-deep: #115e59;
}
```

Every text lives under `odiseo_sulu_site_assistant.*` in the `messages` domain, in English and
Spanish: override any of them in your `translations/`. `card.type.<type>` labels the kinds of
page configured under `content.types`. To change the markup, override
`templates/bundles/OdiseoSuluSiteAssistantBundle/widget.html.twig`.

## Running it

Each turn streams for several seconds. Serve `/agent/*` from its own PHP workers if your site
has traffic — for example a second FrankenPHP container that the main one reverse-proxies
`/agent/*` to, with `flush_interval -1` and compression off for that path.

- `bin/websiteconsole site-assistant:search "query" --locale=en` shows what the assistant finds;
  `--show <content_id>` prints a page as it reads it.
- `bin/websiteconsole agent:chat` chats from the terminal.
- `bin/adminconsole agent:prune` deletes conversations and memory past their retention. Run it
  daily.

## Replacing a part

Services are registered as `odiseo_sulu_site_assistant.*`. To replace the content source, alias
`Odiseo\SuluSiteAssistantBundle\Content\ContentIndexInterface` to your own service. The
entities can be extended: set `objects.<name>.model` to your subclass and migrate the
difference.

## Development

The widget sources are in `assets/widget` and build into `public/`:

```bash
npm install && npm run build
```

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## License

[MIT](LICENSE).
