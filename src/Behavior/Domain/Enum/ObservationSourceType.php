<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * The kind of approved business practice an observation was drawn from.
 *
 * Every {@see \Nizam\Behavior\Domain\BehaviorObservation} cites a source of approved practice; the
 * engine only ever evolves a profile from such approved sources, which makes the resulting behavior
 * explainable and auditable. String-backed for stable persistence in the observation/evidence tables.
 */
enum ObservationSourceType: string
{
    /** An approved decision made in the course of work. */
    case ApprovedDecision = 'approved_decision';

    /** The execution of an approved task. */
    case TaskExecution = 'task_execution';

    /** A manager's review of work. */
    case ManagerReview = 'manager_review';

    /** A ratified business policy. */
    case BusinessPolicy = 'business_policy';

    /** A standard operating procedure. */
    case StandardOperatingProcedure = 'standard_operating_procedure';

    /** An entry in the approved knowledge base. */
    case KnowledgeBase = 'knowledge_base';

    /** An approved exception to normal practice. */
    case ApprovedException = 'approved_exception';

    /** A recorded escalation and its resolution. */
    case Escalation = 'escalation';

    /** The outcome of a communication. */
    case CommunicationOutcome = 'communication_outcome';

    /** A quality review of delivered work. */
    case QualityReview = 'quality_review';
}
