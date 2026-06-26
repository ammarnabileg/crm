<?php

declare(strict_types=1);

namespace Database;

use App\Core\Database;

/**
 * Base class every migration extends. Migrations are MySQL-specific (the
 * platform targets MySQL only) and express schema changes as explicit SQL so
 * there are no surprises about the generated DDL.
 */
abstract class Migration
{
    abstract public function up(Database $db): void;

    abstract public function down(Database $db): void;
}
