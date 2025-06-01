<?php

namespace App\Services;

use App\Interfaces\HintServiceInterface;
use App\Models\Hint;
use App\Models\Step;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class HintService implements HintServiceInterface
{
    /**
     * Create a new hint for a step.
     *
     * @param  \App\Models\Step  $step
     * @param  array  $data
     * @return \App\Models\Hint
     */
    public function createHint(Step $step, array $data)
    {
        $nextOrderNumber = ($step->hints()->max('order_number') ?? 0) + 1;

        return $step->hints()->create([
            'type' => $data['type'],
            'content' => $data['content'],
            'order_number' => $nextOrderNumber,
        ]);
    }

    /**
     * Update a hint.
     *
     * @param  \App\Models\Hint  $hint
     * @param  array  $data
     * @return \App\Models\Hint
     */
    public function updateHint(Hint $hint, array $data)
    {
        $hint->update($data);
        return $hint->fresh();
    }

    /**
     * Delete a hint and reorder remaining hints.
     *
     * @param  \App\Models\Hint  $hint
     * @return int The step ID
     */
    public function deleteHint(Hint $hint)
    {
        $orderDeleted = $hint->order_number;
        $stepId = $hint->step_id;

        return DB::transaction(function() use ($hint, $orderDeleted, $stepId) {
            $hint->delete();

            Hint::where('step_id', $stepId)
                ->where('order_number', '>', $orderDeleted)
                ->decrement('order_number');

            return $stepId;
        });
    }

    /**
     * Upload an image for a hint.
     *
     * @param  \App\Models\Hint  $hint
     * @param  \Illuminate\Http\UploadedFile  $image
     * @return string The image URL
     */
    // public function uploadHintImage(Hint $hint, UploadedFile $image)
    // {
    //     // Générer un nom de fichier unique
    //     $fileName = 'hint_' . $hint->id . '_' . time() . '.' . $image->guessExtension();
        
    //     // Créer le chemin de stockage
    //     $path = 'hints/images/' . $fileName;
        
    //     // Redimensionner l'image avec Intervention Image
    //     $img = Image::make($image->getRealPath());
        
    //     // Redimensionner l'image tout en conservant les proportions
    //     $img->resize(400, 400, function ($constraint) {
    //         $constraint->aspectRatio();
    //         $constraint->upsize();
    //     });
        
    //     // Créer un canvas de 400x400 avec fond blanc
    //     $canvas = Image::canvas(400, 400, '#ffffff');
        
    //     // Placer l'image redimensionnée au centre du canvas
    //     $canvas->insert($img, 'center');
        
    //     // Convertir l'image en flux de données
    //     $imageStream = $canvas->stream();
        
    //     // Stocker l'image
    //     Storage::disk('public')->put($path, $imageStream);
        
    //     // Générer l'URL publique
    //     $imageUrl = Storage::disk('public')->url($path);
        
    //     // Supprimer l'ancienne image si elle existe et est différente
    //     if ($hint->type === 'image' && $hint->content && $hint->content !== $imageUrl) {
    //         $oldPath = str_replace(Storage::disk('public')->url(''), '', $hint->content);
    //         if (Storage::disk('public')->exists($oldPath)) {
    //             Storage::disk('public')->delete($oldPath);
    //         }
    //     }
        
    //     // Mettre à jour l'indice avec l'URL de l'image
    //     $hint->update([
    //         'type' => 'image',
    //         'content' => $imageUrl
    //     ]);
        
    //     return $imageUrl;
    // }
}