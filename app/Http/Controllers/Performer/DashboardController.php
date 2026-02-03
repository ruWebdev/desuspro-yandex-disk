<?php

namespace App\Http\Controllers\Performer;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Performer/Dashboard');
    }
}
