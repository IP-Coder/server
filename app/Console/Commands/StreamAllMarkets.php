<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Symbol;
use Symfony\Component\Process\Process;

class StreamAllMarkets extends Command
{
    protected $signature = 'market:stream:all';
    protected $description = 'Split and stream all market symbols in background chunks';

    public function handle()
    {
        $allSymbols = Symbol::pluck('symbol')->toArray();
        $chunks = array_chunk($allSymbols, 15); // Max 3 streams with ~15 symbols each

        foreach ($chunks as $i => $chunk) {
            $args = [];

            foreach ($chunk as $symbol) {
                $args[] = '--symbol=OANDA:"' . $symbol . '"';
            }

            $command = array_merge(['php', 'artisan', 'market:stream'], $args);

            $this->info("Launching Stream $i: " . implode(' ', $command));

            $process = new Process($command);
            $process->setWorkingDirectory(base_path()); // ✅ necessary for Laravel to boot correctly
            $process->setTimeout(null);                // ⏳ no timeout for long run
            $process->disableOutput();                 // 📉 or remove this if debugging
            $process->start();                         // 🚀 async

            usleep(500000); // 🕐 0.5 sec between launches to avoid race conditions
        }

        $this->info("All stream processes launched.");
    }
}
