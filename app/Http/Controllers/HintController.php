<?php

namespace App\Http\Controllers;

use App\Interfaces\HintServiceInterface;
use App\Models\Hint;
use App\Models\Step;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class HintController extends Controller
{
    protected $hintService;

    public function __construct(HintServiceInterface $hintService)
    {
        $this->hintService = $hintService;
    }

    /**
     * Create a new hint for a step.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Step  $step
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Step $step): JsonResponse
    {
        if ($request->user()->id !== $step->riddle->creator_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        $validatedData = $request->validate([
            'type' => ['required', Rule::in(['text', 'image', 'audio'])],
            'content' => 'required|string',
        ]);

        try {
            $hint = $this->hintService->createHint($step, $validatedData);
            
            return response()->json([
                'data' => $hint,
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error("Error creating hint: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la création de l\'indice.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a hint.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Hint  $hint
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Hint $hint): JsonResponse
    {
        if ($request->user()->id !== $hint->step->riddle->creator_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        $validatedData = $request->validate([
            'type' => ['sometimes', 'required', Rule::in(['text', 'image', 'audio'])],
            'content' => 'sometimes|required|string',
        ]);

        try {
            $updatedHint = $this->hintService->updateHint($hint, $validatedData);
            
            return response()->json([
                'data' => $updatedHint,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error updating hint {$hint->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la mise à jour de l\'indice.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a hint.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Hint  $hint
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Hint $hint): JsonResponse
    {
        if ($request->user()->id !== $hint->step->riddle->creator_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $stepId = $this->hintService->deleteHint($hint);
            
            return response()->json([
                'data' => $stepId,
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            Log::error("Error deleting hint {$hint->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la suppression de l\'indice.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Upload an image for a hint.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Hint  $hint
     * @return \Illuminate\Http\JsonResponse
     */
    // public function uploadImage(Request $request, Hint $hint): JsonResponse
    // {
    //     if ($request->user()->id !== $hint->step->riddle->creator_id) {
    //         return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
    //     }

    //     $validatedData = $request->validate([
    //         'image' => 'required|image|max:5120', // 5MB max
    //     ]);

    //     try {
    //         $imageUrl = $this->hintService->uploadHintImage($hint, $request->file('image'));
            
    //         return response()->json([
    //             'success' => true,
    //             'image_url' => $imageUrl,
    //             'message' => 'Image téléchargée avec succès'
    //         ], Response::HTTP_OK);
    //     } catch (\Exception $e) {
    //         Log::error("Error uploading image for hint {$hint->id}: " . $e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Erreur lors du téléchargement de l\'image: ' . $e->getMessage()
    //         ], Response::HTTP_INTERNAL_SERVER_ERROR);
    //     }
    // }
}