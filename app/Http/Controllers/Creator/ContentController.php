<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Concerns\KoppelingActies;
use App\Http\Controllers\Controller;
use App\Models\Creator;
use Illuminate\Http\Request;

/** Creator: koppelingen met Instagram en TikTok (fase 3). */
class ContentController extends Controller
{
    use KoppelingActies;

    protected function eigenaar(Request $request, bool $beheren = false): Creator
    {
        return $request->user()->creator()->firstOrFail();
    }

    protected function omgeving(): string
    {
        return 'creator';
    }
}
