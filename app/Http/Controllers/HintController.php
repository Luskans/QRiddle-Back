<?php

namespace App\Http\Controllers;

use App\Models\Hint;
use App\Models\Step;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HintController extends Controller
{
    /**
     * Liste les indices pour une étape donnée.
     */
    public function index($stepId)
    {
        $step = Step::findOrFail($stepId);
        $hints = $step->hints; // Relation Step::hints()
        return response()->json($hints, Response::HTTP_OK);
    }

    /**
     * Crée un nouvel indice pour une étape.
     */
    public function store(Request $request, $stepId)
    {
        $validated = $request->validate([
            'order_number' => 'required|integer',
            'type'         => 'required|in:text,image,audio',
            'content'      => 'required|string',
        ]);
        $validated['step_id'] = $stepId;
        $hint = Hint::create($validated);
        return response()->json($hint, Response::HTTP_CREATED);
    }

    /**
     * Affiche les détails d'un indice.
     */
    public function show($stepId, $hintId)
    {
        $hint = Hint::where('step_id', $stepId)->findOrFail($hintId);
        return response()->json($hint, Response::HTTP_OK);
    }

    /**
     * Met à jour un indice.
     */
    public function update(Request $request, $stepId, $hintId)
    {
        $hint = Hint::where('step_id', $stepId)->findOrFail($hintId);
        $validated = $request->validate([
            'order_number' => 'sometimes|required|integer',
            'type'         => 'sometimes|required|in:text,image,audio',
            'content'      => 'sometimes|required|string',
        ]);
        $hint->update($validated);
        return response()->json($hint, Response::HTTP_OK);
    }

    /**
     * Supprime un indice.
     */
    public function destroy($stepId, $hintId)
    {
        $hint = Hint::where('step_id', $stepId)->findOrFail($hintId);
        $hint->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
