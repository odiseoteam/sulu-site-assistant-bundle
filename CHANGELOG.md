# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Requires `odiseoteam/ai-conversational-agent-bundle` 0.5: scope areas, runtime settings, API
  keys per platform, chips in a card's own field, `retry`, tool input checks and skills that
  declare the tools they need.
- Several webspaces: the widget names its page's webspace (`X-Agent-Webspace`), searches, cases,
  settings and spend are per webspace, and `webspace` is the fallback.
- Knowledge (`search_knowledge`): Markdown files in `knowledge_dir` and documents written in the
  admin, with guides listed in the prompt.
- House rules under `rules`, in the prompt on every turn.
- Rules and knowledge edited in the admin with drafts, publications, history, comparison, copy to
  the draft and going back to the configuration.
- Handing off to a person: topics (`person`, `quote`, `support`, `sensitive`, `unresolved`), routes
  by `hours`, `contact` and `locale`, the `visitor_writes` and `team_contact` paths, an email
  notice through Messenger, `HandoffEvent`s and the `handoff` eval key.
- The handoff entity, listed and moved (take, resolve, reopen) in the admin.
- Each webspace's settings in the admin: on or off, identity and voice, widget texts per
  language, models and effort, Anthropic and OpenAI keys stored encrypted, spend caps and memory.
- Statistics by period and webspace: conversations, engaged, cases by topic, contact links
  opened, spend and cost per conversation, top pages.
- `present_guide`: how something works, as steps with the pages they come from.
- Restricted access for a launch (`access.*`), with no PHP session.
- The widget closes on a reached cap with the contact links, and is not rendered while a shared
  cap is reached; `site_assistant_enabled()` for a fallback.
- Contact links (`contact.links`), including `page:<uuid>` for a page in the visitor's language;
  a case's link carries its reference.
- The widget: a nudge, a launcher that folds, a button back to the latest message, a new
  conversation button, a retry in place, errors as a line in the turn, `agent:activity` events,
  `link_hosts`, and overridable `launcher_icon`, `brand` and `widget_foot` templates.
- `/agent/showcase`, every card over the site's own pages.
- `agent:eval` runs as a visitor of the site, with `webspace`, `locale` and `seen_pages`.
- JSON errors on the agent's routes, never the site's error page.
- A test application, functional, integration and unit suites, Vitest, PHPStan at level max,
  Deptrac, the dependency analyser and CI on MySQL and PostgreSQL.

### Changed

- `present_handoff` shows the case `request_handoff` registered, instead of the contact routes of
  the configuration; `handoff.contact_page`, `handoff.email` and `handoff.booking_url` are gone:
  use `contact.links`.
- `grounding.terms` and `grounding.cues` are `grounding.content_terms` and `content_cues`.
- The tables are `odiseo_site_assistant_*`, created by one migration for MySQL, MariaDB and
  PostgreSQL. The conversation's `handoff_shown_at` and `handoff_clicked_at` are gone; it has
  `contact_opened_at`, and the cases are their own table.
- The admin's contexts are `sulu.site_assistant.conversations`, `.handoffs`, `.knowledge` and
  `.settings`.

## [0.1.0] - 2026-09-26

### Added

- The first version: search and read of pages and articles over the bundle's SEAL index, page
  cards and a comparison, a handoff card with the configured contact routes, memory, the widget
  and the admin's conversations and statistics.
