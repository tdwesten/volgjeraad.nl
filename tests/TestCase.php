<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Hermetische suite: elke niet-gefakete externe HTTP-call (ORI, YouTube,
        // Supadata/transcript, model-prices én AI-providers — laravel/ai gebruikt de
        // Http-facade) wordt een harde test-failure i.p.v. de echte endpoint te bellen.
        // Een toekomstige test die een agent/HTTP-service ongefaket raakt faalt dus,
        // i.p.v. de echte API te bellen.
        Http::preventStrayRequests();
    }
}
