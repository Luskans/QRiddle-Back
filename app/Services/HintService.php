<?php

namespace App\Services;

use App\Models\Hint;
use App\Models\Step;
use App\Repositories\Interfaces\HintRepositoryInterface;
use App\Services\Interfaces\HintServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Symfony\Component\HttpFoundation\Response;


class HintService implements HintServiceInterface
{
    protected $hintRepository;

    public function __construct(HintRepositoryInterface $hintRepository)
    {
        $this->hintRepository = $hintRepository;
    }
    
    public function createHint(Step $step, array $data, int $userId)
    {
        if ($userId !== $step->riddle->creator_id) {
            throw new \Exception('Utilisateur non autorisé.', Response::HTTP_FORBIDDEN);
        }

        $data['order_number'] = $this->hintRepository->getNextOrderNumber($step);
        return $this->hintRepository->createForStep($step, $data);
    }

    public function updateHint(Hint $hint, array $data, int $userId)
    {
        if ($userId !== $hint->step->riddle->creator_id) {
            throw new \Exception('Utilisateur non autorisé.', Response::HTTP_FORBIDDEN);
        }

        return $this->hintRepository->update($hint, $data);
    }

    public function deleteHint(Hint $hint, int $userId)
    {
        if ($userId !== $hint->step->riddle->creator_id) {
            throw new \Exception('Utilisateur non autorisé.', Response::HTTP_FORBIDDEN);
        }

        return $this->hintRepository->deleteAndReorder($hint);
    }

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