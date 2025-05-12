<?php

namespace App\Http\Controllers;

use App\Models\Riddle;
use App\Models\Step;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
// Optionnel: Pour générer l'image QR Code
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class StepController extends Controller
{
    /**
     * Affiche la liste des étapes pour une énigme spécifique.
     * GET /riddles/{riddle}/steps
     *
     * @param  \App\Models\Riddle  $riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Riddle $riddle): JsonResponse
    {
        // Autorisation : L'utilisateur doit-il être le créateur pour voir la liste complète ?
        // Ou tout le monde peut voir la liste (mais pas forcément les détails comme le QR code exact) ?
        // Pour l'instant, supposons que tout utilisateur authentifié peut voir la liste.
        // Si seul le créateur peut voir :
        if (Auth::id() !== $riddle->creator_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        // Récupérer les étapes triées par order_number
        // Charger les relations si nécessaire (ex: nombre d'indices)
        $steps = $riddle->steps()
                       ->orderBy('order_number', 'asc')
                       // ->withCount('hints') // Optionnel: compter les indices
                       ->get(); // Récupérer toutes les étapes

        // Optionnel: Générer l'URL de l'image QR pour chaque étape si pas déjà fait
        // foreach ($steps as $step) {
        //     if ($step->qr_code) { // S'assurer qu'il y a une valeur QR
        //         $fileName = 'step_qr_' . $step->id . '.png'; // Nom de fichier prévisible
        //         $filePath = 'qrcodes/' . $fileName;
        //         if (Storage::disk('public')->exists($filePath)) {
        //             $step->qr_code_image_url = Storage::url($filePath);
        //         } else {
        //             // Tenter de générer si manquant (peut ralentir la requête index)
        //             try {
        //                 Storage::disk('public')->put($filePath, QrCode::format('png')->size(300)->generate($step->qr_code));
        //                 $step->qr_code_image_url = Storage::url($filePath);
        //             } catch (\Exception $e) {
        //                 Log::error("Failed to generate/save QR code image for step {$step->id} during index: " . $e->getMessage());
        //                 $step->qr_code_image_url = null;
        //             }
        //         }
        //     } else {
        //          $step->qr_code_image_url = null;
        //     }
        // }


        return response()->json(['steps' => $steps], Response::HTTP_OK);
    }

    /**
     * Crée une nouvelle étape pour une énigme spécifique.
     * POST /riddles/{riddle}/steps
     * (Implémentation déjà discutée, reprise ici pour la complétude)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Riddle  $riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Riddle $riddle): JsonResponse
    {
        // 1. Autorisation
        if (Auth::id() !== $riddle->creator_id) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        // 2. Validation (seulement lat/lon)
        $validatedData = $request->validate([
            'latitude' => ['required', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'longitude' => ['required', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
        ]);

        // 3. Calculer le numéro d'ordre
        $nextOrderNumber = ($riddle->steps()->max('order_number') ?? 0) + 1;

        // 4. Générer la valeur unique pour le QR code (UUID)
        $qrCodeValue = (string) Str::uuid();

        // 5. Créer l'étape
        try {
            $step = $riddle->steps()->create([
                'latitude' => $validatedData['latitude'],
                'longitude' => $validatedData['longitude'],
                'order_number' => $nextOrderNumber,
                'qr_code' => $qrCodeValue,
            ]);

            // 6. Générer l'image QR Code pour affichage
            $fileName = 'step_qr_' . $step->id . '.png'; // Utiliser un nom prévisible
            $filePath = 'qrcodes/' . $fileName;
            try {
                Storage::disk('public')->put($filePath, QrCode::format('png')->size(300)->generate($qrCodeValue));
                $step->qr_code_image_url = Storage::url($filePath);
            } catch (\Exception $e) {
                Log::error("Failed to generate/save QR code image for step {$step->id}: " . $e->getMessage());
                $step->qr_code_image_url = null;
            }

            // 7. Retourner la réponse
            return response()->json($step, Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Error creating step: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create step.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Affiche les détails d'une étape spécifique.
     * GET /steps/{step} (grâce à ->shallow())
     *
     * @param  \App\Models\Step  $step
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Step $step): JsonResponse
    {
        // Autorisation : L'utilisateur doit-il être le créateur pour voir les détails ?
        // Ou un joueur peut voir les détails d'une étape qu'il a atteinte ?
        // Pour l'instant, supposons que seul le créateur peut voir les détails complets via cette route.
        $riddle = $step->riddle; // Récupère l'énigme parente
        if (Auth::id() !== $riddle->creator_id) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        // Charger les relations si nécessaire (ex: indices)
        $step->load('hints'); // Charge les indices associés à cette étape

        // Générer l'URL de l'image QR si nécessaire
        if ($step->qr_code) {
            $fileName = 'step_qr_' . $step->id . '.png';
            $filePath = 'qrcodes/' . $fileName;
            if (Storage::disk('public')->exists($filePath)) {
                $step->qr_code_image_url = Storage::url($filePath);
            } else {
                 // Tenter de générer si manquant
                 try {
                    Storage::disk('public')->put($filePath, QrCode::format('png')->size(300)->generate($step->qr_code));
                    $step->qr_code_image_url = Storage::url($filePath);
                 } catch (\Exception $e) {
                    Log::error("Failed to generate/save QR code image for step {$step->id} during show: " . $e->getMessage());
                    $step->qr_code_image_url = null;
                 }
            }
        } else {
            $step->qr_code_image_url = null;
        }


        return response()->json($step, Response::HTTP_OK);
    }

    /**
     * Met à jour une étape spécifique.
     * PUT/PATCH /steps/{step} (grâce à ->shallow())
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Step  $step
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Step $step): JsonResponse
    {
        // 1. Autorisation : Seul le créateur peut modifier
        $riddle = $step->riddle;
        if (Auth::id() !== $riddle->creator_id) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        // 2. Validation des données (seulement lat/lon sont modifiables ici)
        // Si tu permets de changer l'ordre, ajoute 'order_number' à la validation.
        $validatedData = $request->validate([
            'latitude' => ['sometimes', 'required', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'longitude' => ['sometimes', 'required', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
            // 'order_number' => ['sometimes', 'required', 'integer', 'min:1', Rule::unique('steps')->where('riddle_id', $step->riddle_id)->ignore($step->id)], // Si réorganisation permise
        ]);

        // 3. Mettre à jour l'étape
        try {
            // Utiliser $validatedData car il ne contient que les champs validés présents dans la requête
            $step->update($validatedData);

            // Si l'ordre a été modifié, il faudrait potentiellement recalculer l'ordre des autres étapes.
            // C'est une logique plus complexe, souvent gérée côté client ou via une action dédiée.

            // Recharger les relations si elles ont pu changer ou pour la réponse
            $step->load('hints');

            // Regénérer l'URL QR (au cas où, bien que la valeur QR ne change pas ici)
            if ($step->qr_code) {
                $fileName = 'step_qr_' . $step->id . '.png';
                $filePath = 'qrcodes/' . $fileName;
                if (Storage::disk('public')->exists($filePath)) {
                    $step->qr_code_image_url = Storage::url($filePath);
                } // Ne pas regénérer ici pour l'update, sauf si nécessaire
            }


            return response()->json($step, Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error updating step ' . $step->id . ': ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update step.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime une étape spécifique.
     * DELETE /steps/{step} (grâce à ->shallow())
     *
     * @param  \App\Models\Step  $step
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Step $step): JsonResponse
    {
        // 1. Autorisation
        $riddle = $step->riddle;
        if (Auth::id() !== $riddle->creator_id) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        // 2. Logique de suppression
        try {
            $orderDeleted = $step->order_number;
            $riddleId = $step->riddle_id;

            // Supprimer l'image QR associée (optionnel)
            $fileName = 'step_qr_' . $step->id . '.png';
            $filePath = 'qrcodes/' . $fileName;
            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }

            $step->delete(); // Supprime l'étape de la base de données

            // 3. (IMPORTANT) Réorganiser les étapes suivantes
            // Décrémente l'order_number de toutes les étapes suivantes dans la même énigme
            Step::where('riddle_id', $riddleId)
                ->where('order_number', '>', $orderDeleted)
                ->decrement('order_number');

            return response()->json(null, Response::HTTP_NO_CONTENT); // 204 No Content

        } catch (\Exception $e) {
            Log::error('Error deleting step ' . $step->id . ': ' . $e->getMessage());
            return response()->json(['message' => 'Failed to delete step.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}