<?php

namespace App\Modules\User\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Application\UseCases\CreateUser;
use App\Modules\User\Infra\Database\Models\User as UserModel;
use App\Modules\User\Infra\Http\Requests\StoreUserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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

        $created = $createUser->execute($data['name'], $data['email'], $data['password']);

        Auth::login(UserModel::findOrFail($created->id));

        return redirect()->route('reservations.index');
    }
}
