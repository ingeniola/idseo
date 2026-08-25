<?php

declare(strict_types=1);

use App\Models\Language;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function dataForSeoLocationsFixture(string $name): array
{
    return json_decode(
        file_get_contents(base_path("tests/Fixtures/dataforseo/{$name}.json")),
        associative: true,
    );
}

function fakeLocationsAndLanguages(): void
{
    Http::fake([
        '*/serp/google/languages' => Http::response(dataForSeoLocationsFixture('languages_success'), 200),
        '*/serp/google/locations*' => Http::response(dataForSeoLocationsFixture('locations_success'), 200),
    ]);
}

test('sincroniza idiomas y ubicaciones, incluyendo parent_code aunque el hijo llegue antes que el padre', function () {
    fakeLocationsAndLanguages();

    $this->artisan('dataforseo:sync-locations')->assertExitCode(0);

    expect(Language::query()->count())->toBe(2)
        ->and(Language::query()->find('es')->language_name)->toBe('Spanish');

    expect(Location::query()->count())->toBe(4);

    $tegucigalpa = Location::query()->find(1012678);
    $franciscoMorazan = Location::query()->find(20174);
    $honduras = Location::query()->find(2340);
    $unitedStates = Location::query()->find(2840);

    expect($tegucigalpa->parent_code)->toBe(20174)
        ->and($tegucigalpa->location_name_canonical)->toBe($tegucigalpa->location_name)
        ->and($franciscoMorazan->parent_code)->toBe(2340)
        ->and($honduras->parent_code)->toBeNull()
        ->and($unitedStates->country_iso_code)->toBe('US');
});

test('correr el comando dos veces no duplica filas (upsert)', function () {
    fakeLocationsAndLanguages();

    $this->artisan('dataforseo:sync-locations')->assertExitCode(0);
    $this->artisan('dataforseo:sync-locations')->assertExitCode(0);

    expect(Location::query()->count())->toBe(4)
        ->and(Language::query()->count())->toBe(2);
});

test('el flag --country filtra el endpoint de ubicaciones', function () {
    Http::fake([
        '*/serp/google/languages' => Http::response(dataForSeoLocationsFixture('languages_success'), 200),
        '*/serp/google/locations/HN' => Http::response(dataForSeoLocationsFixture('locations_success'), 200),
    ]);

    $this->artisan('dataforseo:sync-locations', ['--country' => 'HN'])->assertExitCode(0);

    Http::assertSent(fn (Request $request) => str_contains((string) $request->url(), '/serp/google/locations/HN'));
});
