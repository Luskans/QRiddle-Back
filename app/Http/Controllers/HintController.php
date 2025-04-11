<?php

namespace App\Http\Controllers;

use App\Models\Hint;
use App\Models\Step; // Importer le modèle Step
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Pour vérifier les autorisations
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule; // Pour la validation du type

class HintController extends Controller
{
    /**
     * Affiche la liste des indices pour une étape spécifique.
     *
     * @param  \App\Models\Step  $step // Laravel injecte l'étape basée sur l'ID dans l'URL
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Step $step): JsonResponse
    {
        // Optionnel: Vérifier si l'utilisateur a le droit de voir ces indices
        // (par exemple, s'il est le créateur de l'énigme parente)
        // $this->authorize('view', $step->riddle); // Exemple avec une Policy

        // Récupérer les indices triés par order_number
        $hints = $step->hints()->orderBy('order_number', 'asc')->get();

        return response()->json(['hints' => $hints], Response::HTTP_OK);
    }

    /**
     * Crée un nouvel indice pour une étape spécifique.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Step  $step // Laravel injecte l'étape
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Step $step): JsonResponse
    {
        // 1. Autorisation : Vérifier si l'utilisateur est le créateur de l'énigme parente
        if (Auth::id() !== $step->riddle->creator_id) {
            return response()->json(['message' => 'Unauthorized. You did not create this riddle.'], Response::HTTP_FORBIDDEN);
        }

        // 2. Validation des données reçues
        $validatedData = $request->validate([
            'type' => ['required', Rule::in(['text', 'image', 'audio'])], // Valider les types autorisés
            'content' => 'required|string', // Contenu texte ou URL pour image/audio
            'order_number' => 'nullable|integer|min:1', // Optionnel, on peut le calculer
        ]);

        // 3. Calculer le numéro d'ordre si non fourni
        $nextOrderNumber = ($step->hints()->max('order_number') ?? 0) + 1;

        // 4. Créer l'indice
        try {
            $hint = $step->hints()->create([
                'type' => $validatedData['type'],
                'content' => $validatedData['content'],
                'order_number' => $validatedData['order_number'] ?? $nextOrderNumber,
            ]);

            // 5. Retourner l'indice créé
            return response()->json($hint, Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error("Error creating hint for step {$step->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to create hint.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Affiche les détails d'un indice spécifique.
     * (Grâce à ->shallow(), on reçoit directement $hint)
     *
     * @param  \App\Models\Hint  $hint
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Hint $hint): JsonResponse
    {
        // Optionnel: Vérifier l'autorisation
        // $this->authorize('view', $hint->step->riddle);

        // Charger la relation 'step' si nécessaire pour le contexte
        // $hint->load('step:id,riddle_id');

        return response()->json($hint, Response::HTTP_OK);
    }

    /**
     * Met à jour un indice spécifique.
     * (Grâce à ->shallow(), on reçoit directement $hint)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Hint  $hint
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Hint $hint): JsonResponse
    {
        // 1. Autorisation : Vérifier si l'utilisateur est le créateur
        if (Auth::id() !== $hint->step->riddle->creator_id) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        // 2. Validation des données (partielles)
        $validatedData = $request->validate([
            // Utiliser 'sometimes' pour la mise à jour partielle
            'type' => ['sometimes', 'required', Rule::in(['text', 'image', 'audio'])],
            'content' => 'sometimes|required|string',
            'order_number' => 'sometimes|required|integer|min:1',
        ]);

        // 3. Mettre à jour l'indice
        try {
            $hint->update($validatedData);

            // Recharger l'indice avec les données à jour (optionnel mais propre)
            $hint->refresh();

            // 4. Retourner l'indice mis à jour
            return response()->json($hint, Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error updating hint {$hint->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to update hint.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime un indice spécifique.
     * (Grâce à ->shallow(), on reçoit directement $hint)
     *
     * @param  \App\Models\Hint  $hint
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Hint $hint): JsonResponse
    {
        // 1. Autorisation : Vérifier si l'utilisateur est le créateur
        if (Auth::id() !== $hint->step->riddle->creator_id) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        // 2. Supprimer l'indice
        try {
            $hint->delete();

            // 3. Retourner une réponse vide avec succès
            return response()->json(null, Response::HTTP_NO_CONTENT); // 204 No Content

        } catch (\Exception $e) {
            Log::error("Error deleting hint {$hint->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to delete hint.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}