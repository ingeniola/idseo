<?php

declare(strict_types=1);

use App\RankTracking\SerpResultParser;

test('encuentra la posicion organica del dominio rastreado por rank_group', function () {
    $parser = new SerpResultParser([
        ['type' => 'organic', 'domain' => 'competidor.com', 'rank_group' => 1, 'url' => 'https://competidor.com/a'],
        ['type' => 'organic', 'domain' => 'www.miempresa.com', 'rank_group' => 3, 'rank_absolute' => 5, 'url' => 'https://www.miempresa.com/pagina'],
    ], 'miempresa.com');

    expect($parser->findOrganicRanking())->toBe([
        'position' => 3,
        'url' => 'https://www.miempresa.com/pagina',
    ]);
});

test('hace match de subdominios contra el dominio rastreado', function () {
    $parser = new SerpResultParser([
        ['type' => 'organic', 'domain' => 'blog.miempresa.com', 'rank_group' => 7, 'url' => 'https://blog.miempresa.com/post'],
    ], 'miempresa.com');

    expect($parser->findOrganicRanking())->toBe(['position' => 7, 'url' => 'https://blog.miempresa.com/post']);
});

test('no hace match de un dominio que solo comparte sufijo sin punto', function () {
    $parser = new SerpResultParser([
        ['type' => 'organic', 'domain' => 'notmiempresa.com', 'rank_group' => 1, 'url' => 'https://notmiempresa.com'],
    ], 'miempresa.com');

    expect($parser->findOrganicRanking())->toBeNull();
});

test('devuelve null si el dominio rastreado no aparece entre los resultados', function () {
    $parser = new SerpResultParser([
        ['type' => 'organic', 'domain' => 'otro.com', 'rank_group' => 1, 'url' => 'https://otro.com'],
    ], 'miempresa.com');

    expect($parser->findOrganicRanking())->toBeNull();
});

test('ignora items que no son organic al buscar el ranking', function () {
    $parser = new SerpResultParser([
        ['type' => 'featured_snippet', 'domain' => 'miempresa.com', 'rank_group' => 1, 'url' => 'https://miempresa.com'],
    ], 'miempresa.com');

    expect($parser->findOrganicRanking())->toBeNull();
});

test('detecta features de SERP sin duplicados y excluye organic y paid', function () {
    $parser = new SerpResultParser([
        ['type' => 'organic'],
        ['type' => 'paid'],
        ['type' => 'featured_snippet'],
        ['type' => 'local_pack'],
        ['type' => 'local_pack'],
        ['type' => 'people_also_ask'],
    ], 'miempresa.com');

    expect($parser->detectedFeatures())->toEqualCanonicalizing([
        'featured_snippet',
        'local_pack',
        'people_also_ask',
    ]);
});

test('hasFeaturedSnippet y hasLocalPack reflejan los items presentes', function () {
    $parser = new SerpResultParser([
        ['type' => 'featured_snippet'],
    ], 'miempresa.com');

    expect($parser->hasFeaturedSnippet())->toBeTrue()
        ->and($parser->hasLocalPack())->toBeFalse();
});

test('con items vacios no encuentra ranking ni features', function () {
    $parser = new SerpResultParser([], 'miempresa.com');

    expect($parser->findOrganicRanking())->toBeNull()
        ->and($parser->detectedFeatures())->toBe([])
        ->and($parser->hasFeaturedSnippet())->toBeFalse()
        ->and($parser->hasLocalPack())->toBeFalse();
});
