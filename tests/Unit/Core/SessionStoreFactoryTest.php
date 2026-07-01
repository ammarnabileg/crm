<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Core;

use HaHireAI\Core\Http\Session\FileSessionStore;
use HaHireAI\Core\Http\Session\SessionConfig;
use HaHireAI\Core\Http\Session\SessionStore;
use HaHireAI\Core\Http\Session\SessionStoreFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SessionStoreFactoryTest extends TestCase
{
    public function test_file_driver_is_built_in(): void
    {
        $factory = new SessionStoreFactory();
        $store = $factory->make(SessionConfig::fromArray(['driver' => 'file', 'files' => sys_get_temp_dir() . '/s']));

        $this->assertInstanceOf(FileSessionStore::class, $store);
        $this->assertSame('file', $store->name());
    }

    public function test_unknown_driver_fails_loudly(): void
    {
        $factory = new SessionStoreFactory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported session driver [redis]');

        $factory->make(SessionConfig::fromArray(['driver' => 'redis']));
    }

    public function test_a_custom_driver_can_be_registered(): void
    {
        $factory = new SessionStoreFactory();
        $factory->extend('memory', static fn (SessionConfig $c): SessionStore => new class implements SessionStore {
            public function configure(): void
            {
            }

            public function name(): string
            {
                return 'memory';
            }
        });

        $store = $factory->make(SessionConfig::fromArray(['driver' => 'memory']));

        $this->assertSame('memory', $store->name());
    }

    public function test_file_store_creates_its_directory(): void
    {
        $path = sys_get_temp_dir() . '/hahireai_sess_' . bin2hex(random_bytes(4));
        $this->assertDirectoryDoesNotExist($path);

        (new FileSessionStore($path))->configure();

        $this->assertDirectoryExists($path);
        $this->assertFileExists($path . '/.htaccess');

        // cleanup
        @unlink($path . '/.htaccess');
        @rmdir($path);
    }
}
