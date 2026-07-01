<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

use Nizam\Platform\Exception\InvalidArgumentException;

/**
 * The fourteen behavior style axes that a {@see \Nizam\Behavior\Domain\ValueObject\BehaviorTraits}
 * describes, as a first-class, enumerable registry.
 *
 * Each case names one axis and knows the backed style enum that populates it. This lets the domain
 * treat the axes as data — iterating them, reading an axis value by name, and coercing a persisted
 * string back to the correct enum — which is what makes deterministic, per-axis consolidation
 * (majority/weighted voting across approved practice) and per-axis observations possible without
 * hand-written switch statements scattered across the module. The `evidenceRequirements` field is a
 * scalar bar, not a style axis, and is intentionally NOT modeled here.
 *
 * String-backed so an axis name persists stably in JSON evidence documents and read models.
 */
enum BehaviorTraitAxis: string
{
    /** How the role reaches decisions ({@see DecisionStyle}). */
    case DecisionStyle = 'decisionStyle';

    /** How the role communicates ({@see CommunicationStyle}). */
    case CommunicationStyle = 'communicationStyle';

    /** How the role routes approvals ({@see ApprovalStyle}). */
    case ApprovalStyle = 'approvalStyle';

    /** When the role escalates ({@see EscalationStyle}). */
    case EscalationStyle = 'escalationStyle';

    /** How much risk the role accepts ({@see RiskTolerance}). */
    case RiskTolerance = 'riskTolerance';

    /** How the role orders work ({@see PriorityStrategy}). */
    case PriorityStrategy = 'priorityStrategy';

    /** How the role delegates ({@see DelegationStrategy}). */
    case DelegationStrategy = 'delegationStrategy';

    /** How the role plans ({@see PlanningStrategy}). */
    case PlanningStrategy = 'planningStrategy';

    /** How the role follows up ({@see FollowUpStrategy}). */
    case FollowUpStrategy = 'followUpStrategy';

    /** How thoroughly the role documents ({@see DocumentationStyle}). */
    case DocumentationStyle = 'documentationStyle';

    /** How the role runs coordination ({@see MeetingStyle}). */
    case MeetingStyle = 'meetingStyle';

    /** How the role negotiates ({@see NegotiationStyle}). */
    case NegotiationStyle = 'negotiationStyle';

    /** How the role engages customers ({@see CustomerInteractionStyle}). */
    case CustomerInteractionStyle = 'customerInteractionStyle';

    /** The role's quality bar ({@see QualityExpectation}). */
    case QualityExpectation = 'qualityExpectation';

    /**
     * The fully-qualified class name of the backed style enum that populates this axis.
     *
     * @return class-string<\BackedEnum>
     */
    public function enumClass(): string
    {
        return match ($this) {
            self::DecisionStyle => DecisionStyle::class,
            self::CommunicationStyle => CommunicationStyle::class,
            self::ApprovalStyle => ApprovalStyle::class,
            self::EscalationStyle => EscalationStyle::class,
            self::RiskTolerance => RiskTolerance::class,
            self::PriorityStrategy => PriorityStrategy::class,
            self::DelegationStrategy => DelegationStrategy::class,
            self::PlanningStrategy => PlanningStrategy::class,
            self::FollowUpStrategy => FollowUpStrategy::class,
            self::DocumentationStyle => DocumentationStyle::class,
            self::MeetingStyle => MeetingStyle::class,
            self::NegotiationStyle => NegotiationStyle::class,
            self::CustomerInteractionStyle => CustomerInteractionStyle::class,
            self::QualityExpectation => QualityExpectation::class,
        };
    }

    /**
     * Coerce a persisted string to the backed style enum instance for this axis.
     *
     * @param string $value The stored enum value (e.g. "data_driven").
     *
     * @throws InvalidArgumentException When the value is not a case of this axis's enum.
     */
    public function toEnum(string $value): \BackedEnum
    {
        $enumClass = $this->enumClass();
        $case = $enumClass::tryFrom($value);

        if ($case === null) {
            throw new InvalidArgumentException(
                sprintf('"%s" is not a valid value for behavior axis "%s".', $value, $this->value),
            );
        }

        return $case;
    }

    /**
     * Whether a given string is a valid value for this axis's enum.
     */
    public function accepts(string $value): bool
    {
        $enumClass = $this->enumClass();

        return $enumClass::tryFrom($value) !== null;
    }
}
