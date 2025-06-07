<?php

namespace App\Services\Interfaces;

use App\Models\Hint;
use App\Models\Step;
use Illuminate\Http\UploadedFile;

interface HintServiceInterface
{
    /**
     * Create a new hint for a step.
     *
     * @param  \App\Models\Step  $step
     * @param  array  $data
     * @param  int  $userId
     * @return \App\Models\Hint
     */
    public function createHint(Step $step, array $data, int $userId);

    /**
     * Update a hint.
     *
     * @param  \App\Models\Hint  $hint
     * @param  array  $data
     * @param  int  $userId
     * @return \App\Models\Hint
     */
    public function updateHint(Hint $hint, array $data, int $userId);

    /**
     * Delete a hint and reorder remaining hints.
     *
     * @param  \App\Models\Hint  $hint
     * @param  int  $userId
     * @return int The step ID
     */
    public function deleteHint(Hint $hint, int $userId);

    /**
     * Upload an image for a hint.
     *
     * @param  \App\Models\Hint  $hint
     * @param  \Illuminate\Http\UploadedFile  $image
     * @return string The image URL
     */
    // public function uploadHintImage(Hint $hint, UploadedFile $image);
}