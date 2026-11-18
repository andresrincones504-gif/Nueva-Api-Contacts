<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('inicia sesión y entrega un token con credenciales correctas', function () {
    // Preparar
    $user = User::factory()->create([
        'email' => 'ana@contacts.test',
        'password' => 'secreta123',
    ]);

    // Actuar
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ana@contacts.test',
        'password' => 'secreta123',
        'device_name' => 'pruebas',
    ]);

    // Comprobar
    $response->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email']])
        ->assertJsonPath('user.email', 'ana@contacts.test')
        ->assertJsonMissingPath('user.password');

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'name' => 'pruebas',
    ]);
});

test('rechaza el login con una contraseña incorrecta', function () {
    // Preparar
    User::factory()->create([
        'email' => 'ana@contacts.test',
        'password' => 'secreta123',
    ]);

    // Actuar
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ana@contacts.test',
        'password' => 'otra-contraseña',
        'device_name' => 'pruebas',
    ]);

    // Comprobar
    $response->assertUnprocessable()->assertInvalid(['email']);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('responde igual si el correo no existe', function () {
    // Actuar
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nadie@contacts.test',
        'password' => 'secreta123',
        'device_name' => 'pruebas',
    ]);

    // Comprobar
    $response->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Las credenciales no son correctas.');
});

test('no inicia sesión sin correo', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'password' => 'secreta123',
        'device_name' => 'pruebas',
    ]);

    $response->assertUnprocessable()->assertInvalid(['email']);
});

test('no inicia sesión sin contraseña', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ana@contacts.test',
        'device_name' => 'pruebas',
    ]);

    $response->assertUnprocessable()->assertInvalid(['password']);
});

test('no inicia sesión sin nombre de dispositivo', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ana@contacts.test',
        'password' => 'secreta123',
    ]);

    $response->assertUnprocessable()->assertInvalid(['device_name']);
});

test('bloquea el login al sexto intento fallido en un minuto', function () {
    // Preparar
    User::factory()->create(['email' => 'ana@contacts.test']);
    $intentoFallido = [
        'email' => 'ana@contacts.test',
        'password' => 'incorrecta',
        'device_name' => 'pruebas',
    ];

    // Actuar: cinco intentos permitidos
    foreach (range(1, 5) as $intento) {
        $this->postJson('/api/v1/auth/login', $intentoFallido)->assertUnprocessable();
    }

    // Comprobar: el sexto se bloquea
    $this->postJson('/api/v1/auth/login', $intentoFallido)->assertTooManyRequests();
});

test('no muestra el usuario autenticado sin token', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertUnauthorized();
});

test('responde 401 en JSON aunque falte la cabecera Accept', function () {
    $response = $this->get('/api/v1/auth/me');

    $response->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json');
});

test('muestra el usuario autenticado', function () {
    // Preparar
    $user = User::factory()->create(['email' => 'ana@contacts.test']);
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->getJson('/api/v1/auth/me');

    // Comprobar
    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', 'ana@contacts.test')
        ->assertJsonMissingPath('data.password');
});

test('cierra sesión y revoca el token', function () {
    // Preparar
    $user = User::factory()->create();
    $token = $user->createToken('pruebas')->plainTextToken;

    // Actuar
    $response = $this->withToken($token)->postJson('/api/v1/auth/logout');

    // Comprobar
    $response->assertNoContent();
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('no cierra sesión sin token', function () {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertUnauthorized();
});
