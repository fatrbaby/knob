## Design

`whereFullText()` should accept one or more columns plus a search string. The search string must be bound. Grammar classes should own driver-specific SQL.

Where driver support is weak or version-dependent, the implementation should prefer an explicit exception over misleading SQL.

## Decisions

- Keep ranking and ordering outside the initial API.
- Use raw expressions as the advanced escape hatch.
- Keep the public API small until concrete application needs require options.
