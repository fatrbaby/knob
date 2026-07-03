## Design

Lock state should be part of SELECT components and compile after limit/offset or in the position required by a driver grammar. `lockForUpdate()` and `sharedLock()` should be convenience wrappers over a lower-level `lock()` method.

Unsupported lock modes should throw clear exceptions for the active driver.

## Decisions

- Keep transaction boundaries under caller control via existing PDO/Knob transaction methods.
- Allow raw lock strings through `lock(string $value)` for advanced database-specific modes.
- Avoid implementing `skipLocked()` or `noWait()` until a concrete need appears.
