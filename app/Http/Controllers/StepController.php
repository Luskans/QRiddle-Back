<?php

namespace App\Http\Controllers;

use App\Http\Resources\StepDetailResource;
use App\Models\Riddle;
use App\Models\Step;
use App\Services\Interfaces\StepServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StepController extends Controller
{
    protected $stepService;

    public function __construct(StepServiceInterface $stepService)
    {
        $this->stepService = $stepService;
    }

    /**
     * Create a new step for a riddle.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Riddle  $riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Riddle $riddle): JsonResponse
    {
        $validatedData = $request->validate([
            'latitude' => ['required', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'longitude' => ['required', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
        ]);

        try {
            $step = $this->stepService->createStep($riddle, $validatedData, $request->user()->id);
            
            return response()->json([
                'data' => $step,
            ], Response::HTTP_CREATED);
            
        } catch (\Exception $e) {
            Log::error('Error creating step: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage() ?: 'Erreur serveur lors de la création de l\'étape.'], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get the detail of a step
     *
     * @param  \App\Models\Step  $step
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Step $step): JsonResponse
    {
        try {
            $stepDetail = $this->stepService->getStepDetail($step, $request->user()->id);
            
            return response()->json([
                'data' => new StepDetailResource($stepDetail),
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error fetching step detail: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage() ?: 'Erreur serveur lors de la récupération des détails de l\'étape.'], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a step.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Step  $step
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Step $step): JsonResponse
    {
         $validatedData = $request->validate([
            'latitude' => ['sometimes', 'required', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'longitude' => ['sometimes', 'required', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
        ]);

        try {
            $updatedStep = $this->stepService->updateStep($step, $validatedData, $request->user()->id);
            
            return response()->json([
                'data' => $updatedStep,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error updating step {$step->id}: " . $e->getMessage());
            return response()->json(['message' => $e->getMessage() ?: 'Erreur serveur lors de la mise à jour de l\'étape.'], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a step.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Step  $step
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Step $step): JsonResponse
    {
        try {
            $this->stepService->deleteStep($step, $request->user()->id);
            return response()->json(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            Log::error("Error deleting step {$step->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la suppression de l\'étape.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}