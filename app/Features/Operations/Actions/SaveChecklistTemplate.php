<?php

namespace App\Features\Operations\Actions;

use App\Features\Operations\Models\ChecklistTemplate;
use Illuminate\Support\Facades\DB;

class SaveChecklistTemplate
{
    public function execute(array $data, ?ChecklistTemplate $template = null): ChecklistTemplate
    {
        return DB::transaction(function () use ($data, $template): ChecklistTemplate {
            $template ??= new ChecklistTemplate;
            $template->fill(collect($data)->except(['item_lines', 'items'])->all())->save();
            $template->items()->delete();
            foreach ($data['item_lines'] as $index => $instruction) {
                $template->items()->create(['instruction' => $instruction, 'sort_order' => $index]);
            }

            return $template->refresh()->load('items');
        });
    }
}
