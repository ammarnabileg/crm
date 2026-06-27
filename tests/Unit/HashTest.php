<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Hash;
use Tests\TestCase;

/**
 * Password hashing portability (install regression). Argon2id's `threads` cost must
 * be 1 — hosts whose libargon2 is single-threaded reject anything higher with
 * "A thread value other than 1 is not supported by this implementation", which once
 * broke admin creation in the installer. make() must always produce a verifiable
 * hash (Argon2id where possible, else bcrypt) and never need an immediate rehash.
 */
return new class extends TestCase {
    public function test_make_produces_a_verifiable_hash(): void
    {
        $hash = Hash::make('S3cret-Pass!');
        $this->assertTrue($hash !== '');
        $this->assertTrue(Hash::verify('S3cret-Pass!', $hash));
        $this->assertFalse(Hash::verify('wrong', $hash));
    }

    public function test_uses_a_known_algorithm_and_a_single_argon_thread(): void
    {
        $hash = Hash::make('another-secret');
        $name = (string) (password_get_info($hash)['algoName'] ?? '');
        $this->assertTrue(in_array($name, ['argon2id', 'bcrypt'], true), "unexpected algo: {$name}");

        // When Argon2id is used, the encoded parameters must pin parallelism to 1
        // (the portable value); never p=2 (the cost that failed on single-thread hosts).
        if ($name === 'argon2id') {
            $this->assertTrue(str_contains($hash, 'p=1'));
            $this->assertFalse(str_contains($hash, 'p=2'));
        }
    }

    public function test_a_fresh_hash_does_not_need_rehash(): void
    {
        $this->assertFalse(Hash::needsRehash(Hash::make('fresh')));
    }

    public function test_empty_hash_is_handled_safely(): void
    {
        $this->assertFalse(Hash::verify('anything', ''));
        $this->assertTrue(Hash::needsRehash(''));
    }
};
