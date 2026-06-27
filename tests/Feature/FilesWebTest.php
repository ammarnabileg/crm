<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\App\FileController;
use App\Core\Request;
use App\Models\File;
use App\Models\User;
use App\Services\Files\FileService;
use App\Services\Tenancy\WorkspaceService;
use Tests\TestCase;

/**
 * Files web layer (docs/30 File Upload) — the files list renders end to end
 * (controller -> service -> view -> layout), a stored upload persists a tenant-scoped
 * `files` row on the local provider with a checksum and shows up in the listing, a
 * delete soft-deletes the row and removes the bytes, and a download/listing never
 * leaks another workspace's file. Reads are gated by recruitment.view and writes by
 * recruitment.manage, enforced inside the controller via abort_unless(can(...)).
 *
 * Fixtures are built fresh inside a rolled-back transaction (mirrors MembersWebTest)
 * so the suite never depends on or mutates seeded data: a brand-new workspace gives
 * its creator the Owner role (every tenant permission), and the creator is
 * authenticated via the session so the RBAC gates resolve for real. Any bytes
 * written to storage/app/uploads are cleaned up in tearDown.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $workspaceId = 0;
    private int $ownerId = 0;

    /** @var string[] absolute paths written to the uploads tree to clean up. */
    private array $tempPaths = [];

    public function setUp(): void
    {
        $owner = User::create([
            'name'           => 'Files Owner',
            'email'          => 'owner-' . uniqid() . '@files.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->ownerId = (int) $owner->getKey();

        $workspace = (new WorkspaceService())->create($owner, 'Files Test Co');
        $this->workspaceId = (int) $workspace->getKey();
        tenant()->setById($this->workspaceId);

        // Authenticate the owner so the controller's can() gates resolve.
        session()->put((string) config('auth.session_key', 'auth_user_id'), $this->ownerId);
    }

    /**
     * Reset auth resolution + the AccessControl per-request cache on the EXISTING
     * singletons (preserving registered policy gates), and remove any temp files we
     * wrote to disk so the uploads tree is left clean — test isolation, no leak.
     */
    public function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->tempPaths = [];

        // Prune any now-empty per-workspace upload dirs this test created. rmdir()
        // only succeeds on an empty dir, so real data and the .gitkeep are untouched.
        $root = storage_path('app/uploads');
        foreach ((array) @scandir($root) as $entry) {
            if (! is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }
            $dir = $root . '/' . $entry;
            if (is_dir($dir)) {
                @rmdir($dir);
            }
        }

        session()->forget((string) config('auth.session_key', 'auth_user_id'));

        $auth = app('auth');
        $r = new \ReflectionObject($auth);
        foreach (['resolved' => false, 'user' => null] as $prop => $value) {
            if ($r->hasProperty($prop)) {
                $p = $r->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue($auth, $value);
            }
        }

        $access = app('access');
        $ra = new \ReflectionObject($access);
        if ($ra->hasProperty('cache')) {
            $p = $ra->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue($access, []);
        }
    }

    private function request(array $query = [], array $body = []): Request
    {
        $method = $body === [] ? 'GET' : 'POST';
        $req = new Request($query, $body, ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/'], [], []);
        app()->instance('request', $req); // the layout calls request()->path()

        return $req;
    }

    /**
     * Build a synthetic $_FILES-shaped upload backed by a real temp file. The path
     * is tracked for cleanup (FileStorage falls back to copy for non-HTTP uploads,
     * so the temp file survives the store and must be removed).
     *
     * @return array{name:string,type:string,tmp_name:string,error:int,size:int}
     */
    private function fakeUpload(string $name = 'resume.pdf', string $contents = '%PDF-1.4 hello world'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'fileupload_');
        file_put_contents($tmp, $contents);
        $this->tempPaths[] = $tmp;

        return [
            'name'     => $name,
            'type'     => 'application/pdf',
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($contents),
        ];
    }

    public function test_index_renders_empty_state_with_no_files(): void
    {
        $res = (new FileController())->index($this->request());
        $this->assertSame(200, $res->getStatus());

        $content = $res->getContent();
        $this->assertTrue(str_contains($content, 'Files'));
        $this->assertTrue(str_contains($content, 'No files yet'));
    }

    public function test_store_persists_tenant_scoped_row_and_appears_in_list(): void
    {
        $service = new FileService();
        $result = $service->store($this->fakeUpload('contract.pdf'), $this->ownerId);

        $this->assertInstanceOf(File::class, $result);
        $fileId = (int) $result->getKey();
        $this->assertTrue($fileId > 0);
        $this->tempPaths[] = (string) (new \App\Services\Files\FileStorage())->path((string) $result->getAttribute('path'));

        // Row exists, is scoped to THIS workspace, on the local provider, with a checksum.
        $row = app('db')->table('files')->where('id', '=', $fileId)->first();
        $this->assertNotNull($row);
        $this->assertSame($this->workspaceId, (int) $row['workspace_id']);
        $this->assertSame($this->ownerId, (int) $row['user_id']);
        $this->assertSame('local', (string) $row['disk']);
        $this->assertSame('contract.pdf', (string) $row['original_name']);
        $this->assertNotNull($row['checksum']);

        $localProviderId = (int) app('db')->table('storage_providers')->where('driver', '=', 'local')->value('id');
        $this->assertSame($localProviderId, (int) $row['storage_provider_id']);

        // It appears in the listing with the uploader's name.
        $list = $service->list();
        $this->assertCount(1, $list);
        $this->assertSame($fileId, (int) $list[0]['id']);
        $this->assertSame('Files Owner', (string) $list[0]['uploader']);
    }

    public function test_delete_soft_deletes_row_and_removes_disk_file(): void
    {
        $service = new FileService();
        $file = $service->store($this->fakeUpload('to-delete.png', 'PNGDATA'), $this->ownerId);
        $this->assertInstanceOf(File::class, $file);

        $fileId = (int) $file->getKey();
        $absolute = (string) (new \App\Services\Files\FileStorage())->path((string) $file->getAttribute('path'));
        $this->assertTrue(is_file($absolute));

        $deleted = $service->delete($fileId, $this->ownerId);
        $this->assertTrue($deleted);

        // Row is soft-deleted (deleted_at set) and no longer in the active listing.
        $row = app('db')->table('files')->where('id', '=', $fileId)->first();
        $this->assertNotNull($row['deleted_at']);
        $this->assertCount(0, $service->list());

        // The bytes are gone from disk.
        $this->assertFalse(is_file($absolute));
    }

    public function test_list_never_returns_another_workspaces_file(): void
    {
        // A file in THIS workspace.
        $service = new FileService();
        $mine = $service->store($this->fakeUpload('mine.pdf'), $this->ownerId);
        $this->assertInstanceOf(File::class, $mine);
        $this->tempPaths[] = (string) (new \App\Services\Files\FileStorage())->path((string) $mine->getAttribute('path'));

        // A SECOND workspace with its own owner + its own file.
        $otherOwner = User::create([
            'name'           => 'Other Owner',
            'email'          => 'other-' . uniqid() . '@files.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $otherWorkspace = (new WorkspaceService())->create($otherOwner, 'Other Co');
        $otherWorkspaceId = (int) $otherWorkspace->getKey();

        tenant()->setById($otherWorkspaceId);
        $otherService = new FileService();
        $theirs = $otherService->store($this->fakeUpload('theirs.pdf'), (int) $otherOwner->getKey());
        $this->assertInstanceOf(File::class, $theirs);
        $this->tempPaths[] = (string) (new \App\Services\Files\FileStorage())->path((string) $theirs->getAttribute('path'));
        $theirId = (int) $theirs->getKey();

        // Back in THIS workspace: the other tenant's file must be absent, and a
        // tenant-scoped find() of its id must miss.
        tenant()->setById($this->workspaceId);
        $myService = new FileService();
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $myService->list());
        $this->assertCount(1, $ids);
        $this->assertSame((int) $mine->getKey(), $ids[0]);
        $this->assertFalse(in_array($theirId, $ids, true));
        $this->assertNull($myService->find($theirId));
    }
};
