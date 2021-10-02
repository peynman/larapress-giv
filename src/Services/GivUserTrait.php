<?php

namespace Larapress\Giv\Services;

use Larapress\Profiles\Models\FormEntry;

/**
 * Undocumented trait
 */
trait GivUserTrait {
    /**
     * Undocumented function
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function giv_user_form () {
        return $this->hasOne(
            FormEntry::class,
            'user_id'
        )->where('form_id', config('larapress.giv.giv_user_form_id'));
    }
}
