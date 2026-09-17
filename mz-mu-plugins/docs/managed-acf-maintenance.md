# Managed ACF Maintenance Mode

Managed ACF automatic database mutations are enabled by default. For maintenance scripts that need a stable post-bootstrap database state, set:

```sh
MEZA_MANAGED_ACF_SUPPRESS_AUTOMATIC_MUTATIONS=1
```

This suppresses automatic managed ACF mutation callbacks during bootstrap while preserving ACF definition registration and runtime/editor inspection behavior.

After WordPress has booted, run only the intended operation explicitly:

```php
meza_run_managed_acf_maintenance_operation('dedupe_managed_field_groups');
```

Multiple operations can be run deliberately:

```php
meza_run_managed_acf_maintenance_operations([
    'dedupe_managed_field_groups',
    'sync_default_extension_field_group_fields',
]);
```

Safe CLI sequence:

1. Export a fresh database rollback artifact.
2. Boot WordPress with `MEZA_MANAGED_ACF_SUPPRESS_AUTOMATIC_MUTATIONS=1`.
3. Inspect the registered ACF definitions and current database records.
4. Disable or repair the approved target records.
5. Call only the needed `meza_run_managed_acf_maintenance_operation()` operations.
6. Verify field-group identity, active/disabled status, field-tree completeness, and sync option values.
7. Restore from the rollback artifact if an unexpected managed object changes.

Do not remove hooks dynamically or perform direct SQL mutations for managed ACF lifecycle work.
