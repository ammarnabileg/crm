<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Exception;

use RuntimeException;

/**
 * Base type for every error raised by the Plugin Platform.
 *
 * Extends the SPL {@see \RuntimeException} rather than a platform type so the plugin contracts and
 * domain stay coupled only to PHP and the Kernel, honouring the hexagonal boundary. Each instance
 * carries a stable, dotted error code under the {@see self::CODE_PREFIX} namespace (for example
 * `PLUGIN.MANIFEST_INVALID`), so callers, logs and API responses can branch on the failure kind
 * without matching on message text.
 */
class PluginException extends RuntimeException
{
    /**
     * The stable prefix shared by every Plugin Platform error code.
     */
    public const string CODE_PREFIX = 'PLUGIN';

    /**
     * @param string $errorCode The stable, dotted error code (e.g. `PLUGIN.NOT_FOUND`).
     * @param string $message   A human-readable description of the failure.
     */
    public function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * The stable, dotted error code identifying the kind of failure.
     */
    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
