<?php

namespace Larapress\Giv\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Larapress\Giv\Services\GivApi\Client;
use Larapress\Giv\Services\GivSyncronizer;

class GivSyncronize extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lp:giv:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        ini_set('memory_limit', '2G');
        $syncer = new GivSyncronizer();

        // $syncer->resetSyncTimestamps();
        // dd($syncer->getSyncTimestamps());
        // $syncer->syncCategories();
        // $syncer->syncUser(User::find(1));
        $syncer->syncProducts();

        $client = new Client();
        // dd($client->getCustomersPaginated());
        // dd($client->updateCustomer(User::find(1)));
    }
}
