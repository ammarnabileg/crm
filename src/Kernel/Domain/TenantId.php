<?php

declare(strict_types=1);

namespace Nizam\Kernel\Domain;

/**
 * The identity of a tenant in the multi-tenant platform.
 *
 * Every tenant-scoped record carries a `tenant_id`, and the current tenant is tracked at runtime by
 * {@see \Nizam\Kernel\Tenancy\TenantContext}. Being its own type (rather than a bare string or a
 * {@see UserId}) prevents accidentally scoping data to the wrong tenant.
 */
final class TenantId extends Identifier
{
}
