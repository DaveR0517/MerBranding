<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\View\View;

/** Publieke controle van een academy-certificaat: wie de controlelink heeft, ziet of het certificaat echt is. */
class AcademyController extends Controller
{
    public function controle(string $code): View
    {
        $certificaat = Certificate::with(['user', 'enrollment.courseDay'])->where('verification_code', $code)->first();

        return view('academy.controle', ['certificaat' => $certificaat]);
    }
}
