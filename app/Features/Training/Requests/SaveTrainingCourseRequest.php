<?php

namespace App\Features\Training\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveTrainingCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCrew') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $sections = $this->input('sections');
        if (! is_array($sections)) {
            $sections = [['title' => 'Course content', 'blocks' => $this->input('modules', [])]];
        }

        $sections = collect($sections)->filter(fn ($section) => is_array($section) && filled($section['title'] ?? null))
            ->values()->map(function (array $section, int $sectionIndex): array {
                $section['sort_order'] = $sectionIndex;
                $section['blocks'] = collect($section['blocks'] ?? [])->filter(fn ($block) => is_array($block) && filled($block['title'] ?? null))
                    ->values()->map(fn (array $block, int $blockIndex): array => $this->normaliseBlock($block, $blockIndex))->all();

                return $section;
            })->all();

        $this->merge([
            'is_required' => $this->boolean('is_required'),
            'grants_role_qualification' => $this->boolean('grants_role_qualification'),
            'sections' => $sections,
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'crew_role_id' => ['nullable', 'integer', 'exists:crew_roles,id'],
            'renewal_of_course_id' => ['nullable', 'integer', 'exists:training_courses,id'],
            'estimated_minutes' => ['nullable', 'integer', 'between:1,10000'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'is_required' => ['required', 'boolean'],
            'grants_role_qualification' => ['required', 'boolean'],
            'sections' => ['required', 'array', 'min:1', 'max:50'],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.description' => ['nullable', 'string', 'max:5000'],
            'sections.*.sort_order' => ['required', 'integer', 'min:0'],
            'sections.*.blocks' => ['required', 'array', 'min:1', 'max:100'],
            'sections.*.blocks.*.title' => ['required', 'string', 'max:255'],
            'sections.*.blocks.*.module_type' => ['required', Rule::in(self::blockTypes())],
            'sections.*.blocks.*.content' => ['nullable', 'string', 'max:50000'],
            'sections.*.blocks.*.video_url' => ['nullable', 'url', 'max:2000'],
            'sections.*.blocks.*.settings' => ['nullable', 'array'],
            'sections.*.blocks.*.settings.media_url' => ['nullable', 'url', 'max:2000'],
            'sections.*.blocks.*.settings.button_label' => ['nullable', 'string', 'max:255'],
            'sections.*.blocks.*.settings.confirmation_text' => ['nullable', 'string', 'max:500'],
            'sections.*.blocks.*.settings.items' => ['nullable', 'array', 'max:100'],
            'sections.*.blocks.*.settings.items.*' => ['string', 'max:2000'],
            'sections.*.blocks.*.settings.assessment' => ['nullable', 'array'],
            'sections.*.blocks.*.settings.assessment.pass_mark' => ['nullable', 'integer', 'between:1,100'],
            'sections.*.blocks.*.settings.assessment.max_attempts' => ['nullable', 'integer', 'between:1,100'],
            'sections.*.blocks.*.settings.assessment.show_feedback' => ['nullable', 'boolean'],
            'sections.*.blocks.*.settings.assessment.questions' => ['nullable', 'array', 'min:1', 'max:100'],
            'sections.*.blocks.*.settings.assessment.questions.*.prompt' => ['required', 'string', 'max:5000'],
            'sections.*.blocks.*.settings.assessment.questions.*.type' => ['required', Rule::in(['single_choice', 'multiple_choice', 'true_false', 'short_answer', 'number', 'ordering'])],
            'sections.*.blocks.*.settings.assessment.questions.*.options' => ['nullable', 'array', 'max:20'],
            'sections.*.blocks.*.settings.assessment.questions.*.options.*' => ['string', 'max:1000'],
            'sections.*.blocks.*.settings.assessment.questions.*.correct_answer' => ['nullable'],
            'sections.*.blocks.*.settings.assessment.questions.*.correct_answers' => ['nullable', 'array'],
            'sections.*.blocks.*.settings.assessment.questions.*.points' => ['required', 'integer', 'between:1,100'],
            'sections.*.blocks.*.settings.assessment.questions.*.feedback' => ['nullable', 'string', 'max:5000'],
            'sections.*.blocks.*.quiz_question' => ['nullable', 'string', 'max:5000'],
            'sections.*.blocks.*.quiz_options' => ['nullable', 'array', 'min:2', 'max:10'],
            'sections.*.blocks.*.quiz_options.*' => ['string', 'max:1000'],
            'sections.*.blocks.*.correct_option' => ['nullable', 'integer', 'min:0', 'max:9'],
            'sections.*.blocks.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->boolean('grants_role_qualification') && ! $this->filled('crew_role_id')) {
                $validator->errors()->add('grants_role_qualification', 'Select a crew role before enabling automatic qualification.');
            }
            foreach ($this->input('sections', []) as $sectionIndex => $section) {
                foreach ($section['blocks'] ?? [] as $blockIndex => $block) {
                    $path = "sections.{$sectionIndex}.blocks.{$blockIndex}";
                    if (($block['module_type'] ?? null) === 'quiz' && blank($block['quiz_question'] ?? null)) {
                        $validator->errors()->add("{$path}.quiz_question", 'A quiz question is required.');
                    }
                    if (($block['module_type'] ?? null) === 'quiz' && count($block['quiz_options'] ?? []) < 2) {
                        $validator->errors()->add("{$path}.quiz_options", 'At least two answer options are required.');
                    }
                    if (($block['module_type'] ?? null) === 'quiz' && ($block['correct_option'] ?? 0) >= count($block['quiz_options'] ?? [])) {
                        $validator->errors()->add("{$path}.correct_option", 'The correct answer must match one of the supplied options.');
                    }
                    if (($block['module_type'] ?? null) === 'assessment' && count(data_get($block, 'settings.assessment.questions', [])) === 0) {
                        $validator->errors()->add("{$path}.questions", 'Add at least one assessment question.');
                    }
                    foreach (data_get($block, 'settings.assessment.questions', []) as $questionIndex => $question) {
                        $questionPath = "{$path}.questions.{$questionIndex}";
                        if (in_array($question['type'], ['single_choice', 'multiple_choice', 'ordering'], true) && count($question['options'] ?? []) < 2) {
                            $validator->errors()->add("{$questionPath}.options", 'This answer type needs at least two options.');
                        }
                        if ($question['type'] === 'single_choice' && ($question['correct_answer'] ?? 0) >= count($question['options'] ?? [])) {
                            $validator->errors()->add("{$questionPath}.correct_answer", 'The correct answer must match one of the options.');
                        }
                    }
                }
            }
        }];
    }

    private function normaliseBlock(array $block, int $index): array
    {
        $type = $block['module_type'] ?? 'text';
        $block['module_type'] = $type === 'lesson' ? 'text' : $type;
        $block['sort_order'] = $index;
        $items = collect(preg_split('/\R/', (string) ($block['items_text'] ?? '')))->map(fn ($item) => trim($item))->filter()->values()->all();
        $assessment = null;
        if ($block['module_type'] === 'assessment') {
            $assessment = [
                'pass_mark' => (int) ($block['pass_mark'] ?? 80),
                'max_attempts' => filled($block['max_attempts'] ?? null) ? (int) $block['max_attempts'] : null,
                'show_feedback' => filter_var($block['show_feedback'] ?? false, FILTER_VALIDATE_BOOL),
                'questions' => collect($block['questions'] ?? [])->filter(fn ($question) => is_array($question) && filled($question['prompt'] ?? null))->values()->map(fn (array $question) => $this->normaliseQuestion($question))->all(),
            ];
        }
        $block['settings'] = array_filter([
            'media_url' => $block['media_url'] ?? null,
            'button_label' => $block['button_label'] ?? null,
            'confirmation_text' => $block['confirmation_text'] ?? null,
            'items' => $items,
            'assessment' => $assessment,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
        if ($block['module_type'] === 'quiz') {
            $block['quiz_options'] = collect(preg_split('/\R/', (string) ($block['quiz_options_text'] ?? '')))->map(fn ($option) => trim($option))->filter()->values()->all();
        } else {
            unset($block['quiz_question'], $block['quiz_options'], $block['correct_option']);
        }
        unset($block['items_text'], $block['media_url'], $block['button_label'], $block['confirmation_text'], $block['quiz_options_text'], $block['correct_option_display'], $block['pass_mark'], $block['max_attempts'], $block['show_feedback'], $block['questions']);

        return $block;
    }

    public static function blockTypes(): array
    {
        return ['text', 'image', 'gallery', 'video', 'audio', 'file', 'embed', 'callout', 'process', 'accordion', 'flashcards', 'labelled_image', 'scenario', 'confirmation', 'external_link', 'quiz', 'assessment'];
    }

    private function normaliseQuestion(array $question): array
    {
        $type = $question['type'] ?? 'single_choice';
        $options = collect(preg_split('/\R/', (string) ($question['options_text'] ?? '')))->map(fn ($option) => trim($option))->filter()->values()->all();
        $normalised = ['prompt' => $question['prompt'], 'type' => $type, 'options' => $options, 'points' => max(1, (int) ($question['points'] ?? 1)), 'feedback' => $question['feedback'] ?? null];
        if ($type === 'multiple_choice') {
            $normalised['correct_answers'] = collect(preg_split('/[,\s]+/', (string) ($question['correct_answers_text'] ?? '')))->filter()->map(fn ($number) => max(0, (int) $number - 1))->unique()->values()->all();
        } elseif ($type === 'ordering') {
            $normalised['correct_answer'] = null;
        } elseif ($type === 'single_choice') {
            $normalised['correct_answer'] = max(0, (int) ($question['correct_answer'] ?? 1) - 1);
        } else {
            $normalised['correct_answer'] = (string) ($question['correct_answer_value'] ?? $question['correct_answer'] ?? '');
        }

        return $normalised;
    }
}
