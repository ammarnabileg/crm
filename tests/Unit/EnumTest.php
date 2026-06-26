<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Enums\CompanyStatus;
use App\Domain\Enums\SubscriptionStatus;
use App\Domain\Enums\UserStatus;
use Tests\TestCase;

/**
 * Confirms the domain enums match the DB ENUM values and expose helpers used at
 * layer boundaries (docs/47 Validation).
 */
return new class extends TestCase {
    public function test_user_status_values_match_schema(): void
    {
        $this->assertSame(['active', 'suspended', 'pending'], UserStatus::values());
    }

    public function test_company_status_operational(): void
    {
        $this->assertTrue(CompanyStatus::Trial->isOperational());
        $this->assertTrue(CompanyStatus::Active->isOperational());
        $this->assertFalse(CompanyStatus::Suspended->isOperational());
        $this->assertFalse(CompanyStatus::Canceled->isOperational());
    }

    public function test_subscription_status_active(): void
    {
        $this->assertTrue(SubscriptionStatus::Trialing->isActive());
        $this->assertTrue(SubscriptionStatus::Active->isActive());
        $this->assertFalse(SubscriptionStatus::Expired->isActive());
        $this->assertFalse(SubscriptionStatus::PastDue->isActive());
    }

    public function test_from_string_resolves_case(): void
    {
        $this->assertTrue(SubscriptionStatus::from('past_due') === SubscriptionStatus::PastDue);
    }

    public function test_labels_are_human_readable(): void
    {
        $this->assertSame('Past due', SubscriptionStatus::PastDue->label());
        $this->assertSame('Trial', CompanyStatus::Trial->label());
    }
};
