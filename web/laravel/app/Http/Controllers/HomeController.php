<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        $user = request()->user();

        return view('home', [
            'status' => $hours->status(),
            'hours' => $hours->summary(),
            // For the greeting of a logged-in visitor
            'latestOrder' => $user instanceof User ? $user->orders()->latest('id')->first() : null,
        ]);
    }
}
