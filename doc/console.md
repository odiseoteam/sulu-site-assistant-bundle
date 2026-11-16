[← Customizing](customizing.md) · [Docs index](../README.md) · [Evals →](evals.md)

# Console and showcase

## Commands

| Command | What it does |
|---|---|
| `bin/websiteconsole agent:chat` | Chats from the terminal as a visitor of the default webspace |
| `bin/websiteconsole site-assistant:search "query" -l es -w example` | What the assistant finds |
| `bin/websiteconsole site-assistant:search --show pages__<uuid>__en` | A page as the assistant reads it, section by section |
| `bin/websiteconsole cmsig:seal:reindex --index=site_assistant` | Rebuilds the index |
| `bin/adminconsole agent:prune` | Deletes conversations, memory and spend past their retention; run it daily |
| `bin/adminconsole agent:eval` | Runs the eval cases, see [Evals](evals.md) |

## Showcase

`/agent/showcase` renders every card the assistant can show (a shortlist, a comparison, a guide,
the handoff in each of its states, an error and the suggestions) over the site's own pages, with
no model call. It is on in debug (`showcase.enabled`) and answers 404 otherwise.

Without a fixture it takes the first pages the index returns for the webspace. A fixture picks
them, and the cards:

```json
{
    "pages": ["pages__6f1c9d2e-4b1a-4c3e-9d6f-1a2b3c4d5e6f__en"],
    "cards": [
        { "label": "Services", "tool": "present_pages", "input": { "picks": [{ "content_id": "pages__6f1c9d2e-4b1a-4c3e-9d6f-1a2b3c4d5e6f__en", "reason": "What you asked for" }] } }
    ],
    "suggestions": ["Compare them"]
}
```
