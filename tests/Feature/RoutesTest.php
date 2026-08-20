<?php

it('renvoie 404 sur GET /debug-excel', function () {
    $response = $this->get('/debug-excel');

    $response->assertNotFound();
});
