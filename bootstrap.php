<?php
declare(strict_types=1);

/*
 * Loaded by Composer's autoloader on every request and command.
 *
 * It registers a controller resolver for the package's few addresses, the
 * template directives (@component, @slot …) and, when flocms-admin 0.43+ is
 * installed, the package's blocks and settings. No file is read and no
 * database is touched until a page asks for it. Removing the package removes
 * this file, so the site is back to what it was.
 */

if (class_exists(\FloCMS\Core\App::class) && method_exists(\FloCMS\Core\TemplateEngine::class, 'extend')) {
    \FloCMS\Frontend\Frontend::boot();
}
