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

            return response()->json(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            Log::error("Error deleting hint {$hint->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la suppression de l\'indice a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}