<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * How a role engages customers.
 *
 * A behavior style trait. String-backed for stable persistence in trait columns and read models.
 */
enum CustomerInteractionStyle: string
{
    /** Responds to customers when they reach out. */
    case Reactive = 'reactive';

    /** Reaches out to customers ahead of need. */
    case Proactive = 'proactive';

    /** Acts as a trusted advisor guiding the customer. */
    case Advisory = 'advisory';
}
