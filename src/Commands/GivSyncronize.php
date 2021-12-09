<?php

namespace Larapress\Giv\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Larapress\ECommerce\Models\Cart;
use Larapress\Giv\Services\GivSyncronizer;

class GivSyncronize extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lp:giv:sync {subject : one of categories,products,stock,colors,timestamp}';

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

        switch ($this->argument('subject')) {
            case 'categories':
                $syncer->syncCategories();
                $this->info('Categories sync success');
                break;
            case 'stock':
                $syncer->syncProducts(true);
                $this->info('Products sync success');
                break;
            case 'products':
                $syncer->syncProducts();
                $this->info('Products sync success');
                break;
            case 'color':
                $syncer->syncColors();
                $this->info('Colors sync success');
                break;
            case 'timestamp':
                $syncer->resetSyncTimestamps();
                $this->info('Timestamp reset success');
                break;
            default:
                $this->warn('Subject '.$this->argument('subject').' is not valid.');
        }
    }
}
