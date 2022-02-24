<?php

namespace Larapress\Giv\Commands;

use Illuminate\Console\Command;
use Larapress\ECommerce\Models\Cart;
use Larapress\Giv\Services\GivSyncronizer;
use Illuminate\Support\Str;

class GivSyncronize extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lp:giv:sync {subject : one of categories,products,inventory,stock,item,img,colors,cart,timestamp} {--id=} {--code=} {--cat=} {--from-start}';

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
        ini_set('max_execution_time', '0');
        $syncer = new GivSyncronizer();

        switch ($this->argument('subject')) {
            case 'item':
                $id = $this->option('id');
                if (!is_null($id)) {
                    if (Str::contains($id, ',')) {
                        $ids = explode(',', $id);
                        foreach ($ids as $itemId) {
                            $this->info('syncing product with code ' . $itemId);
                            $syncer->syncProductById($itemId, true);
                        }
                    } else {
                        $this->info('syncing product with code ' . $id);
                        $syncer->syncProductById($id, true);
                    }
                    $this->info('Item sync success');
                } else if ($this->option('code') && $this->option('cat')) {
                    $id = $this->option('code');
                    $this->info('syncing product with code ' . $id);
                    $syncer->syncProductByCode($id, $this->option('cat'), true);
                    $this->info('Item sync success');
                }
                break;
            case 'img':
                $id = $this->option('id');
                if (!is_null($id)) {
                    if (Str::contains($id, ',')) {
                        $ids = explode(',', $id);
                        $this->info('syncing product with ids in (' . implode(',', $ids) . ')');
                        foreach ($ids as $itemId) {
                            $syncer->syncProductById($itemId, false);
                        }
                    } else {
                        $syncer->syncProductById($id, false);
                    }
                } else if ($this->option('code') && $this->option('cat')) {
                    $id = $this->option('code');
                    $this->info('syncing product with code ' . $id);
                    $syncer->syncProductByCode($id, $this->option('cat'), false);
                }
                $this->info('Item Image sync success');
                break;
            case 'cart':
                $id = $this->option('id');
                if (!is_null($id)) {
                    $cart = Cart::find($id);
                    if (!is_null($cart)) {
                        $syncer->syncCart($cart);
                        $this->info('Order sync success');
                    }
                }
                break;
            case 'stock':
                // sync without images
                $syncer->syncProducts(true /* sync without images */);
                $this->info('Product Stock sync success');
                break;
            case 'products':
            $syncer->syncProducts(false /* sync with images */);
                $this->info('Products sync success');
                break;
            case 'categories':
                $syncer->syncCategories($this->option('from-start'));
                $this->info('Categories sync success');
                break;
            case 'inventory':
                $syncer->syncInventory($this->option('from-start'));
                $this->info('Products inventory sync success');
                break;
            case 'color':
                $syncer->syncColors($this->option('from-start'));
                $this->info('Colors sync success');
                break;
            case 'timestamp':
                $syncer->resetSyncTimestamps();
                $this->info('Timestamp reset success');
                break;
            default:
                $this->warn('Subject ' . $this->argument('subject') . ' is not valid.');
        }
    }
}
