# ServiceNow work note formatting

Journal fields (`work_notes`, `comments`) **escape** HTML unless wrapped in `[code]…[/code]`.

Requirements (PDI defaults are fine):
- `glide.ui.security.allow_codetag` = `true`

Safe tags (platform CSS): `h3`, `h4`, `p`, `b`, `i`, `ul`/`ol`/`li`, `code`, `a`, `hr`, simple tables.

Avoid `<pre>` in Activity work notes — it creates a non-wrapping box with horizontal scroll.
Use `<ul><li><code>…</code></li></ul>` for package lines instead.

Do **not** put `<script>` inside `[code]` — HTML Sanitizer strips/escapes it.

CR `description` / `justification` are usually **not** journal fields — keep those plain text; put rich evidence in `work_notes`.
