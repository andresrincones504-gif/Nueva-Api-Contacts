<?php

test('responde el estado del servicio', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()->assertJsonPath('status', 'ok');
});

test('responde 404 en JSON si la ruta no existe', function () {
    $response = $this->get('/api/v1/no-existe');

    $response->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('message', 'Recurso no encontrado.');
});
