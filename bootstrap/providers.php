<?php

use App\Providers\AppServiceProvider;
use App\Providers\CreatorProfielServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\InstellingenServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    InstellingenServiceProvider::class,
    CreatorProfielServiceProvider::class,
];
