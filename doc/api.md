[← Handing off to a person](handoff.md) · [Docs index](../README.md) · [Customizing →](customizing.md)

# The admin API

The admin's views read a REST API under `/admin/api/site-assistant`, with the admin's session and
permissions. An integration (a CRM sync, a report) reads the same endpoints with a user that has
the permission.

## Endpoints

| Method | Path | Permission | What it returns |
|---|---|---|---|
| GET | `/conversations.json` | Conversations: view | Sulu list: `?page`, `?limit`, `?sortBy`, `?search`, `?webspaceKey`, `?locale` |
| GET | `/conversations/{id}.json` | Conversations: view | Transcript, cases, memory, spend, models |
| DELETE | `/conversations/{id}.json`, `/conversations.json?ids=1,2` | Conversations: delete | 204 |
| GET | `/handoffs.json` | Handoffs: view | Sulu list: `?state=requested,taken`, `?topic`, `?webspaceKey` |
| GET | `/handoffs/{id}.json` | Handoffs: view | The case and the moves it allows (`actions`) |
| PUT | `/handoffs/{id}.json?action=take\|resolve\|reopen` | Handoffs: edit | The case; 409 for a move its state does not allow |
| GET | `/statistics.json` | Conversations: view | `?period=7d\|30d\|this_month\|last_month` or `?from=&to=`, `?webspace` |
| GET | `/rules.json`, `/documents.json` | Knowledge: view | The items, with their origin and version |
| GET | `/rules/{key}.json`, `/documents/{key}.json` | Knowledge: view | The item, its draft and history |
| POST | `/rules.json?action=draft\|publish` | Knowledge: add | A new item: `{id, data, note}` |
| PUT | `/rules/{key}.json?action=draft\|publish\|discard\|reset\|restore` | Knowledge: edit | `{data, note}`, or `{version}` to restore |
| GET | `/rules/{key}/versions/{number}.json` | Knowledge: view | A version and its diff against the one in use |
| GET | `/settings.json`, `/settings/{webspace}.json?locale=` | Settings: view | The webspaces, or one's settings |
| PUT | `/settings/{webspace}.json?locale=` | Settings: edit | Saves them; 400 with `errors` |

The documents' endpoints are the same as the rules'. A publication that a cap stops answers 422
with `problems`, translated to the user's language.

## Example

```bash
curl -s -b cookies.txt 'https://example.com/admin/api/site-assistant/handoffs.json?state=requested&fields=reference,topic,contactEmail,createdAt'
```

Sign in first through `/admin/login`; see the Sulu documentation on the admin API.
