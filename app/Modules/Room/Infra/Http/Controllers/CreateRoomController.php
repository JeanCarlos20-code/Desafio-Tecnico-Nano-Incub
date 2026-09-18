<?php

namespace App\Modules\Room\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CreateRoomController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Room/Create');
    }
}
