<?php

namespace Froala\NovaFroalaField\Handlers;

use App\CustomPathGenerator;
use Froala\NovaFroalaField\Froala;
use Froala\NovaFroalaField\Models\PendingAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Carbon\Carbon;

class StorePendingAttachment
{
    /**
     * The field instance.
     *
     * @var \Froala\NovaFroalaField\Froala
     */
    public $field;

    /**
     * Create a new invokable instance.
     *
     * @param  \Froala\NovaFroalaField\Froala  $field
     * @return void
     */


    public function __construct(Froala $field)
    {
        $this->field = $field;
    }

    /**
     * Attach a pending attachment to the field.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    public function __invoke(Request $request)
    {
        $this->abortIfFileNameExists($request);



        $originalName = $request->attachment->getClientOriginalName();

        $attachment = PendingAttachment::create([
            'draft_id' => $request->draftId,
            'original_name' => $originalName,
            'attachment' => $request->attachment->store(CustomPathGenerator::getPathFromData($this->field->model_draft_id,$this->field->model_name, Carbon::now()), $this->field->disk),
            'disk' => $this->field->disk,
        ])->attachment;

        $widthdata = getimagesize(Storage::disk($this->field->disk)->path($attachment));

        return ['link' => Storage::disk($this->field->disk)->url($attachment).'?w=740', 'original-width' => $widthdata[0],'original-height' => $widthdata[1],'original-path' => $attachment, 'original-disk' => $this->field->disk,  'request-draft-id' => $request->draftId, 'original-name' => $originalName,'loaded' => 'froala','draft' => ($this->field->model_draft_id ? false:true)];

    }

    protected function abortIfFileNameExists(Request $request): void
    {
        if (config('nova.froala-field.preserve_file_names')
            && Storage::disk($this->field->disk)
                ->exists($request->attachment->getClientOriginalName())
        ) {
            abort(response()->json([
                'status' => Response::HTTP_CONFLICT,
            ], Response::HTTP_CONFLICT));
        }
    }

    protected function imageOptimize(string $attachment): void
    {
        if (config('nova.froala-field.optimize_images')) {
            $optimizerChain = OptimizerChainFactory::create();

            if (count($optimizers = config('nova.froala-field.image_optimizers'))) {
                $optimizers = array_map(
                    function (array $optimizerOptions, string $optimizerClassName) {
                        return (new $optimizerClassName)->setOptions($optimizerOptions);
                    },
                    $optimizers,
                    array_keys($optimizers)
                );

                $optimizerChain->setOptimizers($optimizers);
            }

            $optimizerChain->optimize(Storage::disk($this->field->disk)->path($attachment));
        }
    }
}
