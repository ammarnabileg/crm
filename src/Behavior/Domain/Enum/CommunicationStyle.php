<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * How a role communicates with peers, stakeholders, and customers.
 *
 * A behavior style trait. String-backed for stable persistence in trait columns and read models.
 */
enum CommunicationStyle: string
{
    /** Communication follows formal register and structure. */
    case Formal = 'formal';

    /** Communication is brief and to the point. */
    case Concise = 'concise';

    /** Communication is thorough and richly detailed. */
    case Detailed = 'detailed';

    /** Communication leads with empathy and attention to the recipient. */
    case Empathetic = 'empathetic';

    /** Communication is direct and confident in stating positions. */
    case Assertive = 'assertive';
}
