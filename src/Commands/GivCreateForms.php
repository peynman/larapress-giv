<?php

namespace Larapress\Giv\Commands;

use Illuminate\Console\Command;
use Larapress\Giv\Services\GivApi\Client;
use Larapress\Giv\Services\GivSyncronizer;
use Larapress\Profiles\Models\Form;

class GivCreateForms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lp:giv:create-forms';

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
        Form::createOrUpdate([
            'author_id' => config('larapress.giv.giv_user_form_id'),
            'name' => 'giv_user_form',
        ], [
            'data' => [
                'title' => trans('larapress::giv.giv_user_form_title'),
                'content' => [
                    'children' => [
                        'id' => 'PersionID',
                    ]
                ]
            ],
        ]);
    }
}
