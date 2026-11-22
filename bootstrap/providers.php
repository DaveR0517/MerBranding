<?php

use App\Providers\AppServiceProvider;
use App\Providers\CreatorProfielServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    CreatorProfielServiceProvider::class,
];
