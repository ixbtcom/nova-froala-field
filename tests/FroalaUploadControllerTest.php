<?php

namespace Froala\NovaFroalaField\Tests;

use Froala\NovaFroalaField\Models\Attachment;
use Froala\NovaFroalaField\Models\PendingAttachment;
use function Froala\NovaFroalaField\nova_version_at_least;
use Froala\NovaFroalaField\Tests\Fixtures\Article;
use Illuminate\Support\Facades\Storage;

class FroalaUploadControllerTest extends TestCase
{
    use UploadsHelper;

    /** @test */
    public function store_pending_attachment()
    {
        $response = $this->uploadPendingFile();

        $response->assertJson(['link' => Storage::disk(static::DISK)->url($this->file->hashName())]);

        $this->assertDatabaseHas((new PendingAttachment)->getTable(), [
            'draft_id' => $this->draftId,
            'disk' => static::DISK,
            'attachment' => $this->file->hashName(),
        ]);

        // Assert the file was stored...
        Storage::disk(static::DISK)->assertExists($this->file->hashName());

        // Assert a file does not exist...
        Storage::disk(static::DISK)->assertMissing('missing.jpg');
    }

    /** @test */
    public function store_attachment()
    {
        $this->uploadPendingFile();

        $response = $this->storeArticle();

        if (nova_version_at_least('1.3.1')) {
            $response->assertJson([
                'resource' => [
                    'title' => 'Some title',
                    'content' => 'Some content',
                ],
            ]);
        } else {
            $response->assertJson([
                'title' => 'Some title',
                'content' => 'Some content',
            ]);
        }

        $this->assertDatabaseHas((new Attachment)->getTable(), [
            'disk' => static::DISK,
            'attachment' => $this->file->hashName(),
            'url' => Storage::disk(static::DISK)->url($this->file->hashName()),
            'attachable_id' => $response->json('id'),
            'attachable_type' => Article::class,
        ]);
    }

    /** @test */
    public function detach_attachment()
    {
        $src = $this->uploadPendingFile()->json('link');

        $this->storeArticle();

        Storage::disk(static::DISK)->assertExists($this->file->hashName());

        $this->json('DELETE', 'nova-vendor/froala-field/articles/attachments/content', [
            'src' => $src,
        ]);

        Storage::disk(static::DISK)->assertMissing($this->file->hashName());
    }

    /** @test */
    public function discard_pending_attachments()
    {
        $fileNames = [];

        for ($i = 0; $i <= 3; $i++) {
            $this->uploadPendingFile();

            $fileNames[] = $this->file->hashName();

            $this->regenerateUpload();
        }

        foreach ($fileNames as $fileName) {
            Storage::disk(static::DISK)->assertExists($fileName);
        }

        $this->json('DELETE', 'nova-vendor/froala-field/articles/attachments/content/'.$this->draftId);

        foreach ($fileNames as $fileName) {
            Storage::disk(static::DISK)->assertMissing($fileName);
        }
    }

    /** @test */
    public function delete_all_related_attachments()
    {
        $fileNames = [];

        for ($i = 0; $i <= 5; $i++) {
            $this->uploadPendingFile();

            $fileNames[] = $this->file->hashName();

            $this->regenerateUpload();
        }

        foreach ($fileNames as $fileName) {
            Storage::disk(static::DISK)->assertExists($fileName);
        }

        $articleResponse = $this->storeArticle();

        $this->json('DELETE', 'nova-api/articles', [
            'resources' => [(int) $articleResponse->json('id')],
        ]);

        foreach ($fileNames as $fileName) {
            Storage::disk(static::DISK)->assertMissing($fileName);
        }
    }
}
