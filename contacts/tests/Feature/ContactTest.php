<?php

use App\Models\Contact;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// ---------- Sin sesión ----------

test('no lista contactos sin sesión', function () {
    $response = $this->getJson('/api/v1/contacts');

    $response->assertUnauthorized();
});

test('no crea contactos sin sesión', function () {
    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Carlos Ruiz',
        'email' => 'carlos@correo.test',
        'phone_number' => '3105551234',
    ]);

    $response->assertUnauthorized();
    $this->assertDatabaseCount('contacts', 0);
});

test('no muestra un contacto sin sesión', function () {
    $contact = Contact::factory()->create();

    $response = $this->getJson("/api/v1/contacts/{$contact->id}");

    $response->assertUnauthorized();
});

test('no actualiza un contacto sin sesión', function () {
    $contact = Contact::factory()->create(['name' => 'Nombre original']);

    $response = $this->putJson("/api/v1/contacts/{$contact->id}", [
        'name' => 'Nombre nuevo',
    ]);

    $response->assertUnauthorized();
    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'name' => 'Nombre original',
    ]);
});

test('no elimina un contacto sin sesión', function () {
    $contact = Contact::factory()->create();

    $response = $this->deleteJson("/api/v1/contacts/{$contact->id}");

    $response->assertUnauthorized();
    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'deleted_at' => null,
    ]);
});

// ---------- Listar ----------

test('lista solo los contactos del usuario autenticado', function () {
    // Preparar
    $user = User::factory()->create();
    Contact::factory(3)->for($user)->create();
    Contact::factory(2)->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->getJson('/api/v1/contacts');

    // Comprobar
    $response->assertOk()->assertJsonCount(3, 'data');
});

test('no lista los contactos eliminados', function () {
    // Preparar
    $user = User::factory()->create();
    Contact::factory(2)->for($user)->create();
    Contact::factory()->for($user)->create()->delete();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->getJson('/api/v1/contacts');

    // Comprobar
    $response->assertOk()->assertJsonCount(2, 'data');
});

// ---------- Crear ----------

test('crea un contacto válido para el usuario autenticado', function () {
    // Preparar
    $user = User::factory()->create();
    $otroUsuario = User::factory()->create();
    Sanctum::actingAs($user);

    // Actuar: el cliente intenta asignar el contacto a otro usuario
    $response = $this->postJson('/api/v1/contacts', [
        'user_id' => $otroUsuario->id,
        'name' => 'Carlos Ruiz',
        'email' => 'carlos@correo.test',
        'phone_number' => '3105551234',
    ]);

    // Comprobar
    $response->assertCreated()
        ->assertJsonPath('data.name', 'Carlos Ruiz')
        ->assertJsonPath('data.phone_number', '3105551234');

    $this->assertDatabaseHas('contacts', [
        'user_id' => $user->id,
        'name' => 'Carlos Ruiz',
        'phone_number' => '3105551234',
    ]);
});

test('devuelve los permisos de visibilidad por defecto al crear un contacto', function () {
    // Preparar
    Sanctum::actingAs(User::factory()->create());

    // Actuar
    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Carlos Ruiz',
        'email' => 'carlos@correo.test',
        'phone_number' => '3105551234',
    ]);

    // Comprobar
    $response->assertCreated()
        ->assertJsonPath('data.name_visible', true)
        ->assertJsonPath('data.address_visible', true);
});

test('no crea un contacto sin nombre', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/contacts', [
        'email' => 'carlos@correo.test',
        'phone_number' => '3105551234',
    ]);

    $response->assertUnprocessable()->assertInvalid(['name']);
    $this->assertDatabaseCount('contacts', 0);
});

test('no crea un contacto sin correo', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Carlos Ruiz',
        'phone_number' => '3105551234',
    ]);

    $response->assertUnprocessable()->assertInvalid(['email']);
    $this->assertDatabaseCount('contacts', 0);
});

test('no crea un contacto sin teléfono', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Carlos Ruiz',
        'email' => 'carlos@correo.test',
    ]);

    $response->assertUnprocessable()->assertInvalid(['phone_number']);
    $this->assertDatabaseCount('contacts', 0);
});

test('no crea un contacto con un teléfono de formato inválido', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Carlos Ruiz',
        'email' => 'carlos@correo.test',
        'phone_number' => 'abc123',
    ]);

    $response->assertUnprocessable()->assertInvalid(['phone_number']);
    $this->assertDatabaseCount('contacts', 0);
});

test('rechaza un correo que ya usa otro usuario', function () {
    // Preparar
    User::factory()->create(['email' => 'repetido@correo.test']);
    Sanctum::actingAs(User::factory()->create());

    // Actuar
    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Carlos Ruiz',
        'email' => 'repetido@correo.test',
        'phone_number' => '3105551234',
    ]);

    // Comprobar
    $response->assertUnprocessable()->assertInvalid(['email']);
    $this->assertDatabaseCount('contacts', 0);
});

test('rechaza un correo que ya usa otro contacto', function () {
    // Preparar
    Contact::factory()->create(['email' => 'repetido@correo.test']);
    Sanctum::actingAs(User::factory()->create());

    // Actuar
    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Carlos Ruiz',
        'email' => 'repetido@correo.test',
        'phone_number' => '3105551234',
    ]);

    // Comprobar: solo existe el contacto original
    $response->assertUnprocessable()->assertInvalid(['email']);
    $this->assertDatabaseCount('contacts', 1);
});

test('rechaza un teléfono que ya usa otro usuario', function () {
    // Preparar
    User::factory()->create(['phone_number' => '3001112222']);
    Sanctum::actingAs(User::factory()->create());

    // Actuar
    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Carlos Ruiz',
        'email' => 'carlos@correo.test',
        'phone_number' => '3001112222',
    ]);

    // Comprobar
    $response->assertUnprocessable()->assertInvalid(['phone_number']);
    $this->assertDatabaseCount('contacts', 0);
});

test('rechaza un teléfono que ya usa otro contacto', function () {
    // Preparar
    Contact::factory()->create(['phone_number' => '3001112222']);
    Sanctum::actingAs(User::factory()->create());

    // Actuar
    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Carlos Ruiz',
        'email' => 'carlos@correo.test',
        'phone_number' => '3001112222',
    ]);

    // Comprobar: solo existe el contacto original
    $response->assertUnprocessable()->assertInvalid(['phone_number']);
    $this->assertDatabaseCount('contacts', 1);
});

test('permite reutilizar el correo y el teléfono de un contacto eliminado', function () {
    // Preparar
    Contact::factory()->create([
        'email' => 'carlos@correo.test',
        'phone_number' => '3105551234',
    ])->delete();
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Carlos Ruiz',
        'email' => 'carlos@correo.test',
        'phone_number' => '3105551234',
    ]);

    // Comprobar
    $response->assertCreated();
    $this->assertDatabaseHas('contacts', [
        'user_id' => $user->id,
        'email' => 'carlos@correo.test',
        'deleted_at' => null,
    ]);
});

// ---------- Ver el perfil de un contacto ----------

test('muestra el perfil de un contacto propio', function () {
    // Preparar
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->getJson("/api/v1/contacts/{$contact->id}");

    // Comprobar
    $response->assertOk()
        ->assertJsonPath('data.id', $contact->id)
        ->assertJsonPath('data.name', $contact->name)
        ->assertJsonPath('data.address', $contact->address);
});

test('oculta el nombre y la dirección en el perfil si están marcados como ocultos', function () {
    // Preparar
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create([
        'name_visible' => false,
        'address_visible' => false,
    ]);
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->getJson("/api/v1/contacts/{$contact->id}");

    // Comprobar: nombre y dirección ocultos, el resto sigue visible
    $response->assertOk()
        ->assertJsonPath('data.name', null)
        ->assertJsonPath('data.address', null)
        ->assertJsonPath('data.email', $contact->email);
});

test('responde 404 al consultar un contacto eliminado', function () {
    // Preparar
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    $contact->delete();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->getJson("/api/v1/contacts/{$contact->id}");

    // Comprobar
    $response->assertNotFound();
});

test('responde 404 en JSON si el contacto no existe', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/v1/contacts/99999');

    $response->assertNotFound()
        ->assertJsonPath('message', 'Recurso no encontrado.');
});

test('no deja consultar el contacto de otro usuario', function () {
    // Preparar
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    $contact = Contact::factory()->for($owner)->create(['name' => 'Contacto privado']);
    Sanctum::actingAs($visitor);

    // Actuar
    $response = $this->getJson("/api/v1/contacts/{$contact->id}");

    // Comprobar
    $response->assertForbidden()
        ->assertJsonMissing(['name' => 'Contacto privado']);

    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'user_id' => $owner->id,
    ]);
});

// ---------- Actualizar ----------

test('actualiza un contacto propio', function () {
    // Preparar
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->putJson("/api/v1/contacts/{$contact->id}", [
        'name' => 'Nombre Nuevo',
        'address_visible' => false,
    ]);

    // Comprobar
    $response->assertOk()->assertJsonPath('data.name', 'Nombre Nuevo');

    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'name' => 'Nombre Nuevo',
        'address_visible' => false,
    ]);
});

test('permite guardar un contacto con su propio correo y teléfono', function () {
    // Preparar
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->putJson("/api/v1/contacts/{$contact->id}", [
        'email' => $contact->email,
        'phone_number' => $contact->phone_number,
    ]);

    // Comprobar
    $response->assertOk();
});

test('rechaza actualizar con el correo de otro contacto', function () {
    // Preparar
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    $otro = Contact::factory()->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->putJson("/api/v1/contacts/{$contact->id}", [
        'email' => $otro->email,
    ]);

    // Comprobar
    $response->assertUnprocessable()->assertInvalid(['email']);
    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'email' => $contact->email,
    ]);
});

test('rechaza actualizar con el teléfono de otro contacto', function () {
    // Preparar
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    $otro = Contact::factory()->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->putJson("/api/v1/contacts/{$contact->id}", [
        'phone_number' => $otro->phone_number,
    ]);

    // Comprobar
    $response->assertUnprocessable()->assertInvalid(['phone_number']);
    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'phone_number' => $contact->phone_number,
    ]);
});

test('no deja actualizar el contacto de otro usuario', function () {
    // Preparar
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    $contact = Contact::factory()->for($owner)->create(['name' => 'Nombre original']);
    Sanctum::actingAs($visitor);

    // Actuar
    $response = $this->putJson("/api/v1/contacts/{$contact->id}", [
        'name' => 'Nombre cambiado por otro',
    ]);

    // Comprobar
    $response->assertForbidden();
    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'name' => 'Nombre original',
    ]);
});

// ---------- Eliminar ----------

test('elimina un contacto propio con borrado lógico', function () {
    // Preparar
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    Sanctum::actingAs($user);

    // Actuar
    $response = $this->deleteJson("/api/v1/contacts/{$contact->id}");

    // Comprobar: la fila sigue en la base, marcada como eliminada
    $response->assertNoContent();
    $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
});

test('no deja eliminar el contacto de otro usuario', function () {
    // Preparar
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    $contact = Contact::factory()->for($owner)->create();
    Sanctum::actingAs($visitor);

    // Actuar
    $response = $this->deleteJson("/api/v1/contacts/{$contact->id}");

    // Comprobar: el contacto sigue activo
    $response->assertForbidden();
    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'deleted_at' => null,
    ]);
});
