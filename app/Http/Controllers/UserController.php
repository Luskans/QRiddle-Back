<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Riddle; // Importer le modèle Riddle
use App\Models\GameSession; // Importer le modèle GameSession
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class UserController extends Controller
{
    /**
     * Récupère la liste paginée des énigmes créées par l'utilisateur authentifié.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function myCreatedRiddles(Request $request): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        // Validation des paramètres de pagination (optionnel mais recommandé)
        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
            'status' => 'sometimes|string|in:draft,active,disabled', // Optionnel: filtrer par statut
        ]);

        $limit = $validated['limit'] ?? 15; // Limite par défaut
        $offset = $validated['offset'] ?? 0; // Offset par défaut

        // Requête pour les énigmes créées par l'utilisateur
        $query = Riddle::where('creator_id', $userId)
                       ->orderBy('created_at', 'desc'); // Trier par date de création (ou autre)

        // Appliquer le filtre de statut si fourni
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        // Cloner pour compter le total avant pagination
        $totalQuery = clone $query;
        $totalCount = $totalQuery->count();

        // Appliquer la pagination
        $riddles = $query->skip($offset)
                         ->take($limit)
                         // Sélectionner les colonnes nécessaires pour la liste
                         ->get(['id', 'title', 'status', 'is_private', 'created_at', /* 'latitude', 'longitude' ? */]);
                         // Ajouter d'autres champs si besoin pour l'affichage liste (ex: image miniature ?)

        return response()->json([
            'riddles' => $riddles,
            'meta' => [
                'offset' => $offset,
                'limit' => $limit,
                'total' => $totalCount,
                'hasMore' => ($offset + count($riddles)) < $totalCount,
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Récupère la liste paginée des sessions de jeu de l'utilisateur authentifié.
     * Permet de filtrer par statut (ex: 'completed', 'active', 'abandoned').
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function myGameSessions(Request $request): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        // Validation des paramètres
        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
            'status' => 'sometimes|string|in:active,completed,abandoned', // Statuts possibles de GameSession
            'with' => 'sometimes|string', // Pour charger des relations (ex: 'riddle')
        ]);

        $limit = $validated['limit'] ?? 15;
        $offset = $validated['offset'] ?? 0;

        // Requête pour les sessions de jeu de l'utilisateur
        $query = GameSession::where('player_id', $userId)
                            ->orderBy('updated_at', 'desc'); // Trier par la plus récente mise à jour

        // Appliquer le filtre de statut si fourni
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        // Charger les relations demandées (ex: 'riddle')
        if (isset($validated['with']) && $validated['with'] === 'riddle') {
            // Charger seulement les colonnes nécessaires du riddle
            $query->with('riddle:id,title'); // Adaptez les colonnes selon vos besoins
        }

        // Cloner pour compter le total
        $totalQuery = clone $query;
        $totalCount = $totalQuery->count();

        // Appliquer la pagination
        $gameSessions = $query->skip($offset)
                              ->take($limit)
                              // Sélectionner les colonnes de GameSession nécessaires
                              ->get(['id', 'riddle_id', 'status', 'score', 'created_at', 'updated_at']);

        return response()->json([
            'sessions' => $gameSessions, // Renommé en 'sessions' pour clarté
            'meta' => [
                'offset' => $offset,
                'limit' => $limit,
                'total' => $totalCount,
                'hasMore' => ($offset + count($gameSessions)) < $totalCount,
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Mettre à jour le profil de l'utilisateur authentifié (Exemple).
     *
     * @param Request $request
     * @return JsonResponse
     */
    // public function updateProfile(Request $request): JsonResponse
    // {
    //     $user = Auth::user();
    //     if (!$user) {
    //         return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
    //     }

    //     $validated = $request->validate([
    //         'name' => 'sometimes|required|string|max:255',
    //         'email' => [
    //             'sometimes',
    //             'required',
    //             'string',
    //             'email',
    //             'max:255',
    //             Rule::unique('users')->ignore($user->id), // Ignorer l'email actuel de l'utilisateur
    //         ],
    //         // Ajouter la validation pour l'image si elle est modifiable
    //         // 'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
    //     ]);

    //     // Gérer l'upload d'image si fourni
    //     // if ($request->hasFile('image')) {
    //     //     // Supprimer l'ancienne image si elle existe et n'est pas l'image par défaut
    //     //     if ($user->image && $user->image !== '/default/user.webp') {
    //     //         Storage::disk('public')->delete(str_replace('/storage', '', $user->image));
    //     //     }
    //     //     $path = $request->file('image')->store('profile_images', 'public');
    //     //     $validated['image'] = Storage::url($path); // Stocker l'URL publique
    //     // }

    //     $user->update($validated);

    //     return response()->json($user, Response::HTTP_OK);
    // }
}