# ServiceNow work note formatting

Journal fields (`work_notes`, `comments`) **escape** HTML unless wrapped in `[code]…[/code]`.

Requirements (PDI defaults are fine):
- `glide.ui.security.allow_codetag` = `true`

Safe tags (platform CSS): `h3`, `h4`, `p`, `b`, `i`, `ul`/`ol`/`li`, `pre`, `code`, `a`, `hr`, simple tables.

Do **not** put `<script>` inside `[code]` — HTML Sanitizer strips/escapes it.

CR `description` / `justification` are usually **not** journal fields — keep those plain text; put rich evidence in `work_notes`.
