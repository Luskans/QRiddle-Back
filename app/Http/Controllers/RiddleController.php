<?php

namespace App\Http\Controllers;

use App\Models\Riddle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash; // Pour vérifier le mot de passe si privé
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password; // Si tu veux valider le mot de passe de l'énigme
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class RiddleController extends Controller
{
    /**
     * Affiche une liste paginée des énigmes publiques.
     * Peut inclure des filtres (ex: localisation, recherche texte).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Validation des paramètres de pagination/filtre
        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
            'search' => 'sometimes|string|max:100',
            // Ajouter filtres de localisation si besoin (latitude, longitude, radius)
        ]);

        $limit = $validated['limit'] ?? 20;
        $offset = $validated['offset'] ?? 0;

        $query = Riddle::query()
            // Sélectionner seulement les colonnes nécessaires pour la liste/carte
            ->select(['id', 'creator_id', 'title', 'is_private', 'status', 'latitude', 'longitude', 'created_at'])
            // Filtrer pour n'afficher que les énigmes publiques et actives
            ->where('is_private', false)
            ->where('status', 'active');
            // Optionnel: Ajouter eager loading pour des infos agrégées si besoin
            // ->withCount('steps') // Compte le nombre d'étapes
            // ->withAvg('reviews', 'rating') // Note moyenne
            // ->withAvg('reviews', 'difficulty'); // Difficulté moyenne

        // Appliquer le filtre de recherche (simple recherche sur titre/description)
        if (!empty($validated['search'])) {
            $searchTerm = '%' . $validated['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm)
                  ->orWhere('description', 'like', $searchTerm); // Attention performance sur 'description'
            });
        }

        // Ajouter filtres de localisation ici si implémenté

        // Cloner pour compter le total avant pagination
        $totalQuery = clone $query;
        $totalCount = $totalQuery->count();

        // Appliquer la pagination et récupérer les résultats
        $riddles = $query->skip($offset)
                         ->take($limit)
                         ->latest() // Trier par date de création (ou autre critère)
                         ->get();

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
     * Crée une nouvelle énigme.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000', // Ajuste la longueur max
            'is_private' => 'required|boolean',
            'password' => [
                Rule::requiredIf($request->input('is_private') == true), // Requis si privé
                'nullable', // Permet d'être null si public
                'string',
                'min:6', // Minimum 6 caractères pour le mot de passe de l'énigme
                // Tu peux ajouter des règles Password::defaults() si tu veux forcer la complexité
            ],
            'status' => ['sometimes', Rule::in(['draft', 'active'])], // Permet draft ou active à la création
            'latitude' => ['required', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'longitude' => ['required', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
        ]);

        try {
            $riddle = Auth::user()->createdRiddles()->create([
                'title' => $validatedData['title'],
                'description' => $validatedData['description'],
                'is_private' => $validatedData['is_private'],
                // Hash le mot de passe seulement s'il est fourni (et si privé)
                'password' => ($validatedData['is_private'] && !empty($validatedData['password']))
                                ? Hash::make($validatedData['password'])
                                : null,
                'status' => $validatedData['status'] ?? 'draft', // Défaut à 'draft' si non fourni
                'latitude' => $validatedData['latitude'],
                'longitude' => $validatedData['longitude'],
            ]);

            // Retourner l'énigme créée (avec son ID)
            // Le mot de passe hashé ne sera pas retourné grâce à $hidden dans le modèle
            return response()->json($riddle, Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Error creating riddle: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create riddle.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Affiche les détails d'une énigme spécifique.
     * Gère l'accès aux énigmes privées.
     *
     * @param  \App\Models\Riddle  $riddle // Liaison de modèle implicite
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Riddle $riddle): JsonResponse
    {
        // Vérifier si l'énigme est privée et si l'utilisateur n'est pas le créateur
        if ($riddle->is_private && Auth::id() !== $riddle->creator_id) {
            // Ici, tu pourrais avoir une logique pour vérifier si l'utilisateur
            // a déjà joué ou a le droit de voir (ex: via un mot de passe fourni ?)
            // Pour l'instant, on interdit l'accès direct aux détails si privé et non créateur.
            // Le frontend devra gérer la demande de mot de passe avant de démarrer la partie.
            return response()->json(['message' => 'This riddle is private.'], Response::HTTP_FORBIDDEN);
        }

        // Charger des relations si nécessaire pour le détail
        // $riddle->load(['creator:id,name', 'steps:id,order_number']); // Exemple

        // Retourner les détails
        // Le mot de passe n'est pas inclus grâce à $hidden dans le modèle Riddle
        return response()->json($riddle, Response::HTTP_OK);
    }

    /**
     * Met à jour une énigme existante.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Riddle  $riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Riddle $riddle): JsonResponse
    {
        // 1. Autorisation : Seul le créateur peut modifier
        if (Auth::id() !== $riddle->creator_id) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        // 2. Validation (similaire à store, mais champs non requis)
        $validatedData = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:1000',
            'is_private' => 'sometimes|required|boolean',
            'password' => [
                // Requis si on passe à privé et qu'il n'y en a pas déjà ou si on veut le changer
                Rule::requiredIf(function () use ($request, $riddle) {
                    return $request->input('is_private') == true && empty($riddle->password) && empty($request->input('password'));
                }),
                'nullable',
                'string',
                'min:6',
            ],
            'status' => ['sometimes', 'required', Rule::in(['draft', 'active', 'disabled'])], // Tous les statuts possibles
            'latitude' => ['sometimes', 'required', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'longitude' => ['sometimes', 'required', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
        ]);

        try {
            // Préparer les données à mettre à jour
            $updateData = $validatedData;

            // Gérer le mot de passe :
            // - Si on passe en public, mettre le mdp à null
            // - Si on reste/passe en privé et qu'un nouveau mdp est fourni, le hasher
            if (isset($validatedData['is_private'])) {
                if ($validatedData['is_private'] == false) {
                    $updateData['password'] = null;
                } elseif (!empty($validatedData['password'])) {
                    $updateData['password'] = Hash::make($validatedData['password']);
                } else {
                    // Si on passe en privé sans fournir de nouveau mdp, on garde l'ancien (s'il existe)
                    // ou la validation 'requiredIf' devrait avoir échoué.
                    // On retire 'password' de $updateData pour ne pas écraser l'ancien avec null.
                    unset($updateData['password']);
                }
            } elseif (isset($validatedData['password']) && $riddle->is_private && !empty($validatedData['password'])) {
                 // Si on est déjà privé et qu'on change juste le mot de passe
                 $updateData['password'] = Hash::make($validatedData['password']);
            } else {
                 // Si 'password' est dans $validatedData mais vide ou non applicable, on l'enlève
                 unset($updateData['password']);
            }


            // Mettre à jour l'énigme
            $riddle->update($updateData);

            // Retourner l'énigme mise à jour
            return response()->json($riddle, Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error updating riddle ' . $riddle->id . ': ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update riddle.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime (soft delete) une énigme.
     *
     * @param  \App\Models\Riddle  $riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Riddle $riddle): JsonResponse
    {
        // 1. Autorisation : Seul le créateur peut supprimer
        if (Auth::id() !== $riddle->creator_id) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        try {
            // TODO: Ajouter une logique pour vérifier si des parties sont en cours ?
            //       Ou laisser la suppression et gérer côté jeu ?

            $riddle->delete(); // Soft delete grâce au trait SoftDeletes

            return response()->json(null, Response::HTTP_NO_CONTENT); // Succès sans contenu

        } catch (\Exception $e) {
            Log::error('Error deleting riddle ' . $riddle->id . ': ' . $e->getMessage());
            return response()->json(['message' => 'Failed to delete riddle.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}