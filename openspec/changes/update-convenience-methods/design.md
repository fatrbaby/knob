## Design

`increment()` and `decrement()` should compile as updates that set a column to `column +/- amount`, optionally merging additional columns into the same update. The affected row count should be returned.

`updateOrInsert()` should check for a row matching the attributes. If found, it updates with the provided values; otherwise it inserts attributes merged with values.

## Decisions

- Prefer simple, explicit behavior over database-specific upsert syntax for `updateOrInsert()`.
- Keep transaction handling the caller's responsibility.
- Require positive numeric amounts for increment/decrement unless implementation finds a clear reason to allow signed values.
