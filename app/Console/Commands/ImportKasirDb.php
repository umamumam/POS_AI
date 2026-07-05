<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ImportKasirDb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:import-kasir {file=c:\Users\ACER\Downloads\tokk2289_kasir.sql}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import MySQL kasir dump file into local SQLite database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File SQL dump tidak ditemukan di: {$filePath}");
            return Command::FAILURE;
        }

        $this->info("Memulai proses impor dari {$filePath}...");

        // Disable foreign key checks for SQLite
        DB::statement('PRAGMA foreign_keys = OFF;');

        // Open the SQL file
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("Gagal membuka file SQL dump.");
            return Command::FAILURE;
        }

        // Count lines to show progress bar (approximate or precise)
        $this->info("Menghitung total baris SQL...");
        $lineCount = 0;
        while (!feof($handle)) {
            fgets($handle);
            $lineCount++;
        }
        rewind($handle);

        $bar = $this->output->createProgressBar($lineCount);
        $bar->start();

        // We wrap everything in a transaction for extreme performance in SQLite
        DB::beginTransaction();

        try {
            $queryBuffer = '';
            while (($line = fgets($handle)) !== false) {
                $bar->advance();
                
                // Trim line and check if it's an INSERT statement we care about
                $trimmed = trim($line);
                
                if (empty($trimmed) || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                    continue;
                }

                // Append line to buffer
                $queryBuffer .= $line;

                // If line ends with semicolon, execute it
                if (str_ends_with(trim($line), ';')) {
                    $query = trim($queryBuffer);
                    
                    // Check if it's an insert statement for target tables
                    if (preg_match('/^INSERT INTO `(kategoris|produks|transaksis|detail_transaksis|users)`/i', $query)) {
                        
                        // Execute the raw query
                        DB::unprepared($query);
                    }
                    
                    $queryBuffer = '';
                }
            }

            // Commit the transaction
            DB::commit();
            $bar->finish();
            $this->newLine();
            $this->info("Data berhasil diimpor!");

            // Update user password to 'password' for testing
            $updated = DB::table('users')->where('id', 1)->update([
                'password' => Hash::make('password'),
                'email_verified_at' => now(), // verified
            ]);

            if ($updated) {
                $this->info("Akun user 'umam@gmail.com' berhasil diaktifkan dengan password: 'password'");
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->newLine();
            $this->error("Terjadi kesalahan saat impor data: " . $e->getMessage());
            fclose($handle);
            DB::statement('PRAGMA foreign_keys = ON;');
            return Command::FAILURE;
        }

        fclose($handle);

        // Re-enable foreign key checks
        DB::statement('PRAGMA foreign_keys = ON;');

        $this->info("Proses impor selesai dengan sukses!");
        return Command::SUCCESS;
    }
}
