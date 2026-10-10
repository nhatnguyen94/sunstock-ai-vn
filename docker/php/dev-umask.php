<?php

// Prepended to every PHP process (php.ini: auto_prepend_file) of the dev stack. Without it a file first created by `docker compose exec` (root,
// umask 022) cannot be rewritten by php-fpm (www-data) — a compiled Blade view that changes later would fail with "Permission denied".
// Off unless the compose file asks for it, so a production deploy keeps the normal umask.
if (getenv('PHP_DEV_UMASK') === '1') {
    umask(0);
}
