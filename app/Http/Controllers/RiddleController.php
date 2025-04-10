<?php

namespace App\Http\Controllers;

use App\Interfaces\RiddleServiceInterface;
use App\Models\Riddle;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;


class RiddleController extends Controller
{
    protected $riddleService;

    public function __construct(RiddleServiceInterface $riddleService)
    {
        $this->riddleService = $riddleService;
    }

    public function index(Request $request)
    {
        $limit = $request->get('limit', 20);
        $offset = $request->get('offset', 0);

        $riddles = Riddle::select('id', 'creator_id', 'title', 'is_private', 'status', 'latitude', 'longitude', 'created_at')
            ->where('status', 'active')
            ->withCount('steps')
            ->withAvg('reviews', 'difficulty')
            ->withAvg('reviews', 'rating')
            ->orderByDesc('created_at')
            ->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'riddles' => $riddles
        ], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'         => 'required|string',
            'description'   => 'required|string',
            'is_private'    => 'required|boolean',
            'password'      => 'nullable|string',
            'latitude'      => 'required|string',
            'longitude'     => 'required|string',
            'status'        => 'required|in:draft,active,disabled',
        ]);
        $validated['creator_id'] = $request->user()->id;
        $riddle = Riddle::create($validated);
        return response()->json($riddle, Response::HTTP_CREATED);
    }

    public function show(Request $request, Riddle $riddle)
    {
        // if (Auth::id() !== $riddle->creator_id) {
        if ($request->user()->id !== $riddle->creator_id) {
            unset($riddle->password);
            $riddle->password = null;
        }

        return response()->json([
            'riddle' => $riddle
        ], Response::HTTP_OK);
    }

    public function update(Request $request, Riddle $riddle)
    {
        // TODO : ajouter vérif que connecté = créateur
        $validated = $request->validate([
            'title'         => 'sometimes|required|string',
            'description'   => 'sometimes|required|string',
            'is_private'    => 'sometimes|boolean',
            'password'      => 'nullable|string',
            'latitude'      => 'sometimes|required|string',
            'longitude'     => 'sometimes|required|string',
            'status'        => 'sometimes|required|in:draft,active,disabled'
        ]);
        $riddle->update($validated);
        return response()->json($riddle, Response::HTTP_OK);
    }

    public function destroy(Riddle $riddle)
    {
        // TODO : ajouter vérif que connecté = créateur
        $riddle->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function getCreatedList(Request $request): JsonResponse
    {
        // TODO : ajouter vérif que connecté = créateur
        $user = $request->user();
        $limit = $request->get('limit', 20);
        $offset = $request->get('offset', 0);

        $riddles = Riddle::select('id', 'creator_id', 'title', 'is_private', 'status', 'latitude', 'longitude', 'created_at')
            ->where('creator_id', $user->id)
            ->withCount('steps')
            ->withAvg('reviews', 'difficulty')
            ->withAvg('reviews', 'rating')
            ->orderByDesc('created_at')
            ->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'riddles' => $riddles
        ], Response::HTTP_OK);
    }
}