<?php

namespace App\Http\Controllers;

use App\Models\Riddle;
use App\Models\Step;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StepController extends Controller
{
    public function index(Riddle $riddle)
    {
        // $limit = $request->get('limit', 20);
        // $offset = $request->get('offset', 0);

        // $riddles = Riddle::select('id', 'creator_id', 'title', 'is_private', 'status', 'latitude', 'longitude', 'created_at')
        //     ->where('status', 'active')
        //     ->withCount('steps')
        //     ->withAvg('reviews', 'difficulty')
        //     ->withAvg('reviews', 'rating')
        //     ->orderByDesc('created_at')
        //     ->skip($offset)
        //     ->take($limit)
        //     ->get();

        // return response()->json([
        //     'riddles' => $riddles
        // ], Response::HTTP_OK);

        $steps = $riddle->steps()->orderBy('order_number')->get();
        return response()->json($steps);
    }

    public function store(Request $request, Riddle $riddle)
    {
        if (Auth::id() !== $riddle->creator_id) {
            return response()->json(['message' => 'Non autorisé. Vous n\'êtes pas l\'auteur de cette énigme.'], 403);
        }

        $validated = $request->validate([
            // 'latitude' => ['required', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            // 'longitude' => ['required', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
            'latitude' => 'required|string',
            'longitude' => 'required|string',
        ]);
        $orderNumber = ($riddle->steps()->max('order_number') ?? 0) + 1;
        $qrCode = (string) Str::uuid();

        $step = $riddle->steps()->create([
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'order_number' => $orderNumber,
            'qr_code' => $qrCode,
        ]);

        return response()->json($step, Response::HTTP_CREATED);
    }

    public function show(Request $request, Riddle $riddle)
    {
        // // if (Auth::id() !== $riddle->creator_id) {
        // if ($request->user()->id !== $riddle->creator_id) {
        //     unset($riddle->password);
        //     $riddle->password = null;
        // }

        // return response()->json([
        //     'riddle' => $riddle
        // ], Response::HTTP_OK);
    }

    public function update(Request $request, Riddle $riddle)
    {
        // // TODO : ajouter vérif que connecté = créateur
        // $validated = $request->validate([
        //     'title'         => 'sometimes|required|string',
        //     'description'   => 'sometimes|required|string',
        //     'is_private'    => 'sometimes|boolean',
        //     'password'      => 'nullable|string',
        //     'latitude'      => 'sometimes|required|string',
        //     'longitude'     => 'sometimes|required|string',
        //     'status'        => 'sometimes|required|in:draft,active,disabled'
        // ]);
        // $riddle->update($validated);
        // return response()->json($riddle, Response::HTTP_OK);
    }

    public function destroy(Riddle $riddle)
    {
        // // TODO : ajouter vérif que connecté = créateur
        // $riddle->delete();
        // return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    // public function getStepList(Request $request, Riddle $riddle)
    // {
    //     if (Auth::id() !== $riddle->creator_id) {
    //         return response()->json(['message' => 'Non autorisé. Vous n\'êtes pas l\'auteur de cette énigme.'], 403);
    //     }

    //     $steps = Step::select('id', 'riddle_id', 'order_number', 'qr_code', 'latitude', 'longitude')
    //         ->where('riddle_id', $riddle->id)
    //         ->orderByDesc('order_number')
    //         ->get();

    //     return response()->json([
    //         'steps' => $steps
    //     ], Response::HTTP_OK);
    // }
}
