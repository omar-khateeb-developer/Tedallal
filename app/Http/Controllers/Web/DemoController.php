<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DemoController extends Controller
{
    /**
     * Display the 60-second interactive hospitality sandbox simulator.
     */
    public function index(): Response
    {
        return Inertia::render('Demo/Sandbox');
    }
}
