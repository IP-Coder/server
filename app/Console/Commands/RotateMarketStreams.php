<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Symbol;
use Symfony\Component\Process\Process;

class RotateMarketStreams extends Command
{
    protected $signature = 'market:rotate';
    protected $description = 'Rotates streaming of symbols in chunks every 10 seconds across limited WebSocket connections';

    public function handle()
    {
        $symbols = Symbol::pluck('symbol')->toArray();
        $chunks = array_chunk($symbols, 10); // 5 chunks (10 symbols each)
        $chunkCount = count($chunks);
        $connections = 3;

        $this->info("Starting rotating streams with {$chunkCount} chunks and {$connections} connections...");

        while (true) {
            for ($i = 0; $i < $connections; $i++) {
                // Figure out which chunk this connection should use
                $chunkIndex = ($i + (int)(time() / 10)) % $chunkCount;
                $chunk = $chunks[$chunkIndex];

                $args = [];
                foreach ($chunk as $symbol) {
                    $args[] = '--symbol="OANDA:' . $symbol . '"';
                }

                $command = array_merge(['php', 'artisan', 'market:stream'], $args);
                $this->info("Connection {$i} => Chunk {$chunkIndex}: " . implode(' ', $command));

                $process = new Process($command);
                $process->setWorkingDirectory(base_path());
                $process->setTimeout(10); // force stop after 10 seconds
                $process->disableOutput();
                $process->start();
            }

            sleep(10); // wait 10 seconds before rotating again
        }
    }
}