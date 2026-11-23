<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Delete a master record (product, customer, warehouse ...). When it is
 * already used by orders, stock or vouchers the database refuses; the user
 * then gets a plain message instead of an error page.
 */
trait DeletesSafely
{
    protected function deleteSafely(Model $record): bool
    {
        try {
            $record->delete();
        } catch (QueryException) {
            session()->flash('error', __('This record is already used in transactions, so it cannot be deleted. Mark it inactive instead.'));

            return false;
        }

        return true;
    }
}
