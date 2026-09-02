<?php

namespace App\Features\Training\Actions;

use App\Features\Training\Models\TrainingCourse;
use Illuminate\Support\Facades\DB;

class SaveTrainingCourse
{
    public function execute(array $data, ?TrainingCourse $course = null): TrainingCourse
    {
        return DB::transaction(function () use ($data, $course): TrainingCourse {
            $course ??= new TrainingCourse;
            $course->fill(collect($data)->except(['modules', 'sections'])->all())->save();
            $course->sections()->delete();
            foreach ($data['sections'] as $sectionData) {
                $blocks = $sectionData['blocks'];
                unset($sectionData['blocks']);
                $section = $course->sections()->create($sectionData);
                $section->modules()->createMany(collect($blocks)->map(fn (array $block) => [...$block, 'training_course_id' => $course->id])->all());
            }

            return $course->refresh()->load(['sections.modules', 'modules']);
        });
    }
}
