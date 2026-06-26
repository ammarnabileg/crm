<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\CreateWorkspaceData;
use App\DTOs\RegisterUserData;
use Tests\TestCase;

/**
 * DTOs normalize and carry validated data between layers (docs/47 EAS-4).
 */
return new class extends TestCase {
    public function test_register_user_data_normalizes(): void
    {
        $dto = RegisterUserData::fromArray([
            'name'         => '  Jane Doe  ',
            'email'        => '  JANE@Example.COM ',
            'password'     => 'secret123',
            'workspace_name' => 'Acme Inc',
        ]);

        $this->assertSame('Jane Doe', $dto->name);
        $this->assertSame('jane@example.com', $dto->email);
        $this->assertSame('Acme Inc', $dto->workspaceName);
        $this->assertSame('en', $dto->locale);
    }

    public function test_register_user_data_workspace_null_when_blank(): void
    {
        $dto = RegisterUserData::fromArray([
            'name'         => 'Solo',
            'email'        => 'solo@x.com',
            'password'     => 'secret123',
            'workspace_name' => '   ',
        ]);

        $this->assertNull($dto->workspaceName);
    }

    public function test_to_array_and_only_and_except(): void
    {
        $dto = RegisterUserData::fromArray([
            'name'     => 'A',
            'email'    => 'a@b.com',
            'password' => 'p',
        ]);

        $array = $dto->toArray();
        $this->assertArrayHasKey('email', $array);
        $this->assertSame(['email' => 'a@b.com'], $dto->only('email'));
        $this->assertFalse(array_key_exists('password', $dto->except('password')));
    }

    public function test_create_workspace_data(): void
    {
        $dto = CreateWorkspaceData::fromArray(['name' => ' Globex ', 'owner_id' => '7']);
        $this->assertSame('Globex', $dto->name);
        $this->assertSame(7, $dto->ownerId);
    }

    public function test_dto_is_immutable(): void
    {
        $dto = CreateWorkspaceData::fromArray(['name' => 'X', 'owner_id' => 1]);
        $this->assertThrows(static function () use ($dto) {
            /** @phpstan-ignore-next-line intentionally mutating a readonly prop */
            $dto->name = 'Y';
        }, \Error::class);
    }
};
