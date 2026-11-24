<?php

namespace App\Jobs;

use App\Services\Geocoder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Zoekt de plek op de kaart van een vestiging of aanmelding, op de achtergrond (het formulier wacht er niet op). */
class Geocodeer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Model $model) {}

    public function handle(Geocoder $geocoder): void
    {
        $geocoder->plaats($this->model);
    }
}
