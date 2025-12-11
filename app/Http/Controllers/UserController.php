<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = \App\Models\User::query();

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->get();
        return UserResource::collection($users);
    }
}

