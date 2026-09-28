<?php

use App\Models\Contact;
use App\Models\ContactList;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * The importer previously parsed inline, inside one transaction, running a
 * live `checkdnsrr()` MX lookup per row. A 10k-row file meant 10k blocking DNS
 * queries while holding an HTTP worker and a write lock.
 */
beforeEach(function (): void {
    $this->user = actingAsTenantUser();
    $this->list = ContactList::factory()->create(['tenant_id' => $this->user->tenant_id]);
});

function uploadCsv(string $body): UploadedFile
{
    return UploadedFile::fake()->createWithContent('contacts.csv', $body);
}

it('imports contacts and attaches them to the target list', function (): void {
    $this->post(route('contacts.import'), [
        'csv_file' => uploadCsv("Email,First Name,Last Name\nmark@example.com,Mark,Smith\nlucy@example.com,Lucy,Brown"),
        'list_id' => $this->list->id,
    ])->assertRedirect()->assertSessionHas('success');

    expect(Contact::count())->toBe(2)
        ->and($this->list->contacts()->count())->toBe(2);
});

it('de-duplicates within the file and against existing contacts', function (): void {
    Contact::factory()->create(['tenant_id' => $this->user->tenant_id, 'email' => 'dupe@example.com']);

    $this->post(route('contacts.import'), [
        'csv_file' => uploadCsv("email\ndupe@example.com\nDUPE@example.com\nnew@example.com"),
        'list_id' => $this->list->id,
    ])->assertRedirect();

    expect(Contact::count())->toBe(2);
});

it('skips malformed rows instead of aborting the import', function (): void {
    $this->post(route('contacts.import'), [
        'csv_file' => uploadCsv("email,first name\nnot-an-email,Bob\n,Empty\ngood@example.com,Good"),
    ])->assertRedirect()->assertSessionHas('success');

    expect(Contact::pluck('email')->all())->toBe(['good@example.com']);
});

it('accepts alternative header spellings', function (): void {
    $this->post(route('contacts.import'), [
        'csv_file' => uploadCsv("E-Mail,Given Name,Surname\nalt@example.com,Alt,Names"),
    ])->assertRedirect();

    $contact = Contact::sole();

    expect($contact->email)->toBe('alt@example.com')
        ->and($contact->first_name)->toBe('Alt')
        ->and($contact->last_name)->toBe('Names');
});

it('rejects a list belonging to another workspace', function (): void {
    $victim = User::factory()->create();
    $foreignList = ContactList::factory()->create(['tenant_id' => $victim->tenant_id]);

    $this->post(route('contacts.import'), [
        'csv_file' => uploadCsv("email\nx@example.com"),
        'list_id' => $foreignList->id,
    ])->assertSessionHasErrors('list_id');

    expect(Contact::withoutGlobalScopes()->count())->toBe(0);
});

it('fails cleanly when the email column is missing', function (): void {
    $this->post(route('contacts.import'), [
        'csv_file' => uploadCsv("name,company\nBob,Acme"),
    ])->assertRedirect()->assertSessionHas('import_id');

    $importId = session('import_id');

    // A malformed upload is reported through the status endpoint rather than
    // exploding in the user's face or retrying forever on the queue.
    expect(Contact::count())->toBe(0);

    $this->getJson(route('contacts.import.status', $importId))
        ->assertOk()
        ->assertJsonPath('data.state', 'failed')
        ->assertJsonPath('data.message', 'CSV must contain an "email" column.');
});

it('does not write one audit row per imported contact', function (): void {
    $this->post(route('contacts.import'), [
        'csv_file' => uploadCsv("email\na@example.com\nb@example.com\nc@example.com"),
    ])->assertRedirect();

    expect(Contact::count())->toBe(3)
        ->and(App\Models\AuditLog::where('auditable_type', Contact::class)->count())->toBe(0);
});
