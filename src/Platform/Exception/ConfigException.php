<?php

declare(strict_types=1);

namespace Nizam\Platform\Exception;

/**
 * Raised when configuration is missing, malformed, or a required environment value is absent.
 *
 * @see \Nizam\Platform\Config\Config
 * @see \Nizam\Platform\Config\Env
 */
final class ConfigException extends PlatformException
{
}
