<h1 align="center">Sulu Site Assistant</h1>

<p align="center">
    <a href="https://packagist.org/packages/odiseoteam/sulu-site-assistant-bundle"><img src="https://img.shields.io/packagist/v/odiseoteam/sulu-site-assistant-bundle.svg?style=flat-square" alt="Version" /></a>
    <a href="https://github.com/odiseoteam/sulu-site-assistant-bundle/actions/workflows/build.yaml"><img src="https://img.shields.io/github/actions/workflow/status/odiseoteam/sulu-site-assistant-bundle/build.yaml?branch=master&style=flat-square" alt="Build status" /></a>
    <img src="https://img.shields.io/badge/sulu-3.0-52b6ca?style=flat-square" alt="Sulu version" />
    <img src="https://img.shields.io/badge/php-8.2%20%7C%208.3%20%7C%208.4-777bb4?style=flat-square" alt="PHP versions" />
</p>

---

A conversational assistant for [Sulu](https://sulu.io) 3 websites. From a chat widget on your
pages it answers what the site publishes (services, products, cases, posts, ways of working),
shows the pages that fit, and passes the visitor to your team with a reference when they want a
price, a date or a person. Every answer comes from your own pages and knowledge.

It is built on [`odiseoteam/ai-conversational-agent-bundle`](https://github.com/odiseoteam/ai-conversational-agent-bundle),
which runs the agent: tools, gates, sessions, streaming, memory, budgets and evals. This bundle
adds the Sulu layer.

## What you get

- **Grounded in your pages**: Its own index of every published page and article, split into
  sections, in every locale of every webspace. The assistant can only show or link a page a tool
  returned in the same conversation; it never makes one up.
- **Rich answers**: A shortlist of pages, a comparison and a step-by-step guide, streamed as they
  are written, with the suggestions that fit next.
- **Your own knowledge**: Policies, guides and answers in Markdown files or written in the admin,
  for what is not a page: terms, ways of working, what you do not take on.
- **House rules**: Short behaviour rules in the prompt on every turn ("never quote a price").
- **Handing off to a person**: Topics, routes by opening hours, language or known contact, and
  paths: the visitor writes with the reference already in the message, or the team contacts them
  and gets an email. Every case is saved, with a summary, before anything else happens.
- **Memory across visits**: What the visitor said about their work, kept per visitor.
- **An admin section**: Statistics, conversations with their transcript, the cases with their
  state, the rules and the knowledge with drafts and versions, and each webspace's settings:
  model, voice, spend caps, memory, API keys (encrypted) and the widget's texts.
- **Spend under control**: Per-conversation, per-client, daily and monthly caps, and rate limits.
  While a shared cap is reached the widget is not rendered.
- **A widget that needs no build**: Stimulus and its styles ship compiled. Themed with `--sa-*`
  custom properties; pages stay cacheable, since the visitor is a signed cookie, not a session.

## Tools

| Capability | Tools | Backed by |
|---|---|---|
| Content | `search_content`, `get_content` | The bundle's SEAL index of pages and articles |
| Knowledge | `search_knowledge` | Markdown files and documents edited in the admin |
| Presentation | `present_pages`, `present_comparison`, `present_guide` | Cards joined from what the conversation read |
| Handoff | `request_handoff`, `present_handoff` | The handoff desk: topics, routes, paths and notices |

## Documentation

|                                                 |                                                                |
| ----------------------------------------------- | -------------------------------------------------------------- |
| [Installation](doc/installation.md)             | Bundles, routes, migrations, the index, the widget, the admin  |
| [Configuration](doc/configuration.md)           | Every option, the model, the caps and restricted access        |
| [Knowledge](doc/knowledge.md)                   | Markdown files, documents in the admin, guides                 |
| [Where to improve what](doc/improving.md)       | Rule, knowledge, page, voice or skill: which to change         |
| [The admin](doc/admin.md)                       | Statistics, conversations, cases, versions and settings        |
| [Handing off to a person](doc/handoff.md)       | Topics, routes, paths, notices and events                      |
| [The admin API](doc/api.md)                     | The REST endpoints behind the admin, for integrations          |
| [Customizing](doc/customizing.md)               | Replacing a part, theming the widget, overriding templates     |
| [Console and showcase](doc/console.md)          | `agent:chat`, `site-assistant:search`, `agent:prune`, showcase |
| [Evals](doc/evals.md)                           | `agent:eval`, a case's site preconditions and its keys         |
| [Contributing](CONTRIBUTING.md)                 | The test application and the checks                            |
| [Changelog](CHANGELOG.md)                       | What changed in each version                                   |

## Compatibility

| Bundle | Sulu | PHP             | Symfony | Database                     |
| ------ | ---- | --------------- | ------- | ---------------------------- |
| `^0.2` | 3.0  | 8.2 · 8.3 · 8.4 | 7.4     | MySQL · MariaDB · PostgreSQL |

The model is Claude or OpenAI's, per role, through Symfony AI. The conversation goes to that
provider (see [Installation](doc/installation.md#privacy)).

## Credits

This bundle is maintained by [Odiseo](https://odiseo.io/en?utm_source=github&utm_medium=readme&utm_campaign=sulu-site-assistant-bundle).
Want us to help you with it or with any Sulu or Symfony project?
[Get in touch](https://odiseo.io/en/contact-us?utm_source=github&utm_medium=readme&utm_campaign=sulu-site-assistant-bundle).

## License

[MIT](LICENSE).
