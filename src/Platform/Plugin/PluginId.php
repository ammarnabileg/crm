<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin;

use Nizam\Kernel\Domain\Identifier;

/**
 * The identity of a {@see RegisteredPlugin} within the platform.
 *
 * A plugin's install is addressed by this UUID-backed identifier, distinct from its human-readable
 * manifest name: the name identifies the *plugin* (and may be reused across versions and re-installs),
 * while the {@see PluginId} identifies a particular registry record. Being its own type prevents a
 * plugin id from being confused with any other {@see Identifier} in the system. Mint with
 * {@see PluginId::generate()} or rehydrate with {@see PluginId::fromString()}.
 */
final class PluginId extends Identifier
{
}
