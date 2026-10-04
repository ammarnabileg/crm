<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * The public-facing company profile of a workspace, addressed by its slug. Lets
 * decoupled layers (the public careers page) render a tenant's brand + company
 * info without depending on the Workspaces module internals (ARCHITECTURE.md §4).
 * Bound to the Workspaces module's implementation at registration.
 */
interface CompanyDirectory
{
    /**
     * Resolve an ACTIVE workspace's public company profile by slug, or null when
     * no such active workspace exists (unknown, archived, suspended or deleted).
     *
     * @return array{
     *   id: string,
     *   name: string,
     *   slug: string,
     *   industry: string,
     *   website: string,
     *   contact_email: string,
     *   contact_phone: string,
     *   about: string,
     *   tagline: string,
     *   logo_text: string,
     *   brand_color: string,
     *   logo_file_id: ?string,
     *   legal_name: string,
     *   terms_url: string,
     *   privacy_url: string,
     *   address: string
     * }|null
     */
    public function findBySlug(string $slug): ?array;
}
