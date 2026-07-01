<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Contract;

use Nizam\Platform\Plugin\PluginInterface;

/**
 * The SDK contract for a department plugin.
 *
 * A department plugin is a self-contained bundle of roles and capabilities — a whole functional unit
 * (for example, "Sales" or "Support") installed as one unit. Beyond the base {@see PluginInterface},
 * it must enumerate the roles it provides so the platform can compose an organisation from installed
 * departments.
 */
interface DepartmentPlugin extends PluginInterface
{
    /**
     * The role identifiers this department provides.
     *
     * @return list<string>
     */
    public function providedRoles(): array;
}
