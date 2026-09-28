<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CsvImportController extends Controller
{
    public function store(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'], // 10MB max
            'list_id' => ['nullable', 'exists:contact_lists,id'],
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();
        
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle); // Assuming first row is header
        
        if (!$header) {
            return redirect()->back()->with('error', 'Invalid CSV file format.');
        }

        // Map columns based on standard names
        $emailIdx = $this->getColumnIndex($header, ['email', 'email address', 'e-mail']);
        if ($emailIdx === false) {
            return redirect()->back()->with('error', 'CSV must contain an "email" column.');
        }
        
        $firstNameIdx = $this->getColumnIndex($header, ['first name', 'firstname', 'first_name', 'name', 'first']);
        $lastNameIdx = $this->getColumnIndex($header, ['last name', 'lastname', 'last_name', 'last']);

        $tenantId = \App\Tenancy\TenantContext::id();
        $listId = $request->input('list_id');
        $now = now();
        
        $imported = 0;
        $duplicates = 0;
        
        \Illuminate\Support\Facades\DB::beginTransaction();
        
        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (!isset($row[$emailIdx]) || empty(trim($row[$emailIdx]))) {
                    continue;
                }
                
                $email = strtolower(trim($row[$emailIdx]));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue; // Skip invalid emails
                }
                
                // Live MX Validation
                $domain = substr(strrchr($email, "@"), 1);
                if (!checkdnsrr($domain, 'MX')) {
                    continue; // Skip emails with invalid domains (Bounce Shield)
                }

                // Fast insert or ignore (for duplicates)
                // We use firstOrCreate so we can attach to list if it already exists
                $contact = \App\Models\Contact::firstOrCreate(
                    ['tenant_id' => $tenantId, 'email' => $email],
                    [
                        'first_name' => $firstNameIdx !== false && isset($row[$firstNameIdx]) ? trim($row[$firstNameIdx]) : null,
                        'last_name' => $lastNameIdx !== false && isset($row[$lastNameIdx]) ? trim($row[$lastNameIdx]) : null,
                        'status' => 'active',
                    ]
                );
                
                if ($contact->wasRecentlyCreated) {
                    $imported++;
                } else {
                    $duplicates++;
                }
                
                if ($listId) {
                    // Sync without detaching to safely add to list
                    $contact->lists()->syncWithoutDetaching([$listId]);
                }
            }
            
            \Illuminate\Support\Facades\DB::commit();
            fclose($handle);
            
            return redirect()->back()->with('success', "Import complete! Added $imported new contacts. (Skipped $duplicates duplicates).");
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            fclose($handle);
            // Log full exception internally; never expose raw errors to users
            \Illuminate\Support\Facades\Log::error('CSV import failed', [
                'user_id' => auth()->id(),
                'list_id' => $listId,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'Import failed. Please check the file and try again.');
        }
    }
    
    private function getColumnIndex(array $header, array $possibleNames)
    {
        foreach ($header as $index => $colName) {
            if (in_array(strtolower(trim($colName)), $possibleNames)) {
                return $index;
            }
        }
        return false;
    }
}
