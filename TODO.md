# TODO

- [x] Update UserStoreRequest to validate role_ids / role (and role.* exists)
- [x] Refactor UserController store() role-sync parsing to match update() logic (or vice-versa) and optionally extract helper
- [x] Ensure store() unsets role_ids/role before calling update-like logic, and only syncs when role IDs are non-empty
- [x] PHP lint modified files
- [x] Run test suite (if present)
