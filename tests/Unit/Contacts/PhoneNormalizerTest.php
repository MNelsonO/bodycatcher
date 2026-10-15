<?php

use App\Contacts\InvalidPhoneNumber;
use App\Contacts\PhoneNormalizer;

it('normalizes Kenyan local numbers to E.164', function () {
    $phones = app(PhoneNormalizer::class);

    expect($phones->normalize('0712 345 678'))->toBe('+254712345678')
        ->and($phones->normalize('712345678'))->toBe('+254712345678');
});

it('accepts international E.164 numbers', function () {
    $phones = app(PhoneNormalizer::class);

    expect($phones->normalize('+256772100200'))->toBe('+256772100200')
        ->and($phones->normalize('+2348012345678'))->toBe('+2348012345678')
        ->and($phones->normalize('256772100200'))->toBe('+256772100200');
});

it('rejects empty input', function () {
    app(PhoneNormalizer::class)->normalize('   ');
})->throws(InvalidPhoneNumber::class);
