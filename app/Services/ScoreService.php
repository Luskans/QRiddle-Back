<?php

namespace App\Services;

use App\Interfaces\ScoreServiceInterface;
use App\Models\GameSession;
use App\Models\GlobalScore;
use App\Models\Riddle;
use Illuminate\Support\Facades\DB;


class ScoreService implements ScoreServiceInterface
{
    /**
     * Get the paginated list of global ranking by period.
     *
     * @param string $period
     * @param int $page
     * @param int $limit
     * @param int $userId
     * @return array
     */
    public function getGlobalRanking(string $period, int $page, int $limit, int $userId)
    {
        $offset = ($page - 1) * $limit;

        $query = GlobalScore::query()
            ->select(['id', 'user_id', 'score'])
            ->where('period', $period)
            ->orderBy('score', 'desc')
            ->with('user:id,name,image');

        $totalCount = $query->count();
        $totalPages = ceil($totalCount / $limit);

        $globalRanks = $query->skip($offset)
            ->take($limit)
            ->get();

        $userScore = GlobalScore::query()
            ->select(['id', 'score'])
            ->where('user_id', $userId)
            ->where('period', $period)
            ->value('score');

        $userRank = null;
        if ($userScore) {
            $userRank = GlobalScore::query()
                ->where('period', $period)
                ->where('score', '>', $userScore)
                ->count() + 1;
        }

        return [
            'items' => $globalRanks,
            'data' => [
                'userScore' => $userScore,
                'userRank' => $userRank,
            ],
            'page' => $page,
            'limit' => $limit,
            'totalItems' => $totalCount,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ];
    }

    /**
     * Get the paginated list of riddle ranking.
     *
     * @param Riddle $riddle
     * @param int $page
     * @param int $limit
     * @param int $userId
     * @return array
     */
    public function getRiddleRanking(Riddle $riddle, int $page, int $limit, int $userId)
    {
        $offset = ($page - 1) * $limit;

        $query = GameSession::query()
            ->select(['id', 'user_id', 'score'])
            ->where('riddle_id', $riddle->id)
            ->where('status', 'completed')
            ->orderBy('score', 'desc')
            ->with('user:id,name,image');

        $totalCount = $query->count();
        $totalPages = ceil($totalCount / $limit);

        $riddleRanks = $query->skip($offset)
            ->take($limit)
            ->get();

        $userScore = GameSession::query()
            ->select(['id', 'score'])
            ->where('riddle_id', $riddle->id)
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->value('score');

        $userRank = null;
        if ($userScore) {
            $userRank = GameSession::query()
                ->where('riddle_id', $riddle->id)
                ->where('score', '>', $userScore)
                ->count() + 1;
        }

        return [
            'items' => $riddleRanks,
            'data' => [
                'userScore' => $userScore,
                'userRank' => $userRank,
            ],
            'page' => $page,
            'limit' => $limit,
            'totalItems' => $totalCount,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ];
    }

    /**
     * Get the top 5 of global ranking by period.
     *
     * @param string $period
     * @param int $userId
     * @return array
     */
    public function getTopGlobalRanking(string $period, int $userId)
    {      
        $globalRanking = GlobalScore::query()
            ->select(['id', 'user_id', 'score'])
            ->where('period', $period)
            ->orderBy('score', 'desc')
            ->with('user:id,name,image')
            ->take(5)
            ->get();

        $userScore = GlobalScore::query()
            ->select(['id', 'score'])
            ->where('user_id', $userId)
            ->where('period', $period)
            ->value('score');

        $userRank = null;
        if ($userScore) {
            $userRank = GlobalScore::query()
                ->where('period', $period)
                ->where('score', '>', $userScore)
                ->count() + 1;
        }

        return [
            'items' => $globalRanking,
            'data' => [
                'userScore' => $userScore,
                'userRank' => $userRank,
            ],
        ];
    }

    /**
     * Get the top 5 list of riddle ranking.
     *
     * @param Riddle $riddle
     * @param int $userId
     * @return array
     */
    public function getTopRiddleRanking(Riddle $riddle, int $userId)
    {        
        $riddleRanking = GameSession::query()
            ->select(['id', 'user_id', 'score'])
            ->where('riddle_id', $riddle->id)
            ->where('status', 'completed')
            ->orderBy('score', 'desc')
            ->with('user:id,name,image')
            ->take(5)
            ->get();

        $userScore = GameSession::query()
            ->select(['id', 'score'])
            ->where('riddle_id', $riddle->id)
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->value('score');

        $userRank = null;
        if ($userScore) {
            $userRank = GameSession::query()
                ->where('riddle_id', $riddle->id)
                ->where('score', '>', $userScore)
                ->count() + 1;
        }

        return [
            'items' => $riddleRanking,
            'data' => [
                'userScore' => $userScore,
                'userRank' => $userRank,
            ],
        ];
    }

    /**
     * Calculate the score after completing a riddle.
     *
     * @param GameSession $gameSession
     * @return int
     */
    public function calculateFinalScore(GameSession $gameSession): int
    {
        // Récupérer les données nécessaires
        $riddle = $gameSession->riddle;
        $totalSteps = $riddle->steps()->count();
        
        // Calculer le temps total en secondes
        $totalDuration = $gameSession->getTotalDuration();
        
        // Récupérer la difficulté moyenne de l'énigme (si disponible)
        $avgDifficulty = $riddle->reviews()->avg('difficulty') ?: 3; // Valeur par défaut si pas d'avis
        
        // Calculer le temps moyen des autres utilisateurs ayant complété cette énigme
        $avgCompletionTime = GameSession::where('riddle_id', $riddle->id)
            ->where('status', 'completed')
            ->where('id', '!=', $gameSession->id)
            ->get()
            ->avg(function($session) {
                return $session->getTotalDuration();
            }) ?: $totalDuration; // Si pas d'autres sessions, utiliser le temps actuel
        
        // Score total pour toutes les étapes
        $totalScore = 0;
        
        // Calculer le score pour chaque étape
        $sessionSteps = $gameSession->sessionSteps()->with('step')->get();
        
        foreach ($sessionSteps as $sessionStep) {
            // Base score pour chaque étape - 20 points par étape
            $stepBaseScore = 20;
            
            // Pénalité pour les indices supplémentaires
            $hintPenalty = 0;
            for ($i = 1; $i <= $sessionStep->extra_hints; $i++) {
                if ($i === 1) {
                    $hintPenalty += 3;
                } else if ($i === 2) {
                    $hintPenalty += 2;
                } else {
                    $hintPenalty += 1;
                }
            }
            
            // Calculer le score de l'étape (minimum 12 points)
            $stepScore = max(12, $stepBaseScore - $hintPenalty);
            
            // Ajouter au score total
            $totalScore += $stepScore;
        }
        
        // Bonus de difficulté - facteur multiplicateur basé sur la difficulté
        $difficultyMultiplier = 1 + (($avgDifficulty - 1) * 0.1); // 1.0, 1.1, 1.2, 1.3, 1.4 pour les niveaux 1-5
        $scoreWithDifficulty = $totalScore * $difficultyMultiplier;
        
        // Bonus/malus de temps - entre -50% et +50%
        $timeMultiplier = 1.0;
        if ($avgCompletionTime > 0 && $totalDuration > 0) {
            // Calculer le ratio de temps (temps moyen / temps utilisateur)
            $timeRatio = $avgCompletionTime / $totalDuration;
            
            // Limiter le multiplicateur entre 0.5 et 1.5 (de -50% à +50%)
            $timeMultiplier = min(1.5, max(0.5, $timeRatio));
        }
        
        // Appliquer le multiplicateur de temps
        $finalScore = $scoreWithDifficulty * $timeMultiplier;
        
        // Arrondir à l'entier le plus proche
        return (int) round($finalScore);
    }

    /**
     * Update global scores after completing a riddle.
     *
     * @param int $userId
     * @param int $score
     * @return void
     */
    public function updateGlobalScores(int $userId, int $score): void
    {
        // Mettre à jour les scores globaux pour les différentes périodes
        $periods = ['week', 'month', 'all'];
        
        foreach ($periods as $period) {
            $globalScore = GlobalScore::firstOrNew([
                'user_id' => $userId,
                'period' => $period
            ]);
            
            $globalScore->score = ($globalScore->score ?? 0) + $score;
            $globalScore->save();
        }
    }
}