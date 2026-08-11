<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function orders(): Response
    {
        return Inertia::render('Staff/Orders');
    }
}
