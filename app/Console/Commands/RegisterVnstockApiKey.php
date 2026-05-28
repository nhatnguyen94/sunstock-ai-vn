<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class RegisterVnstockApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vnstock:register-key';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Registers the VNSTOCK_API_KEY from the .env file with the vnstock library.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $apiKey = env('VNSTOCK_API_KEY');

        if (!$apiKey) {
            $this->error('VNSTOCK_API_KEY is not set in your .env file.');
            $this->info("Please ensure 'VNSTOCK_API_KEY=your_key' is present in your .env file.");
            return 1;
        }

        $pythonExecutable = env('PYTHON_PATH', 'python');
        $this->info('Found Python executable: ' . $pythonExecutable);
        $this->info('Attempting to register API key...');

        // Pass the API key as a command-line argument
        $process = new Process([$pythonExecutable, base_path('py/register_api_key.py'), $apiKey]);
        
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('Failed to register API key.');
            $this->line('Error Output:');
            // Use utf8_decode to handle potential encoding issues from Python script on Windows
            $this->line(utf8_decode($process->getErrorOutput()));
            return 1;
        }

        $this->info('Process finished successfully!');
        $this->line(utf8_decode($process->getOutput()));
        
        return 0;
    }
}
