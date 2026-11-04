<?php

use App\Contacts\Contact;
use Illuminate\Http\UploadedFile;

function unnamedContact(string $phone): Contact
{
    return Contact::factory()->create(['full_name' => $phone, 'primary_phone' => $phone, 'business_name' => null]);
}

it('names contacts from an Android vCard export, including quoted-printable names and photos', function () {
    $this->actingAs(crmSalesUser());
    $grace = unnamedContact('+254711000111');
    $otieno = unnamedContact('+254733000222');
    $named = Contact::factory()->create(['full_name' => 'Mama Njeri', 'primary_phone' => '+254722100201']);

    $vcf = implode("\r\n", [
        'BEGIN:VCARD',
        'VERSION:2.1',
        'N:Achieng;Grace;;;',
        'FN:Grace Achieng',
        'TEL;CELL:0711 000 111',
        'PHOTO;ENCODING=BASE64;JPEG:/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4n==',
        'END:VCARD',
        'BEGIN:VCARD',
        'VERSION:2.1',
        'FN;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:Otieno =48ardware =',
        '=E2=9C=93',
        'ORG:Otieno Hardware Ltd',
        'TEL;CELL:+254 733 000 222',
        'END:VCARD',
        'BEGIN:VCARD',
        'VERSION:3.0',
        'FN:Njeri From Phone',
        'TEL;TYPE=CELL:+254722100201',
        'END:VCARD',
        'BEGIN:VCARD',
        'VERSION:3.0',
        'FN:Not In Desk',
        'TEL:+254799999999',
        'END:VCARD',
    ]);

    $this->post(route('contacts.import-names'), [
        'file' => UploadedFile::fake()->createWithContent('contacts.vcf', $vcf),
    ])->assertRedirect(route('contacts.index'))->assertSessionHasNoErrors();

    expect($grace->refresh()->full_name)->toBe('Grace Achieng')
        ->and($otieno->refresh()->full_name)->toBe('Otieno Hardware ✓')
        ->and($otieno->business_name)->toBe('Otieno Hardware Ltd')
        ->and($named->refresh()->full_name)->toBe('Mama Njeri');
});

it('names contacts from a Google Contacts CSV export', function () {
    $this->actingAs(crmSalesUser());
    $brian = unnamedContact('+254711222333');
    $wa = Contact::factory()->create(['full_name' => 'WhatsApp +254744555666', 'primary_phone' => '+254744555666']);

    $csv = "\xEF\xBB\xBF".implode("\n", [
        'First Name,Middle Name,Last Name,Organization Name,Phone 1 - Label,Phone 1 - Value,Phone 2 - Label,Phone 2 - Value',
        'Brian,,Otieno,Sparkle Laundry,Mobile,+254 711 222 333,,',
        'Wanjiru,,Kamau,,Work,0700 000 001 ::: 0744 555 666,,',
    ]);

    $this->post(route('contacts.import-names'), [
        'file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv),
    ])->assertRedirect(route('contacts.index'));

    expect($brian->refresh()->full_name)->toBe('Brian Otieno')
        ->and($brian->business_name)->toBe('Sparkle Laundry')
        ->and($wa->refresh()->full_name)->toBe('Wanjiru Kamau');
});

it('rejects files that are not contact exports', function () {
    $this->actingAs(crmSalesUser());

    $this->post(route('contacts.import-names'), [
        'file' => UploadedFile::fake()->createWithContent('photo.jpg', 'not contacts'),
    ])->assertSessionHasErrors('file');

    $this->post(route('contacts.import-names'), [
        'file' => UploadedFile::fake()->createWithContent('empty.csv', "Notes\nhello\n"),
    ])->assertSessionHasErrors('file');
});
