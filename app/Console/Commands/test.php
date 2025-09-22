<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Packages\Server\WebServer\WebServiceProvision;
use Illuminate\Console\Command;

class test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $server = Server::firstWhere('id', 8);

        (new WebServiceProvision($server))->provision();
    }
}
