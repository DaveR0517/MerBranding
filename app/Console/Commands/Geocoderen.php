<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Location;
use App\Services\Geocoder;
use Illuminate\Console\Command;

/** Zet vestigingen en aanmeldingen van ondernemers zonder plek alsnog op de kaart (eenmalig, of na een storing bij PDOK). */
class Geocoderen extends Command
{
    protected $signature = 'mebranding:geocoderen {--opnieuw : Ook wat al een plek heeft}';

    protected $description = 'Vestigingen en aanmeldingen van ondernemers op de kaart zetten (PDOK)';

    public function handle(Geocoder $geocoder): int
    {
        $vestigingen = Location::query()->when(! $this->option('opnieuw'), fn ($q) => $q->whereNull('lat'))->get();
        $aanmeldingen = Lead::where('type', Lead::TYPE_ONDERNEMER)->when(! $this->option('opnieuw'), fn ($q) => $q->whereNull('lat'))->get();

        $gevonden = 0;
        foreach ($vestigingen->concat($aanmeldingen) as $model) {
            $gevonden += $geocoder->plaats($model) ? 1 : 0;
            usleep(150_000); // vriendelijk voor PDOK
        }
        $this->info("Op de kaart gezet: {$gevonden} van ".($vestigingen->count() + $aanmeldingen->count()).'.');

        return self::SUCCESS;
    }
}
