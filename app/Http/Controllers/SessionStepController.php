<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SessionStepController extends Controller
{
    /**
     * Affiche le suivi détaillé d'une étape dans une session de jeu.
     */
    public function show($sessionId, $stepId)
    {
        // Retourner les détails de la progression sur une étape spécifique.
    }
    
    /**
     * Met à jour la progression (par exemple, le nombre d'indices utilisés et le statut) pour une étape dans une session.
     */
    public function update(Request $request, $sessionId, $stepId)
    {
        // Mettre à jour la progression (hint_used_number, status, etc.).
    }
}
