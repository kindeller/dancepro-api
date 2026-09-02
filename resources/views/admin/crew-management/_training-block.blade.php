@php
    $type = data_get($block, 'module_type', 'text') === 'lesson' ? 'text' : data_get($block, 'module_type', 'text');
    $settings = data_get($block, 'settings', []) ?: [];
    $itemsText = data_get($block, 'items_text', collect(data_get($settings, 'items', []))->join("\n"));
    $assessment = data_get($settings, 'assessment', []);
    $questions = data_get($block, 'questions', data_get($assessment, 'questions', []));
@endphp
<article class="training-block card card-pad grid" draggable="true">
    <div class="toolbar"><strong><span class="drag-handle" title="Drag to reorder">⋮⋮</span> Content block</strong><button type="button" class="secondary remove-block">Remove</button></div>
    <div class="grid two-col">
        <label>Block title<input data-block-field="title" value="{{ data_get($block, 'title') }}" required></label>
        <label>Block type<select class="block-type" data-block-field="module_type">@foreach(['text'=>'Formatted text','image'=>'Image','gallery'=>'Image gallery','video'=>'Video','audio'=>'Audio','file'=>'PDF or file','embed'=>'Embedded content','callout'=>'Callout','process'=>'Step-by-step process','accordion'=>'Accordion','flashcards'=>'Flashcards','labelled_image'=>'Labelled image','scenario'=>'Scenario','confirmation'=>'Confirmation','external_link'=>'External link','quiz'=>'Quick quiz question','assessment'=>'Assessment'] as $value=>$label)<option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>@endforeach</select></label>
    </div>
    <label>Text or supporting information<textarea data-block-field="content">{{ data_get($block, 'content') }}</textarea></label>
    <div class="media-fields grid two-col"><label>Media, file or destination URL<input type="url" data-block-field="media_url" value="{{ data_get($settings, 'media_url') ?: data_get($block, 'video_url') }}" placeholder="https://..."></label><label>Button label <span class="muted">(optional)</span><input data-block-field="button_label" value="{{ data_get($settings, 'button_label') }}"></label></div>
    <label class="items-fields">Items <span class="muted">(one per line)</span><textarea data-block-field="items_text">{{ $itemsText }}</textarea></label>
    <label class="confirmation-fields">Confirmation wording<input data-block-field="confirmation_text" value="{{ data_get($settings, 'confirmation_text') }}" placeholder="I confirm I have completed this step"></label>
    <div class="quiz-fields grid"><label>Question<textarea data-block-field="quiz_question">{{ data_get($block, 'quiz_question') }}</textarea></label><div class="grid two-col"><label>Answer options <span class="muted">(one per line)</span><textarea data-block-field="quiz_options_text">{{ data_get($block, 'quiz_options_text', collect(data_get($block, 'quiz_options', []))->join("\n")) }}</textarea></label><label>Correct answer number <span class="muted">(first answer is 1)</span><input class="correct-option-display" type="number" min="1" max="10" value="{{ data_get($block, 'correct_option') !== null ? data_get($block, 'correct_option') + 1 : 1 }}"><input type="hidden" data-block-field="correct_option" value="{{ data_get($block, 'correct_option', 0) }}"></label></div></div>
    <div class="assessment-fields grid">
        <div class="grid two-col"><label>Pass mark (%)<input type="number" min="1" max="100" data-assessment-field="pass_mark" value="{{ data_get($block, 'pass_mark', data_get($assessment, 'pass_mark', 80)) }}"></label><label>Maximum attempts <span class="muted">(blank means unlimited)</span><input type="number" min="1" max="100" data-assessment-field="max_attempts" value="{{ data_get($block, 'max_attempts', data_get($assessment, 'max_attempts')) }}"></label></div>
        <label style="display:flex;flex-direction:row;align-items:center;gap:8px"><input type="hidden" data-assessment-field="show_feedback" value="0"><input type="checkbox" class="show-feedback" value="1" @checked(data_get($block, 'show_feedback', data_get($assessment, 'show_feedback', true))) style="width:auto;min-height:0"> Show answer feedback after each attempt</label>
        <div class="assessment-questions grid">@foreach($questions as $question) @include('admin.crew-management._training-question', compact('question')) @endforeach</div>
        <button type="button" class="secondary add-question">Add question</button>
    </div>
</article>
