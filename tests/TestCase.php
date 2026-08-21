<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Les tests PHP n'ont pas à dépendre d'un `npm run build` préalable :
        // sans ça, toute page rendue échoue sur « Vite manifest not found ».
        $this->withoutVite();
    }
}
