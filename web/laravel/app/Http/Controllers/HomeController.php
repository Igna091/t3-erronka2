<?php

namespace App\Http\Controllers;

use App\Support\OpeningHours;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * The public landing page.
     */
    public function __invoke(): View
    {
        $hours = OpeningHours::fromConfig();

        return view('home', [
            'status' => $hours->status(),
            'hours' => $hours->summary(),
        ]);
    }
}
