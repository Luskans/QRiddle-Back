<?php

namespace App\Interfaces;

use App\Models\Hint;
use App\Models\Step;
use Illuminate\Http\UploadedFile;

interface HintServiceInterface
{
    public function createHint(Step $step, array $data);
    public function updateHint(Hint $hint, array $data);
    public function deleteHint(Hint $hint);
    // public function uploadHintImage(Hint $hint, UploadedFile $image);
}