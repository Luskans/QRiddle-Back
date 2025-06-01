<?php

namespace App\Http\NewControllers;

use App\Http\Resources\StepDetailResource;
use App\Models\Riddle;
use App\Models\Step;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StepController extends Controller
{
    /**
     * Create a new step for a riddle.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \App\Models\Riddle $riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Riddle $riddle): JsonResponse
    {
        if (Auth::id() !== $riddle->creator_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        $validatedData = $request->validate([
            'latitude' => ['required', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'longitude' => ['required', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
        ]);

        $nextOrderNumber = ($riddle->steps()->max('order_number') ?? 0) + 1;
        $qrCodeValue = (string) Str::uuid();

        try {
            $step = $riddle->steps()->create([
                'latitude' => $validatedData['latitude'],
                'longitude' => $validatedData['longitude'],
                'order_number' => $nextOrderNumber,
                'qr_code' => $qrCodeValue,
            ]);

            return response()->json([
                'data' => $step,
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Error creating step: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la création de l\'étape a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get the detail of a step
     *
     * @param  \App\Models\Step $step
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Step $step): JsonResponse
    {
        $riddle = $step->riddle;
        if (!$riddle) {
            return response()->json(['message' => 'Étape non associée à une énigme.'], Response::HTTP_NOT_FOUND);
        }

        if (Auth::id() !== $riddle->creator_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        $step->load(['hints' => function($query) {
            $query->orderBy('order_number', 'asc')
                ->select('id', 'step_id', 'order_number', 'type', 'content');
        }]);

        return response()->json([
            'data' => new StepDetailResource($step),
        ], Response::HTTP_OK);
    }


    /**
     * Update a step.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \App\Models\Step $step
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Step $step): JsonResponse
    {
        $riddle = $step->riddle;
        if (!$riddle) {
            return response()->json(['message' => 'Étape non associée à une énigme.'], Response::HTTP_NOT_FOUND);
        }

        if (Auth::id() !== $riddle->creator_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        $validatedData = $request->validate([
            'latitude' => ['sometimes', 'required', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'longitude' => ['sometimes', 'required', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
        ]);

        try {
            $step->update($validatedData);
            $step->refresh();

            return response()->json([
                'data' => $step,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error updating step {$step->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la mise à jour de l\'étape a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Delete a step.
     *
     * @param  \App\Models\Step $step
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Step $step): JsonResponse
    {
        $riddle = $step->riddle;
        if (!$riddle) {
            return response()->json(['message' => 'Étape non associée à une énigme.'], Response::HTTP_NOT_FOUND);
        }

        if (Auth::id() !== $riddle->creator_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $orderDeleted = $step->order_number;
            $riddleId = $step->riddle_id;

            $step->delete();

            Step::where('riddle_id', $riddleId)
                ->where('order_number', '>', $orderDeleted)
                ->decrement('order_number');

            return response()->json(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            Log::error("Error deleting step {$step->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la suppression de l\'étape a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}