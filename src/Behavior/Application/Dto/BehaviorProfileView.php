<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Dto;

use Nizam\Behavior\Domain\BehaviorProfile;

/**
 * A flat, read-only projection of a {@see BehaviorProfile} aggregate for callers outside the domain.
 *
 * The view exposes a profile's identity, its immutable role binding, lifecycle status, current
 * version and traits, and — optionally — its full ordered revision history, all as scalars and
 * plain arrays. It is what queries return: a transport-safe snapshot that never leaks a domain
 * object or lets a caller mutate behavior.
 */
final class BehaviorProfileView
{
    /**
     * @param string                     $profileId      The profile's identity.
     * @param string                     $tenantId       The owning tenant.
     * @param string                     $roleId         The role the profile is bound to.
     * @param string                     $status         The lifecycle status value.
     * @param int                        $currentVersion The current (latest) version number.
     * @param array<string, int|string>  $currentTraits  The current traits, as scalars.
     * @param string                     $createdAt      When the profile was created (ISO-8601).
     * @param string                     $updatedAt      When the profile last changed (ISO-8601).
     * @param int                        $version        The optimistic-concurrency version.
     * @param list<BehaviorRevisionView> $revisions      The ordered revision history (empty when omitted).
     */
    public function __construct(
        public readonly string $profileId,
        public readonly string $tenantId,
        public readonly string $roleId,
        public readonly string $status,
        public readonly int $currentVersion,
        public readonly array $currentTraits,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly int $version,
        public readonly array $revisions,
    ) {
    }

    /**
     * Project a domain profile into its read-only view.
     *
     * @param bool $includeHistory Whether to materialize the full revision history into the view.
     */
    public static function fromDomain(BehaviorProfile $profile, bool $includeHistory = false): self
    {
        $revisions = [];
        if ($includeHistory) {
            foreach ($profile->revisions() as $revision) {
                $revisions[] = BehaviorRevisionView::fromDomain($revision);
            }
        }

        return new self(
            profileId: $profile->profileId()->toString(),
            tenantId: $profile->tenantId()->toString(),
            roleId: $profile->roleId()->toString(),
            status: $profile->status()->value,
            currentVersion: $profile->currentVersion(),
            currentTraits: $profile->currentTraits()->toArray(),
            createdAt: $profile->createdAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $profile->updatedAt()->format(\DateTimeInterface::ATOM),
            version: $profile->version(),
            revisions: $revisions,
        );
    }

    /**
     * A scalar-only representation suitable for JSON serialization and transport.
     *
     * @return array{
     *     profileId: string,
     *     tenantId: string,
     *     roleId: string,
     *     status: string,
     *     currentVersion: int,
     *     currentTraits: array<string, int|string>,
     *     createdAt: string,
     *     updatedAt: string,
     *     version: int,
     *     revisions: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'profileId' => $this->profileId,
            'tenantId' => $this->tenantId,
            'roleId' => $this->roleId,
            'status' => $this->status,
            'currentVersion' => $this->currentVersion,
            'currentTraits' => $this->currentTraits,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'version' => $this->version,
            'revisions' => array_map(
                static fn (BehaviorRevisionView $revision): array => $revision->toArray(),
                $this->revisions,
            ),
        ];
    }
}
