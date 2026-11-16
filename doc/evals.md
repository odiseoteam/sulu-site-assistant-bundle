[← Console and showcase](console.md) · [Docs index](../README.md)

# Evals

`agent:eval` runs conversations against the real model and grades them, so a change to a rule, a
skill or the model is measured before it reaches visitors. It is the core's command; this bundle
adds the site's side of a case and a grader for handoffs.

## Running

```bash
bin/adminconsole agent:eval                       # every case in evals_dir
bin/adminconsole agent:eval --tag handoff --trials 3 --min-pass 2
bin/adminconsole agent:eval --replay var/evals/eval-<date>.json
```

Each case runs in a transaction that is rolled back: the cases it opens and the conversations it
saves do not stay. Live runs call the model and cost money; `--replay` grades a stored run again
with no model call.

## A case

```json
{
    "id": "handoff-002-a-price-is-a-case",
    "priority": "critical",
    "tags": ["handoff"],
    "state": { "webspace": "example", "locale": "es", "seen_pages": ["pages__<uuid>__es"] },
    "turns": ["¿Cuánto sale una tienda?"],
    "expected": {
        "calls_tool": ["request_handoff"],
        "handoff": { "topic": "quote", "path": "visitor_writes" },
        "rubric": "PASS if it quotes no price and passes the case on."
    }
}
```

## The site's preconditions

| `state` key | What it sets |
|---|---|
| `webspace` | The webspace the visitor talks on; the default one without it |
| `locale` | The language of the visitor's page; the webspace's default without it |
| `seen_pages` | Content ids the visitor has already seen, as a search would have left them |

## The handoff key

`expected.handoff.topic` asks for a `request_handoff` on that topic; `expected.handoff.path` asks for
a card that registered the case on that path. Either can be left out.

The core's keys (`calls_tool`, `never_calls`, `ui_components`, `reply_omits`, `closes_on`, a
`rubric` for the judge model…) are documented in the core bundle.

## Your own cases

Keep them in `config/site_assistant/evals` (`evals_dir`), one file per area. Write the turns in
your visitors' words and languages, and a rubric that says what passes and what fails. The test
application's cases (`tests/Application/evals`) are a starting point.
