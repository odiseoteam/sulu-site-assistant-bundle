[← Installation](installation.md) · [Docs index](../README.md) · [Knowledge →](knowledge.md)

# Configuration

Everything lives under `odiseo_sulu_site_assistant`. Defaults are neutral: no lexicon means no
forced search, no route means no handoff. Setting a list replaces the default. Many of the values
can also be changed per webspace in the [admin](admin.md), over what the configuration says.

```yaml
odiseo_sulu_site_assistant:
    # The webspace a request falls back to when it names none; null takes the first one.
    webspace: example

    content:
        resource_keys: [pages, articles]   # what goes into the index
        excluded_templates: [search]       # never indexed
        types:                             # kinds of page the visitor may ask for
            service: [service]
            case: [case-study]
            post: [article]
        search_notes: 'Services and cases are in English and Spanish.'
        image_format: sulu-400x400         # thumbnails on the cards

    grounding:                             # see below
        content_terms: [service, servicio]
        content_cues: ['?', how, cómo]
        knowledge_terms: [payment, pago, privacy]
        knowledge_cues: ['?', how]

    contact:
        links:                             # see below
            whatsapp: { label: WhatsApp, url: 'https://wa.me/5491100000000' }

    widget:
        new_chat_button: true
        link_hosts: [wa.me, calendly.com]  # links in a reply that stay clickable
        template: '@OdiseoSuluSiteAssistant/widget.html.twig'
        cookie_path: /agent

    access:                                # see below
        restricted: false
        token: ''
        query_parameter: agent

    handoff: { … }                         # see Handing off to a person

    knowledge_dir: '%kernel.project_dir%/config/site_assistant/knowledge'
    evals_dir: '%kernel.project_dir%/config/site_assistant/evals'

    limits:
        max_search_results: 6
        # The core's limits go here too: max_components_per_turn, max_rules, max_guides…

    showcase:
        enabled: '%kernel.debug%'
        fixture: ''

    encryption:
        secret: '%kernel.secret%'          # encrypts the API keys saved in the admin

    # Forwarded as they are to the core bundle; see its documentation for their keys.
    identity: { brand_name: Example, assistant_name: 'the Example assistant', brand_voice: 'plain and specific' }
    models:
        turn: { platform: anthropic, model: claude-haiku-5-5, thinking_effort: low }
        memory: { platform: anthropic, model: claude-haiku-5-5 }
        judge: { platform: anthropic, model: claude-sonnet-5-5 }
    budgets: { session_usd: 0.5, client_usd: ~, daily_usd: 20, monthly_usd: ~ }
    memory: { enabled: true, retention_days: 180 }
    sessions: { timezone: Europe/Vienna, retention_days: 30 }
    conversations: { retention_days: 180 }
    rules: { … }                           # see Rules
```

`identity.audience` is the bundle's: the assistant always talks with a visitor of the site.

## Content types

`content.types` groups templates into kinds the visitor names: "show me your cases". The model can
pass a kind to `search_content`, and the card shows its label: translate `card.type.<kind>` under
`odiseo_sulu_site_assistant` in your `messages` translations.

## Grounding

A message that names a term and a cue starts from a search the host runs before the model
answers, so a question about your offer or your terms is never answered from general knowledge.
Write the lexicons in your visitors' languages. `content_*` forces `search_content`, `knowledge_*`
forces `search_knowledge`. Without them the model still searches; the lexicon only makes it sure.

## Rules

The organisation's behaviour rules, in the cached prompt on every turn:

```yaml
odiseo_sulu_site_assistant:
    rules:
        no-prices:
            title: No prices
            category: sales          # style, sales, clarification or other
            text: 'Never quote a price or a timeline; pass the case to the team.'
```

Keep each to a sentence: a fact belongs in the [knowledge](knowledge.md). Rules can be edited,
added and turned off in the admin, with versions. `limits.max_rules` and `limits.max_rule_chars`
cap them.

## Contact links

Where the widget sends a person: the buttons of a handoff card, and the way on when the assistant
cannot answer (a reached cap, an outage). By name, in order; the first is the main button.

| Key | What it is |
|---|---|
| `label` | The button's text. It goes through the translator, so it can be a translation key |
| `url` | `https://…`, `mailto:…`, `tel:…`, or `page:<uuid>` for a page of the site in the visitor's language |
| `detail` | A line under the button, also translated |

When a case is handed off, the first link carries the reference: in the subject of a mail, in the
`text` of a messaging link, and as `?reference=` on a page of the site, for its contact form to
fill in. A link to a page that is not published is left out.

## Spend caps

`budgets.session_usd`, `budgets.client_usd` (per client and day), `budgets.daily_usd` and
`budgets.monthly_usd` stop a turn once reached; the widget then gives way to the contact links,
with `error.limit.<cap>` as its message. While the day or month cap is reached the widget is not
rendered at all. A webspace can set its own session, day and month caps in the admin; spend is
recorded per webspace.

## Restricted access

For a launch, only browsers that came in with the token see the assistant:

```yaml
odiseo_sulu_site_assistant:
    access:
        restricted: true
        token: '%env(SITE_ASSISTANT_ACCESS_TOKEN)%'
```

Open any page with `?agent=<token>`: the browser gets a signed cookie and the page reloads without
the token. Everyone else gets no widget and a 404 from `/agent/*`. No PHP session is involved, so
pages stay cacheable: the widget is in the page, hidden, and shows itself only where the cookie
is. A new token closes every earlier grant; an empty one keeps the assistant closed.

## Environment variables

| Variable | Used by |
|---|---|
| `ANTHROPIC_API_KEY`, `OPENAI_API_KEY` | `ai.platform.*`, when no webspace has its own key |
| `APP_SECRET` | The visitor cookie's and the access grant's signature, and the API keys' encryption by default |

## Logging

The bundle logs on the `site_assistant` channel (a failed handoff path, an activity it could not
record); the core logs on `conversational_agent`. Where they go is your monolog configuration.

## Rate limits

The core's limiters (`odiseo_agent_session_start`, `odiseo_agent_chat_turn`,
`odiseo_agent_chat_turn_per_session`) and the bundle's `odiseo_site_assistant_contact_link` are
Symfony rate limiters: override them under `framework.rate_limiter`.
