<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Dashboard');
    }

    public function createRestaurant(): Response
    {
        return Inertia::render('SuperAdmin/CreateRestaurant');
    }

    public function users(): Response
    {
        return Inertia::render('SuperAdmin/Users');
    }
}
