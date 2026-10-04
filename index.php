<?php

declare(strict_types=1);

/*
 * Fallback entry point for hosts where the document root is the PROJECT root
 * (e.g. Plesk/cPanel "httpdocs") instead of the public/ folder, and root-level
 * mod_rewrite isn't routing. It simply hands off to the real front controller.
 *
 * The proper setup is to point the site's Document Root at the public/ folder
 * (see .htaccess and docs/INSTALLATION.md); this file just prevents a bare "/"
 * from failing on a plain upload.
 */

require __DIR__ . '/public/index.php';
