<?php

use App\Models\Contact;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('no muestra el perfil sin sesión', function () {
    $response = $this->getJson('/api/v1/profile');

    $response->assertUnauthorized();
});

test('no actualiza el perfil sin sesión', function () {
    $response = $this->putJson('/api/v1/profile', ['name' => 'Nombre Nuevo']);

    $response->assertUnauthorized();
});

test('muestra el perfil del usuario autenticado', function () {
    // Preparar
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->getJson('/api/v1/profile');

    // Comprobar
    $response->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.phone_number', $user->phone_number)
        ->assertJsonMissingPath('data.password');
});

test('actualiza el perfil del usuario', function () {
    // Preparar
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->putJson('/api/v1/profile', [
        'name' => 'Nombre Nuevo',
        'address' => 'Carrera 1 # 2-3',
        'address_visible' => false,
    ]);

    // Comprobar
    $response->assertOk()->assertJsonPath('data.name', 'Nombre Nuevo');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Nombre Nuevo',
        'address' => 'Carrera 1 # 2-3',
        'address_visible' => false,
    ]);
});

test('permite guardar el perfil con su propio correo y teléfono', function () {
    // Preparar
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->putJson('/api/v1/profile', [
        'email' => $user->email,
        'phone_number' => $user->phone_number,
    ]);

    // Comprobar
    $response->assertOk();
});

test('rechaza un correo que ya usa otro usuario', function () {
    // Preparar
    $user = User::factory()->create();
    $otro = User::factory()->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->putJson('/api/v1/profile', ['email' => $otro->email]);

    // Comprobar
    $response->assertUnprocessable()->assertInvalid(['email']);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
});

test('rechaza un correo que ya usa un contacto', function () {
    // Preparar
    $user = User::factory()->create();
    $contacto = Contact::factory()->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->putJson('/api/v1/profile', ['email' => $contacto->email]);

    // Comprobar
    $response->assertUnprocessable()->assertInvalid(['email']);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
});

test('rechaza un teléfono que ya usa otro usuario', function () {
    // Preparar
    $user = User::factory()->create();
    $otro = User::factory()->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->putJson('/api/v1/profile', ['phone_number' => $otro->phone_number]);

    // Comprobar
    $response->assertUnprocessable()->assertInvalid(['phone_number']);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'phone_number' => $user->phone_number]);
});

test('rechaza un teléfono que ya usa un contacto', function () {
    // Preparar
    $user = User::factory()->create();
    $contacto = Contact::factory()->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->putJson('/api/v1/profile', ['phone_number' => $contacto->phone_number]);

    // Comprobar
    $response->assertUnprocessable()->assertInvalid(['phone_number']);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'phone_number' => $user->phone_number]);
});

test('rechaza un correo con formato inválido', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->putJson('/api/v1/profile', ['email' => 'no-es-un-correo']);

    $response->assertUnprocessable()->assertInvalid(['email']);
});
