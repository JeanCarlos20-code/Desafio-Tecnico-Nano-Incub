<?php

namespace App\Modules\User\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Application\UseCases\CreateUser;
use App\Modules\User\Infra\Http\Requests\StoreUserRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('User/Create');
    }

    public function store(StoreUserRequest $request, CreateUser $createUser): RedirectResponse
    {
        $data = $request->validated();

        $createUser->execute($data['name'], $data['email'], $data['password']);

        return redirect()->route('users.create');
    }
}
