<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * Read access to a user's pending workspace invitations, addressed by email.
 * Lets decoupled layers (the context switcher, the post-login funnel) surface
 * "you've been invited" without depending on the Memberships module internals
 * (ARCHITECTURE.md §4). Bound to InvitationService at registration.
 */
interface InvitationInbox
{
    /**
     * Pending, non-expired invitations addressed to this email.
     *
     * @return list<array{
     *   id: string,
     *   workspace_id: string,
     *   workspace_name: string,
     *   email: string,
     *   role_ids: list<string>,
     *   inviter_name: ?string,
     *   created_at: string,
     *   expires_at: ?string
     * }>
     */
    public function pendingForEmail(string $email): array;
}
