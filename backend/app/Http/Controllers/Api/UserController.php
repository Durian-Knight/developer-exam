<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * GET /api/users
     * Admin: every user (used for the assignee dropdown / user list).
     * User: only themselves (they can't assign tasks to anyone else anyway).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $users = $user->isAdmin()
            ? User::query()->orderBy('name')->get(['id', 'name', 'email', 'role'])
            : collect([$user]);

        return UserResource::collection($users);
    }

    /** POST /api/users - admin only (authorized in StoreUserRequest via UserPolicy). */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }
}
