<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\SessionStep;
use App\Models\Step;
use App\Models\Hint; // Importer Hint
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Carbon\Carbon; // Pour manipuler les dates/heures
use Illuminate\Support\Facades\Log;

class SessionStepController extends Controller
{
    // --- Méthodes CRUD Optionnelles (si besoin d'historique détaillé) ---

    /**
     * Affiche l'historique des étapes pour une session de jeu donnée.
     *
     * @param  \App\Models\GameSession  $gameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(GameSession $gameSession): JsonResponse
    {
        // Vérifier si l'utilisateur peut voir cette session (son propre historique)
        if (Auth::id() !== $gameSession->player_id) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        $sessionSteps = $gameSession->sessionSteps()
                                    ->with('step:id,order_number,latitude,longitude') // Charger infos de base de l'étape
                                    ->orderBy('start_time', 'asc') // Trier par début
                                    ->get();

        return response()->json($sessionSteps);
    }

    /**
     * Affiche les détails d'une étape spécifique d'une session.
     *
     * @param  \App\Models\GameSession  $gameSession
     * @param  \App\Models\SessionStep  $sessionStep // Laravel injecte via la route imbriquée
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(GameSession $gameSession, SessionStep $sessionStep): JsonResponse
    {
        // Vérifier l'appartenance et l'autorisation
        if ($sessionStep->game_session_id !== $gameSession->id || Auth::id() !== $gameSession->player_id) {
             return response()->json(['message' => 'Not Found or Unauthorized.'], Response::HTTP_NOT_FOUND);
        }

        // Charger les relations si nécessaire
        $sessionStep->load('step', 'gameSession.riddle');

        return response()->json($sessionStep);
    }


    // --- Actions Principales du Jeu ---

    /**
     * Valide une étape en vérifiant le QR code scanné.
     * Met à jour le statut de l'étape de session, calcule le score si fin de partie,
     * et prépare la prochaine étape.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\GameSession  $gameSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateStep(Request $request, GameSession $gameSession): JsonResponse
    {
        // 1. Vérifier si l'utilisateur est le joueur de cette session
        if (Auth::id() !== $gameSession->player_id) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        // 2. Vérifier si la partie est bien active
        if ($gameSession->status !== 'active') { // Ou 'in_progress'
            return response()->json(['message' => 'Game session is not active.'], Response::HTTP_BAD_REQUEST);
        }

        // 3. Valider le QR code reçu
        $validated = $request->validate([
            'qr_code' => 'required|string|uuid', // Valider que c'est un UUID si vous utilisez ça
        ]);
        $scannedQrCode = $validated['qr_code'];

        // 4. Trouver l'étape de session *actuellement active* pour cette partie
        $currentSessionStep = $gameSession->sessionSteps()
                                          ->where('status', 'active') // Ou 'in_progress'
                                          ->with('step') // Charger l'étape associée pour vérifier le QR code
                                          ->latest('start_time') // Prend la plus récente si jamais il y en a plusieurs actives (ne devrait pas)
                                          ->first();

        if (!$currentSessionStep) {
            // Ne devrait pas arriver si la session est active, mais sécurité
            return response()->json(['message' => 'No active step found for this session.'], Response::HTTP_NOT_FOUND);
        }

        // 5. Vérifier si le QR code scanné correspond à celui de l'étape attendue
        if ($scannedQrCode !== $currentSessionStep->step->qr_code) {
            return response()->json(['message' => 'Incorrect QR code.'], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 pour erreur logique/validation
        }

        // --- Validation Réussie ---

        // 6. Mettre à jour l'étape de session actuelle
        $currentSessionStep->status = 'completed';
        $currentSessionStep->end_time = Carbon::now();
        $currentSessionStep->save();

        // 7. Vérifier si c'était la dernière étape de l'énigme
        $riddle = $gameSession->riddle()->withCount('steps')->first(); // Charger l'énigme avec le compte des étapes
        $totalStepsInRiddle = $riddle->steps_count;
        $currentStepOrder = $currentSessionStep->step->order_number;

        $isGameComplete = ($currentStepOrder >= $totalStepsInRiddle);
        $nextStep = null;
        $nextSessionStep = null;
        $nextHints = []; // Indices pour la prochaine étape

        if ($isGameComplete) {
            // 8.a. Fin de partie : Mettre à jour la session de jeu
            $gameSession->status = 'completed';
            // Calculer le score final (à implémenter dans GameSession ou un Service)
            $gameSession->score = $this->calculateFinalScore($gameSession); // Méthode à créer
            $gameSession->save();

            // TODO: Mettre à jour les GlobalScores (via un Event/Listener ou directement ici/Service)

        } else {
            // 8.b. Étape suivante : Trouver la prochaine étape de l'énigme
            $nextStep = Step::where('riddle_id', $gameSession->riddle_id)
                            ->where('order_number', $currentStepOrder + 1)
                            ->first();

            if ($nextStep) {
                // Créer la nouvelle étape de session (active)
                $nextSessionStep = $gameSession->sessionSteps()->create([
                    'step_id' => $nextStep->id,
                    'status' => 'active', // Ou 'in_progress'
                    'start_time' => Carbon::now(),
                    'hint_used_number' => 0, // Réinitialiser pour la nouvelle étape
                ]);
                // Charger les indices disponibles pour la prochaine étape (seulement le premier ?)
                $nextHints = $nextStep->hints()->orderBy('order_number')->limit(1)->get(); // Ou selon votre logique d'indices
            } else {
                // Problème : on n'a pas trouvé l'étape suivante alors que ce n'était pas la dernière ?
                Log::warning("Could not find next step for riddle {$gameSession->riddle_id} after order {$currentStepOrder}");
                // Marquer quand même la partie comme terminée ? Ou erreur ?
                $isGameComplete = true; // Considérer comme terminé s'il n'y a pas de suite logique
                $gameSession->status = 'completed';
                $gameSession->score = $this->calculateFinalScore($gameSession);
                $gameSession->save();
            }
        }

        // 9. Préparer la réponse
        $responseData = [
            'message' => $isGameComplete ? 'Riddle completed successfully!' : 'Step validated successfully!',
            'isGameComplete' => $isGameComplete,
            'updatedSession' => $isGameComplete ? $gameSession->fresh() : null, // Renvoyer la session mise à jour si terminée
            'nextSessionStep' => $nextSessionStep ? $nextSessionStep->load('step') : null, // Renvoyer la nouvelle étape de session avec l'étape de base
            'nextHints' => $nextHints, // Renvoyer les indices pour la prochaine étape
        ];

        return response()->json($responseData, Response::HTTP_OK);
    }


    /**
     * Enregistre l'utilisation d'un indice pour l'étape active d'une session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\GameSession  $gameSession
     * @param  \App\Models\Step  $step // L'étape pour laquelle on demande un indice
     * @return \Illuminate\Http\JsonResponse
     */
    public function useHint(Request $request, GameSession $gameSession, Step $step): JsonResponse
    {
         // 1. Vérifications (Utilisateur, Session active)
         if (Auth::id() !== $gameSession->player_id) {
             return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
         }
         if ($gameSession->status !== 'active') {
             return response()->json(['message' => 'Game session is not active.'], Response::HTTP_BAD_REQUEST);
         }

         // 2. Trouver l'étape de session correspondante et active
         $currentSessionStep = $gameSession->sessionSteps()
                                           ->where('step_id', $step->id)
                                           ->where('status', 'active')
                                           ->first();

         if (!$currentSessionStep) {
             return response()->json(['message' => 'This step is not currently active in this session.'], Response::HTTP_BAD_REQUEST);
         }

         // 3. Déterminer quel indice révéler
         $hintsUsedCount = $currentSessionStep->hint_used_number;
         $nextHint = $step->hints()
                          ->where('order_number', '>', $hintsUsedCount) // Prend le prochain indice non utilisé
                          ->orderBy('order_number', 'asc')
                          ->first();

         if (!$nextHint) {
             return response()->json(['message' => 'No more hints available for this step.'], Response::HTTP_NOT_FOUND);
         }

         // 4. Mettre à jour le compteur d'indices utilisés sur l'étape de session
         $currentSessionStep->increment('hint_used_number');
         // $currentSessionStep->save(); // increment() sauvegarde déjà

         // 5. Retourner l'indice révélé et l'étape de session mise à jour
         return response()->json([
             'hint' => $nextHint,
             'updatedSessionStep' => $currentSessionStep->fresh(), // Renvoyer l'état à jour
         ], Response::HTTP_OK);
    }


    /**
     * Calcule le score final pour une session de jeu terminée.
     * (Méthode privée ou à déplacer dans un Service)
     *
     * @param GameSession $gameSession
     * @return int
     */
    private function calculateFinalScore(GameSession $gameSession): int
    {
        // Recharger les étapes de session pour avoir les temps et indices
        $gameSession->load('sessionSteps');

        // 1. Calculer la durée totale en secondes
        $totalDurationSeconds = $gameSession->getTotalDuration(); // Utilise la méthode du modèle

        // 2. Calculer le nombre total d'indices utilisés
        $totalHintsUsed = $gameSession->sessionSteps->sum('hint_used_number');

        // 3. Logique de scoring (exemple simple, à adapter !)
        // Score de base (plus le temps est court, plus le score est haut)
        // Pénalité par indice utilisé
        $baseScore = max(0, 10000 - ($totalDurationSeconds * 0.5)); // Exemple: 0.5 point perdu par seconde
        $hintPenalty = $totalHintsUsed * 500; // Exemple: 500 points perdus par indice

        $finalScore = max(0, round($baseScore - $hintPenalty)); // Score minimum de 0

        Log::info("Score calculation for session {$gameSession->id}: Duration={$totalDurationSeconds}s, Hints={$totalHintsUsed}, Base={$baseScore}, Penalty={$hintPenalty}, Final={$finalScore}");

        return $finalScore;
    }
}