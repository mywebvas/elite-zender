<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\LeadCaptureForm;
use App\Models\Role;
use App\Models\SmtpAccount;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local development data.
 *
 * Seeds **two** workspaces on purpose. A single-tenant seed makes every
 * isolation bug invisible: with only one tenant in the database, a missing
 * `where tenant_id = ?` looks exactly like a correct query.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $acme = $this->workspace('Acme Corp', 'demo@elitesender.app', withData: true);
        $rival = $this->workspace('Globex', 'rival@elitesender.app', withData: true);

        $this->command?->newLine();
        $this->command?->info('Seeded two workspaces — sign in with either:');
        $this->command?->table(
            ['Workspace', 'Email', 'Password'],
            [[$acme->name, 'demo@elitesender.app', 'password'], [$rival->name, 'rival@elitesender.app', 'password']],
        );
    }

    private function workspace(string $name, string $email, bool $withData): Tenant
    {
        $tenant = Tenant::factory()->create(['name' => $name]);

        return TenantContext::run($tenant, function () use ($tenant, $name, $email, $withData): Tenant {
            $owner = User::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => $name.' Owner',
                'email' => $email,
                'password' => Hash::make('password'),
                'role' => Role::OWNER,
            ]);

            // A viewer proves the RBAC layer is doing something at a glance.
            User::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => $name.' Analyst',
                'email' => 'viewer+'.$tenant->id.'@elitesender.app',
                'password' => Hash::make('password'),
                'role' => Role::VIEWER,
            ]);

            if (! $withData) {
                return $tenant;
            }

            $relays = SmtpAccount::factory(3)->create(['tenant_id' => $tenant->id]);

            $list = ContactList::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Newsletter subscribers',
            ]);

            $tags = collect(['vip', 'trial', 'churn-risk'])
                ->map(fn (string $tag) => Tag::create(['tenant_id' => $tenant->id, 'name' => $tag]));

            $contacts = Contact::factory(50)->create(['tenant_id' => $tenant->id]);
            $contacts->each(function (Contact $contact) use ($list, $tags): void {
                $contact->lists()->attach($list->id);
                $contact->tags()->attach($tags->random()->id);
            });

            $campaigns = Campaign::factory(5)->create([
                'tenant_id' => $tenant->id,
                'list_id' => $list->id,
                'status' => Campaign::STATUS_COMPLETED,
                'recipients_count' => $contacts->count(),
                'sent_count' => $contacts->count(),
                'started_at' => now()->subDays(3),
                'completed_at' => now()->subDays(3)->addHour(),
            ]);

            $campaigns->each(function (Campaign $campaign) use ($relays, $contacts, $tenant): void {
                $campaign->smtpAccounts()->sync($relays->pluck('id')->all());

                // Roughly a 40% open rate and 12% click rate, so the dashboard
                // shows plausible numbers rather than zeroes.
                foreach ($contacts->random(20) as $contact) {
                    CampaignEvent::create([
                        'tenant_id' => $tenant->id,
                        'campaign_id' => $campaign->id,
                        'contact_id' => $contact->id,
                        'type' => CampaignEvent::TYPE_OPEN,
                    ]);
                }

                foreach ($contacts->random(6) as $contact) {
                    CampaignEvent::create([
                        'tenant_id' => $tenant->id,
                        'campaign_id' => $campaign->id,
                        'contact_id' => $contact->id,
                        'type' => CampaignEvent::TYPE_CLICK,
                    ]);
                }
            });

            LeadCaptureForm::factory()->create([
                'tenant_id' => $tenant->id,
                'list_id' => $list->id,
                'name' => 'Website footer form',
            ]);

            return $tenant;
        });
    }
}
