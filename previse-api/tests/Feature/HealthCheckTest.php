<?php

test('health endpoint returns ok status', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
            'app' => 'Previse',
            'version' => '2.0.0',
        ])
        ->assertJsonStructure([
            'status',
            'app',
            'version',
            'timestamp',
        ]);
});

test('protected routes return 401 without auth', function () {
    $response = $this->getJson('/api/v1/user');

    $response->assertStatus(401);
});
