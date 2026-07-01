<?php

declare(strict_types=1);

namespace Nizam\Behavior\Infrastructure\Persistence\Pdo;

use DateTimeImmutable;
use Nizam\Behavior\Domain\BehaviorChangeProposal;
use Nizam\Behavior\Domain\BehaviorObservation;
use Nizam\Behavior\Domain\BehaviorProfile;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\BehaviorProfileRevision;
use Nizam\Behavior\Domain\Enum\ApprovalStyle;
use Nizam\Behavior\Domain\Enum\CommunicationStyle;
use Nizam\Behavior\Domain\Enum\CustomerInteractionStyle;
use Nizam\Behavior\Domain\Enum\DecisionStyle;
use Nizam\Behavior\Domain\Enum\DelegationStrategy;
use Nizam\Behavior\Domain\Enum\DocumentationStyle;
use Nizam\Behavior\Domain\Enum\EscalationStyle;
use Nizam\Behavior\Domain\Enum\FollowUpStrategy;
use Nizam\Behavior\Domain\Enum\MeetingStyle;
use Nizam\Behavior\Domain\Enum\NegotiationStyle;
use Nizam\Behavior\Domain\Enum\ObservationSourceType;
use Nizam\Behavior\Domain\Enum\PlanningStrategy;
use Nizam\Behavior\Domain\Enum\PriorityStrategy;
use Nizam\Behavior\Domain\Enum\ProfileStatus;
use Nizam\Behavior\Domain\Enum\ProposalStatus;
use Nizam\Behavior\Domain\Enum\QualityExpectation;
use Nizam\Behavior\Domain\Enum\RiskTolerance;
use Nizam\Behavior\Domain\ObservationId;
use Nizam\Behavior\Domain\ProposalId;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Behavior\Domain\ValueObject\BehaviorTraits;
use Nizam\Behavior\Domain\ValueObject\ChangeLogEntry;
use Nizam\Behavior\Domain\ValueObject\EvidenceReference;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Exception\PlatformException;
use Nizam\Platform\Support\Json;

/**
 * The single translation point between Behavior aggregates and their persisted scalar form.
 *
 * PDO adapters speak rows of strings and JSON; the domain speaks value objects, enums and
 * timestamps. This stateless mapper owns that translation in both directions, so every PDO
 * repository serializes and hydrates identically. Trait sets, change logs and evidence are stored as
 * JSON documents (the JSONB columns in Postgres, TEXT-encoded JSON in SQLite) and rebuilt here
 * through the aggregates' `reconstitute()` factories — which skip event emission, since loading a row
 * is not a new business fact. Elevated risk tolerance is honored on hydration through the
 * policy-allowance factory: a value already persisted was, by definition, permitted when written.
 */
final class BehaviorMapper
{
    /**
     * Encode a {@see BehaviorTraits} to its canonical JSON document.
     *
     * @throws PlatformException When encoding fails.
     */
    public function encodeTraits(BehaviorTraits $traits): string
    {
        return Json::encode($traits->toArray());
    }

    /**
     * Rebuild a {@see BehaviorTraits} from a persisted JSON document.
     *
     * @throws PlatformException When the JSON is invalid or a field is missing/ill-typed.
     */
    public function decodeTraits(string $json): BehaviorTraits
    {
        return $this->traitsFromArray(Json::decode($json));
    }

    /**
     * Rebuild a {@see BehaviorTraits} from its decoded array form.
     *
     * Uses the policy-allowance factory so a legitimately elevated risk value round-trips; the value
     * was validated against policy when first written.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws PlatformException When a field is missing or ill-typed.
     */
    public function traitsFromArray(array $data): BehaviorTraits
    {
        return BehaviorTraits::withPolicyAllowance(
            DecisionStyle::from($this->string($data, 'decisionStyle')),
            CommunicationStyle::from($this->string($data, 'communicationStyle')),
            ApprovalStyle::from($this->string($data, 'approvalStyle')),
            EscalationStyle::from($this->string($data, 'escalationStyle')),
            RiskTolerance::from($this->string($data, 'riskTolerance')),
            PriorityStrategy::from($this->string($data, 'priorityStrategy')),
            DelegationStrategy::from($this->string($data, 'delegationStrategy')),
            PlanningStrategy::from($this->string($data, 'planningStrategy')),
            FollowUpStrategy::from($this->string($data, 'followUpStrategy')),
            DocumentationStyle::from($this->string($data, 'documentationStyle')),
            MeetingStyle::from($this->string($data, 'meetingStyle')),
            NegotiationStyle::from($this->string($data, 'negotiationStyle')),
            CustomerInteractionStyle::from($this->string($data, 'customerInteractionStyle')),
            QualityExpectation::from($this->string($data, 'qualityExpectation')),
            $this->int($data, 'evidenceRequirements'),
            true,
        );
    }

    /**
     * Encode an ordered revision history to a JSON array document.
     *
     * @param list<BehaviorProfileRevision> $revisions
     *
     * @throws PlatformException When encoding fails.
     */
    public function encodeRevisions(array $revisions): string
    {
        return Json::encode(array_map(
            static fn (BehaviorProfileRevision $revision): array => $revision->toArray(),
            $revisions,
        ));
    }

    /**
     * Rebuild an ordered revision history from a persisted JSON array document.
     *
     * @return list<BehaviorProfileRevision>
     *
     * @throws PlatformException When the JSON is invalid or malformed.
     */
    public function decodeRevisions(string $json): array
    {
        $rows = Json::decode($json);

        $revisions = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new PlatformException('Each persisted revision must be an object.');
            }
            $revisions[] = $this->revisionFromArray($row);
        }

        return $revisions;
    }

    /**
     * Rebuild a single {@see BehaviorProfileRevision} from its decoded array form.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws PlatformException When a field is missing or malformed.
     */
    public function revisionFromArray(array $data): BehaviorProfileRevision
    {
        return new BehaviorProfileRevision(
            version: $this->int($data, 'version'),
            traits: $this->traitsFromArray($this->arr($data, 'traits')),
            changeLog: $this->changeLogFromArray($this->arr($data, 'changeLog')),
            approvedBy: $this->string($data, 'approvedBy'),
            approvedAt: $this->dateTime($data, 'approvedAt'),
            evidence: $this->evidenceListFromArray($this->arr($data, 'evidence')),
        );
    }

    /**
     * Rebuild a {@see ChangeLogEntry} from its decoded array form.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws PlatformException When a field is missing or malformed.
     */
    public function changeLogFromArray(array $data): ChangeLogEntry
    {
        return new ChangeLogEntry(
            version: $this->int($data, 'version'),
            changedAt: $this->dateTime($data, 'changedAt'),
            changedBy: $this->string($data, 'changedBy'),
            summary: $this->string($data, 'summary'),
            traitsDiff: $this->diffFromArray($this->arr($data, 'traitsDiff')),
            businessImpact: $this->string($data, 'businessImpact'),
            rollbackToVersion: $this->nullableInt($data, 'rollbackToVersion'),
        );
    }

    /**
     * Encode a list of {@see EvidenceReference} to a JSON array document.
     *
     * @param list<EvidenceReference> $evidence
     *
     * @throws PlatformException When encoding fails.
     */
    public function encodeEvidence(array $evidence): string
    {
        return Json::encode(array_map(
            static fn (EvidenceReference $reference): array => $reference->toArray(),
            $evidence,
        ));
    }

    /**
     * Rebuild a list of {@see EvidenceReference} from a persisted JSON array document.
     *
     * @return list<EvidenceReference>
     *
     * @throws PlatformException When the JSON is invalid or malformed.
     */
    public function decodeEvidence(string $json): array
    {
        return $this->evidenceListFromArray(Json::decode($json));
    }

    /**
     * Rebuild a list of {@see EvidenceReference} from a decoded array of rows.
     *
     * @param array<array-key, mixed> $rows
     *
     * @return list<EvidenceReference>
     *
     * @throws PlatformException When a row is malformed.
     */
    public function evidenceListFromArray(array $rows): array
    {
        $evidence = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new PlatformException('Each persisted evidence reference must be an object.');
            }
            $evidence[] = $this->evidenceFromArray($row);
        }

        return $evidence;
    }

    /**
     * Rebuild a single {@see EvidenceReference} from its decoded array form.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws PlatformException When a field is missing or malformed.
     */
    public function evidenceFromArray(array $data): EvidenceReference
    {
        return new EvidenceReference(
            sourceType: ObservationSourceType::from($this->string($data, 'sourceType')),
            referenceId: $this->string($data, 'referenceId'),
            summary: $this->string($data, 'summary'),
            occurredAt: $this->dateTime($data, 'occurredAt'),
            weight: $this->float($data, 'weight'),
            observedTraits: $this->observedTraitsFromValue($data['observedTraits'] ?? []),
        );
    }

    /**
     * Coerce a decoded value into a string-keyed, string-valued observed-trait map.
     *
     * Older persisted evidence predating per-trait observation carries no `observedTraits`; such a
     * value hydrates to an empty map. {@see EvidenceReference} validates the axis names and values.
     *
     * @return array<string, string>
     */
    private function observedTraitsFromValue(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $axis => $observed) {
            if (is_string($axis) && is_scalar($observed)) {
                $map[$axis] = (string) $observed;
            }
        }

        return $map;
    }

    /**
     * Rebuild a {@see BehaviorProfile} aggregate from a persisted profile row.
     *
     * @param array<string, mixed> $row A row from the behavior_profiles table.
     *
     * @throws PlatformException When the row is missing columns or malformed.
     */
    public function profileFromRow(array $row): BehaviorProfile
    {
        return BehaviorProfile::reconstitute(
            BehaviorProfileId::fromString($this->string($row, 'id')),
            TenantId::fromString($this->string($row, 'tenant_id')),
            RoleId::fromString($this->string($row, 'role_id')),
            ProfileStatus::from($this->string($row, 'status')),
            $this->int($row, 'current_version'),
            $this->decodeTraits($this->string($row, 'current_traits')),
            $this->decodeRevisions($this->string($row, 'revisions')),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
            $this->int($row, 'version'),
        );
    }

    /**
     * Flatten a {@see BehaviorProfile} to a column map ready for an INSERT/UPDATE.
     *
     * Audit columns (`created_by`, `updated_by`) are recorded from the aggregate's revision history:
     * the first revision's approver created the profile, the latest revision's approver last touched
     * it. `deleted_at` is always null on write — soft deletion is a separate operation.
     *
     * @return array<string, string|int|null>
     *
     * @throws PlatformException When encoding fails.
     */
    public function profileToRow(BehaviorProfile $profile): array
    {
        $revisions = $profile->revisions();
        $first = $revisions[0];
        $last = $revisions[count($revisions) - 1];

        return [
            'id' => $profile->profileId()->toString(),
            'tenant_id' => $profile->tenantId()->toString(),
            'role_id' => $profile->roleId()->toString(),
            'status' => $profile->status()->value,
            'current_version' => $profile->currentVersion(),
            'current_traits' => $this->encodeTraits($profile->currentTraits()),
            'revisions' => $this->encodeRevisions($revisions),
            'created_at' => $profile->createdAt()->format(DateTimeImmutable::ATOM),
            'updated_at' => $profile->updatedAt()->format(DateTimeImmutable::ATOM),
            'created_by' => $first->approvedBy(),
            'updated_by' => $last->approvedBy(),
            'version' => $profile->version(),
        ];
    }

    /**
     * Rebuild a {@see BehaviorChangeProposal} aggregate from a persisted proposal row.
     *
     * @param array<string, mixed> $row A row from the behavior_change_proposals table.
     *
     * @throws PlatformException When the row is missing columns or malformed.
     */
    public function proposalFromRow(array $row): BehaviorChangeProposal
    {
        return BehaviorChangeProposal::reconstitute(
            ProposalId::fromString($this->string($row, 'id')),
            TenantId::fromString($this->string($row, 'tenant_id')),
            RoleId::fromString($this->string($row, 'role_id')),
            BehaviorProfileId::fromString($this->string($row, 'profile_id')),
            $this->decodeTraits($this->string($row, 'proposed_traits')),
            $this->string($row, 'rationale'),
            $this->decodeEvidence($this->string($row, 'supporting_evidence')),
            $this->float($row, 'confidence'),
            $this->string($row, 'business_impact'),
            $this->nullableInt($row, 'rollback_to_version'),
            ProposalStatus::from($this->string($row, 'status')),
            $this->string($row, 'proposed_by'),
            $this->dateTime($row, 'proposed_at'),
            $this->nullableString($row, 'decided_by'),
            $this->nullableDateTime($row, 'decided_at'),
        );
    }

    /**
     * Flatten a {@see BehaviorChangeProposal} to a column map ready for an INSERT/UPDATE.
     *
     * `deleted_at` is always null on write. The proposal carries no independent optimistic version;
     * it is stored at version 1 to satisfy the shared audit schema.
     *
     * @return array<string, string|int|float|null>
     *
     * @throws PlatformException When encoding fails.
     */
    public function proposalToRow(BehaviorChangeProposal $proposal): array
    {
        $decidedAt = $proposal->decidedAt();

        return [
            'id' => $proposal->proposalId()->toString(),
            'tenant_id' => $proposal->tenantId()->toString(),
            'role_id' => $proposal->roleId()->toString(),
            'profile_id' => $proposal->profileId()->toString(),
            'proposed_traits' => $this->encodeTraits($proposal->proposedTraits()),
            'rationale' => $proposal->rationale(),
            'supporting_evidence' => $this->encodeEvidence($proposal->supportingEvidence()),
            'confidence' => $proposal->confidence(),
            'business_impact' => $proposal->businessImpact(),
            'rollback_to_version' => $proposal->rollbackToVersion(),
            'status' => $proposal->status()->value,
            'proposed_by' => $proposal->proposedBy(),
            'proposed_at' => $proposal->proposedAt()->format(DateTimeImmutable::ATOM),
            'decided_by' => $proposal->decidedBy(),
            'decided_at' => $decidedAt?->format(DateTimeImmutable::ATOM),
            'created_at' => $proposal->proposedAt()->format(DateTimeImmutable::ATOM),
            'updated_at' => ($decidedAt ?? $proposal->proposedAt())->format(DateTimeImmutable::ATOM),
            'created_by' => $proposal->proposedBy(),
            'updated_by' => $proposal->decidedBy() ?? $proposal->proposedBy(),
            'version' => 1,
        ];
    }

    /**
     * Rebuild an approved {@see BehaviorObservation} from a persisted observation row.
     *
     * Only approved observations are ever materialized through this mapper, matching the
     * {@see \Nizam\Behavior\Domain\Port\ObservationSource} contract.
     *
     * @param array<string, mixed> $row A row from the behavior_observations table.
     *
     * @throws PlatformException When the row is missing columns or malformed.
     */
    public function observationFromRow(array $row): BehaviorObservation
    {
        $observedTraits = array_key_exists('observed_traits', $row) && is_string($row['observed_traits'])
            ? $this->observedTraitsFromValue(Json::decode($row['observed_traits']))
            : [];

        $evidence = new EvidenceReference(
            sourceType: ObservationSourceType::from($this->string($row, 'source_type')),
            referenceId: $this->string($row, 'reference_id'),
            summary: $this->string($row, 'summary'),
            occurredAt: $this->dateTime($row, 'occurred_at'),
            weight: $this->float($row, 'weight'),
            observedTraits: $observedTraits,
        );

        return BehaviorObservation::approved(
            ObservationId::fromString($this->string($row, 'id')),
            TenantId::fromString($this->string($row, 'tenant_id')),
            RoleId::fromString($this->string($row, 'role_id')),
            $evidence,
            $this->string($row, 'approved_by'),
            $this->dateTime($row, 'approved_at'),
        );
    }

    /**
     * Flatten an approved {@see BehaviorObservation} to a column map ready for an INSERT.
     *
     * @return array<string, string|float|null>
     *
     * @throws PlatformException When the observation is not approved.
     */
    public function observationToRow(BehaviorObservation $observation): array
    {
        if (!$observation->isApproved()) {
            throw new PlatformException('Only approved observations may be persisted as an observation source.');
        }

        $evidence = $observation->evidence();
        $approvedAt = $observation->approvedAt();
        $approvedBy = $observation->approvedBy() ?? '';

        return [
            'id' => $observation->observationId()->toString(),
            'tenant_id' => $observation->tenantId()->toString(),
            'role_id' => $observation->roleId()->toString(),
            'source_type' => $evidence->sourceType()->value,
            'reference_id' => $evidence->referenceId(),
            'summary' => $evidence->summary(),
            'occurred_at' => $evidence->occurredAt()->format(DateTimeImmutable::ATOM),
            'weight' => $evidence->weight(),
            'observed_traits' => Json::encode($evidence->observedTraits()),
            'approved_by' => $approvedBy,
            'approved_at' => ($approvedAt ?? $evidence->occurredAt())->format(DateTimeImmutable::ATOM),
            'created_at' => ($approvedAt ?? $evidence->occurredAt())->format(DateTimeImmutable::ATOM),
            'created_by' => $approvedBy,
        ];
    }

    /**
     * Rebuild a per-trait diff map from decoded array form, discarding malformed entries defensively.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<string, array{from: string, to: string}>
     */
    private function diffFromArray(array $data): array
    {
        $diff = [];
        foreach ($data as $trait => $change) {
            if (!is_string($trait) || !is_array($change)) {
                continue;
            }
            if (!isset($change['from'], $change['to'])) {
                continue;
            }
            $diff[$trait] = [
                'from' => (string) $change['from'],
                'to' => (string) $change['to'],
            ];
        }

        return $diff;
    }

    /**
     * Read a required string field.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws PlatformException When the field is absent or not string-coercible.
     */
    private function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new PlatformException(sprintf('Persisted field "%s" must be a string.', $key));
    }

    /**
     * Read an optional string field, returning null when absent or null.
     *
     * @param array<array-key, mixed> $data
     */
    private function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return $value === null ? null : $this->string($data, $key);
    }

    /**
     * Read a required integer field.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws PlatformException When the field is absent or not an integer.
     */
    private function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '' && ctype_digit(ltrim($value, '-'))) {
            return (int) $value;
        }

        throw new PlatformException(sprintf('Persisted field "%s" must be an integer.', $key));
    }

    /**
     * Read an optional integer field, returning null when absent or null.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws PlatformException When present but not an integer.
     */
    private function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return $value === null ? null : $this->int($data, $key);
    }

    /**
     * Read a required float field.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws PlatformException When the field is absent or not numeric.
     */
    private function float(array $data, string $key): float
    {
        $value = $data[$key] ?? null;
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        throw new PlatformException(sprintf('Persisted field "%s" must be numeric.', $key));
    }

    /**
     * Read a required array field.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     *
     * @throws PlatformException When the field is absent or not an array.
     */
    private function arr(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (is_array($value)) {
            return $value;
        }

        throw new PlatformException(sprintf('Persisted field "%s" must be an array.', $key));
    }

    /**
     * Read a required timestamp field expressed as an ISO-8601/ATOM string.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws PlatformException When the field is absent or not a parseable timestamp.
     */
    private function dateTime(array $data, string $key): DateTimeImmutable
    {
        $value = $this->string($data, $key);

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception $exception) {
            throw new PlatformException(
                sprintf('Persisted field "%s" is not a valid timestamp.', $key),
                0,
                $exception,
            );
        }
    }

    /**
     * Read an optional timestamp field, returning null when absent or null.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws PlatformException When present but not a parseable timestamp.
     */
    private function nullableDateTime(array $data, string $key): ?DateTimeImmutable
    {
        $value = $data[$key] ?? null;

        return $value === null ? null : $this->dateTime($data, $key);
    }
}
