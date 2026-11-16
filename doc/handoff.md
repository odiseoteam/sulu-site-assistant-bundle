[← The admin](admin.md) · [Docs index](../README.md) · [The admin API →](api.md)

# Handing off to a person

When the visitor wants something only your team can give (a price, a date, a call) or the
assistant cannot solve it, it passes the case on. The visitor gets a reference and a clear next
step, the team gets the case with a summary, and both see the same reference.

Nothing happens until a route is set. Without one the assistant has no handoff tool, and says so
when asked for something only a person can give.

## The four parts

| Part | What it answers | Set in |
|---|---|---|
| Topic | Why the case is handed off: a quote, a request for a person | `handoff.topics` |
| Route | Which paths a topic can take, and when | `handoff.routes` |
| Path | How the case goes on for the visitor: they write, or the team contacts them | A tagged service |
| Notice | How the team hears of it: an email, a CRM, a chat message | A tagged service |

A topic picks its route, the route picks a path, and a path can send a notice. They are separate
on purpose: a site that moves from email to a CRM changes the notice and keeps its paths.

## Turning it on

```yaml
odiseo_sulu_site_assistant:
    sessions:
        timezone: Europe/Madrid
    handoff:
        hours:
            mon: ['09:00-18:00']
            tue: ['09:00-18:00']
        routes:
            - { when: { hours: closed }, paths: [visitor_writes] }
            - { paths: [visitor_writes, team_contact] }
        notice:
            email:
                recipients: ['team@example.com']
                from: 'assistant@example.com'
                admin_url: 'https://example.com/admin'
                locale: es
```

An invalid configuration (an unknown path or condition, a wrong day, a topic without its fields,
`team_contact` without a notice) fails when the container compiles, not in a conversation.

## Topics

The bundle brings five. A site changes them under the same id, adds its own, or turns one off.

| Id | When | Category | Mode |
|---|---|---|---|
| `person` | The visitor asks for a person, a call or to be contacted | `visitor_request` | `direct` |
| `quote` | A price, a quote, availability, a start date, hiring something | `sale` | `direct` |
| `support` | Someone already working with you has a problem with that work | `support` | `direct` |
| `sensitive` | A lawyer, a legal claim, a complaint to an authority, the press | `out_of_scope` | `direct` |
| `unresolved` | Nothing published answers it, the visitor is upset or repeats themselves | `knowledge_unavailable` | `offer` |

An `offer` topic is offered once before anything is saved; `direct` hands off at once. The
description is what the assistant reads to pick the topic, so it is prompt text:

```yaml
odiseo_sulu_site_assistant:
    handoff:
        topics:
            sensitive: { enabled: false }
            partnership:
                description: 'An agency or a freelancer wants to work with us.'
                category: other
                mode: direct
                routes:
                    - { paths: [visitor_writes] }
```

## Routes

A route's `paths` are the ways the case can go on; `when` lists conditions that must all hold. The
most specific routes win: a topic's in a webspace, then the webspace's, then the topic's, then the
global ones. Within a list, the first route that applies wins.

| Condition | Values |
|---|---|
| `hours` | `open` or `closed`, by `handoff.hours` (or the webspace's) in `sessions.timezone` |
| `contact` | `any`, `email` or `phone`: that contact is known |
| `locale` | A locale or a list: the language of the visitor's page |

With several paths, `choice: visitor` (the default) asks the visitor and `choice: priority` takes
the first one the known contact allows. A path that needs a contact holds the case until the
visitor gives one; `contact: optional` goes on without it and `contact: off` ignores it.

Per webspace:

```yaml
odiseo_sulu_site_assistant:
    handoff:
        webspaces:
            shop:
                hours: { sat: ['10:00-14:00'] }
                topics:
                    support: { enabled: false }
```

## Paths

| Path | What happens | Needs |
|---|---|---|
| `visitor_writes` | The card's first contact link opens with the reference in the message | Nothing |
| `team_contact` | The team gets a notice and contacts the visitor | An email or a phone |

A path is a service implementing `Handoff\HandoffPathInterface`, tagged
`odiseo_sulu_site_assistant.handoff_path` with a `path` name: a booking path that creates a slot,
say. Its `open()` returns the sentence the assistant may say and optional buttons for the card;
throwing marks the case as failed and the card shows every contact link instead.

## Notices

The email notice is registered when `handoff.notice.email.recipients` lists an address. The
visitor's email goes as Reply-To, and with `admin_url` the email links the case. Override its
template in `templates/bundles/OdiseoSuluSiteAssistantBundle/email/handoff_notice.html.twig`.

Another notice is a service implementing `Handoff\Notice\HandoffNoticeInterface`, tagged
`odiseo_sulu_site_assistant.handoff_notice`. Every notice runs for a case on `team_contact`; a
case is notified once, and a notice that throws leaves it for a retry.

## The card

The card is the assistant's only way to show a case: it shows the reference and what happens next
from what the desk decided, never from the model's words. The texts are translations under
`odiseo_sulu_site_assistant.card.handoff`, per path (`path.<name>`, `heading.<name>`,
`text.<name>`). Opening one of its links is recorded on the conversation and told to the
assistant on the next turn.

## The case

A case is saved before its path runs, so a failing path or a visitor who leaves does not lose it.
The reference is random (`W-` and six characters with no ambiguous glyphs); `reference_prefix`
changes the `W-`. A second request on the same topic in the same conversation updates the case.

## Events

Integrations listen to `Handoff\Event\HandoffEvent`:

| Event | When |
|---|---|
| `odiseo_sulu_site_assistant.handoff.opened` | A case was saved |
| `odiseo_sulu_site_assistant.handoff.updated` | The assistant updated it |
| `odiseo_sulu_site_assistant.handoff.path_opened` | Its path ran |
| `odiseo_sulu_site_assistant.handoff.failed` | Its path failed |
| `odiseo_sulu_site_assistant.handoff.state_changed` | The team moved it in the admin |

## Evals

The `handoff` key of an eval case asks for a topic and a registered path; see [Evals](evals.md).
