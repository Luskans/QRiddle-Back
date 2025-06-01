<?php

namespace App\Http\NewControllers;

use App\Models\Hint;
use App\Models\Step;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class HintController extends Controller
{
    /**
     * Get the list of hints for a step.
     *
     * @param  \App\Models\Step  $step
     * @return \Illuminate\Http\JsonResponse
     */
    // TODO : plus nécessaire, hints ajoutés dans step detail
    // public function index(Step $step): JsonResponse
    // {
    //     if (Auth::id() !== $step->riddle->creator_id) {
    //         return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
    //     }

    //     // Récupérer les indices triés par order_number
    //     $hints = $step->hints('id', 'order_number', 'type', 'content')
    //         ->orderBy('order_number', 'asc')
    //         ->get();

    //     return response()->json([
    //         'items' => $hints,
    //     ], Response::HTTP_OK);
    // }

    /**
     * Create a new hint for a step.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Step  $step
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Step $step): JsonResponse
    {
        if (Auth::id() !== $step->riddle->creator_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        $validatedData = $request->validate([
            'type' => ['required', Rule::in(['text', 'image', 'audio'])],
            'content' => 'required|string',
        ]);

        $nextOrderNumber = ($step->hints()->max('order_number') ?? 0) + 1;

        try {
            $hint = $step->hints()->create([
                'type' => $validatedData['type'],
                'content' => $validatedData['content'],
                'order_number' => $nextOrderNumber,
            ]);

            return response()->json([
                'data' => $hint,
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error("Error creating hint: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la création de l\'indice a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get the detail of a hint.
     *
     * @param  \App\Models\Hint
     * @return \Illuminate\Http\JsonResponse
     */
    // public function show(Hint $hint): JsonResponse
    // {
    //     $hint->load(['step.riddle']);

    //     if (Auth::id() !== $hint->step->riddle->creator_id) {
    //         return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
    //     }

    //     return response()->json([
    //         'data' => $hint->only(['id', 'order_number', 'type', 'content']),
    //     ], Response::HTTP_OK);
    // }

    /**
     * Update a hint.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Hint  $hint
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Hint $hint): JsonResponse
    {
        if (Auth::id() !== $hint->step->riddle->creator_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        $validatedData = $request->validate([
            'type' => ['sometimes', 'required', Rule::in(['text', 'image', 'audio'])],
            'content' => 'sometimes|required|string',
        ]);

        try {
            $hint->update($validatedData);
            $hint->refresh();

            return response()->json([
                'data' => $hint,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error updating hint {$hint->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la mise à jour de l\'indice a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a hint.
     *
     * @param  \App\Models\Hint  $hint
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Hint $hint): JsonResponse
    {
        if (Auth::id() !== $hint->step->riddle->creator_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $orderDeleted = $hint->order_number;
            $stepId = $hint->step_id;

            $hint->delete();

            Hint::where('step_id', $stepId)
                ->where('order_number', '>', $orderDeleted)
                ->decrement('order_number');

            // return response()->json(null, Response::HTTP_NO_CONTENT);
            return response()->json([
                'data' => $stepId,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error deleting hint {$hint->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la suppression de l\'indice a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Upload an image for a hint.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Hint
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadImage(Request $request, Hint $hint)
    {
        if (Auth::id() !== $hint->step->riddle->creator_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        $validatedData = $request->validate([
            'image' => 'required|image|max:5120', // 5MB max
            // 'hint_id' => 'required|exists:hints,id',
            // 'width' => 'nullable|integer|min:1',
            // 'height' => 'nullable|integer|min:1',
            // 'mimeType' => 'required|string'
        ]);

        try {
            // Récupérer l'image
            $image = $request->file('image');
            
            // Définir les dimensions
            // $width = $request->input('width', 400);
            // $height = $request->input('height', 400);

            // $type = $validatedData['mimeType'];

            // Générer un nom de fichier unique
            // $fileName = 'hint_' . $hint->id . '_' . time() . '.' . $image->getClientOriginalExtension();
            $fileName = 'hint_' . $hint->id . '_' . time() . '.' . $image->guessExtension();
            
            // Créer le chemin de stockage
            $path = 'hints/images/' . $fileName;
            
            // Redimensionner l'image avec Intervention Image
            $img = Image::make($image->getRealPath());
            
            // Redimensionner l'image tout en conservant les proportions
            $img->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            
            // Créer un canvas de 400x400 avec fond blanc
            $canvas = Image::canvas($width, $height, '#ffffff');
            
            // Placer l'image redimensionnée au centre du canvas
            $canvas->insert($img, 'center');
            
            // Convertir l'image en flux de données
            $imageStream = $canvas->stream();
            
            // Stocker l'image
            Storage::disk('public')->put($path, $imageStream);
            
            // Générer l'URL publique
            $imageUrl = Storage::disk('public')->url($path);
            
            // Supprimer l'ancienne image si elle existe et est différente
            if ($hint->type === 'image' && $hint->content && $hint->content !== $imageUrl) {
                $oldPath = str_replace(Storage::disk('public')->url(''), '', $hint->content);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            
            // Mettre à jour l'indice avec l'URL de l'image
            $hint->update([
                'type' => 'image',
                'content' => $imageUrl
            ]);
            
            return response()->json([
                'success' => true,
                'image_url' => $imageUrl,
                'message' => 'Image téléchargée avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement de l\'image: ' . $e->getMessage()
            ], 500);
        }
    }
}