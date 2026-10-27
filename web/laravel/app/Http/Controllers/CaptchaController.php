<?php

namespace App\Http\Controllers;

use App\Support\Captcha;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptchaController extends Controller
{
    /**
     * A new picture for the sign-up or login form; each one replaces the answer to the last.
     */
    public function __invoke(Request $request): Response
    {
        return response(Captcha::image($request->session()), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }
}
