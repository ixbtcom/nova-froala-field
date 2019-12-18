<?php

namespace Froala\NovaFroalaField\Models;

use Froala\NovaFroalaField\Froala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PendingAttachment extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nova_pending_froala_attachments';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Persist the given draft's pending attachments.
     *
     * @param  string  $draftId
     * @param  \Froala\NovaFroalaField\Froala  $field
     * @param  mixed  $model
     * @return void
     */
    public static function persistDraft($draftId, Froala $field, $model)
    {
        static::where('draft_id', $draftId)->get()->each->persist($field, $model);
    }

    /**
     * Persist the pending attachment.
     *
     * @param  \Froala\NovaFroalaField\Froala $field
     * @param  mixed $model
     * @return void
     * @throws \Exception
     */
    public function persist(Froala $field, $model)
    {
        $datadisk = $field->disk;
        $mediacollection = 'pubimages';
        if(substr($this->attachment->getMimeType(),0,5) != 'image') {
            $datadisk = $field->datadisk;
            $urladd = '';
            $mediacollection = 'pubdata';
        }
        $model->addMediaFromDisk($this->attachment,$datadisk)->withCustomProperties(['publication_draft_id' => $model->publication_draft_id, 'original_name' => $this->original_name,'media_draft_id' => $this->draft_id])->toMediaCollection($mediacollection);
        /*Attachment::create([
            'attachable_type' => get_class($model),
            'attachable_id' => $model->getKey(),
            'attachment' => $this->attachment,
            'disk' => $field->disk,
            'url' => Storage::disk($field->disk)->url($this->attachment),
            'attachable_draft_id' => $model->publication_draft_id
        ]);*/

        $this->delete();
    }

    /**
     * Purge the attachment.
     *
     * @return void
     * @throws \Exception
     */
    public function purge()
    {
        Storage::disk($this->disk)->delete($this->attachment);

        $this->delete();
    }
}
