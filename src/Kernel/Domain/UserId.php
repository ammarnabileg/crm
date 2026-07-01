<?php

declare(strict_types=1);

namespace Nizam\Kernel\Domain;

/**
 * The identity of a user.
 *
 * A distinct type from {@see TenantId} and every other identifier, so the type system rejects
 * passing a user id where a different id is expected.
 */
final class UserId extends Identifier
{
}
