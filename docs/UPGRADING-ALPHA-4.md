# Upgrading from Alpha 4.0–4.2

Alpha 4.0/4.1 registered `deleted_post_meta` against a strictly typed callback whose
first parameter expected an integer. WordPress supplies an array of deleted metadata
IDs to that hook. Because WordPress deletes the uploaded ZIP attachment during
`File_Upload_Upgrader::cleanup()`, an **already-loaded old Core** can fatal after a new
ZIP has been uploaded but before the update request completes.

This is an in-memory upgrade-request problem: the replacement ZIP cannot change the
PHP class that was loaded earlier in the same request.

For the one-time upgrade to 3.0.0-alpha.4.4, use one of these safe paths:

1. Deactivate CGM Core, upload/replace the plugin, then reactivate it. Temporarily
   deactivate dependent CGM plugins first if WordPress blocks Core deactivation.
2. On a development/local install, replace the `wp-content/plugins/cgm-core` folder
   directly while Core is not executing a request.
3. Hot-patch the existing `src/Cache/Invalidator.php` before using the WordPress ZIP
   upgrader. Alpha 4.3 ships a separate emergency replacement file for this purpose.

Alpha 4.3 uses separate, untyped WordPress-hook boundary methods and normalizes values
inside Core. This prevents the same class of fatal from `deleted_post_meta` and avoids
strict scalar assumptions at other WordPress hook boundaries in this invalidator.
