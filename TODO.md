# TODO

- [x] Fix RoleController@update permission syncing (IDs -> names) to allow multiple permissions.
- [x] Normalize comma-separated permissions input (e.g. ["1,2,3,4"]) in RoleController@update.
- [x] Fix UserController@update undefined `$user` variable.
- [ ] Enable Spatie role assignment on User update.
  - [ ] Add Spatie `HasRoles` trait (and `HasPermissions` if needed) to `app/Models/User.php`.
  - [ ] Re-test user update with payload containing `role`.

