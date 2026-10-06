<?php

declare(strict_types=1);

use App\Modules\Post\Requests\PostRequest;
use Illuminate\Http\UploadedFile;
use SanderMuller\FluentValidation\Testing\FluentRulesTester;

it('is unauthorized for guests', function (): void {
    FluentRulesTester::for(PostRequest::class)
        ->with(['content' => 'Contenido de la publicación'])
        ->assertUnauthorized();
});

it('passes with valid content and no image', function (): void {
    FluentRulesTester::for(PostRequest::class)
        ->actingAs(createUser())
        ->with(['content' => 'Contenido de la publicación'])
        ->passes();
});

it('requires the content with a custom message', function (): void {
    $fluentRulesTester = FluentRulesTester::for(PostRequest::class)
        ->actingAs(createUser())
        ->with(['content' => ''])
        ->failsOnly('content', 'required');

    expect($fluentRulesTester->errors()->first('content'))->toBe('El contenido de la publicación es obligatorio');
});

it('rejects content shorter than 10 characters with a custom message', function (): void {
    $fluentRulesTester = FluentRulesTester::for(PostRequest::class)
        ->actingAs(createUser())
        ->with(['content' => 'corto'])
        ->failsOnly('content', 'min');

    expect($fluentRulesTester->errors()->first('content'))->toBe('El contenido de la publicación no debe ser menor a 10 caracteres');
});

it('accepts png, jpg and webp images', function (string $fileName): void {
    FluentRulesTester::for(PostRequest::class)
        ->actingAs(createUser())
        ->with([
            'content' => 'Contenido de la publicación',
            'image' => UploadedFile::fake()->image($fileName),
        ])
        ->passes();
})->with(['photo.png', 'photo.jpg', 'photo.webp']);

it('rejects files that are not png, jpg or webp images', function (): void {
    $fluentRulesTester = FluentRulesTester::for(PostRequest::class)
        ->actingAs(createUser())
        ->with([
            'content' => 'Contenido de la publicación',
            'image' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
        ])
        ->failsOnly('image', 'mimes');

    expect($fluentRulesTester->errors()->first('image'))->toBe('El archivo debe ser de tipo png, jpg o webp');
});

it('rejects images larger than 5MB with a custom message', function (): void {
    $fluentRulesTester = FluentRulesTester::for(PostRequest::class)
        ->actingAs(createUser())
        ->with([
            'content' => 'Contenido de la publicación',
            'image' => UploadedFile::fake()->image('photo.jpg')->size(5121),
        ])
        ->failsOnly('image', 'max');

    expect($fluentRulesTester->errors()->first('image'))->toBe('El archivo no debe ser mayor a 5MB');
});
