@php
    $questionType = data_get($question, 'type', 'single_choice');
    $questionOptions = data_get($question, 'options_text', collect(data_get($question, 'options', []))->join("\n"));
    $correctAnswer = data_get($question, 'correct_answer');
    if ($questionType === 'single_choice' && $correctAnswer !== null) $correctAnswer++;
    $correctAnswers = data_get($question, 'correct_answers_text', collect(data_get($question, 'correct_answers', []))->map(fn($answer) => $answer + 1)->join(', '));
@endphp
<div class="assessment-question card card-pad grid" draggable="true">
    <div class="toolbar"><strong><span class="question-drag-handle" title="Drag to reorder">⋮⋮</span> Assessment question</strong><button type="button" class="secondary remove-question">Remove</button></div>
    <label>Question<textarea data-question-field="prompt" required>{{ data_get($question, 'prompt') }}</textarea></label>
    <div class="grid two-col"><label>Answer type<select class="question-type" data-question-field="type">@foreach(['single_choice'=>'Single choice','multiple_choice'=>'Multiple choice','true_false'=>'True or false','short_answer'=>'Short answer','number'=>'Number','ordering'=>'Put in order'] as $value=>$label)<option value="{{ $value }}" @selected($questionType===$value)>{{ $label }}</option>@endforeach</select></label><label>Points<input type="number" min="1" max="100" data-question-field="points" value="{{ data_get($question, 'points', 1) }}" required></label></div>
    <label class="question-options">Options <span class="muted">(one per line; ordering uses this as the correct order)</span><textarea data-question-field="options_text">{{ $questionOptions }}</textarea></label>
    <label class="question-correct-single">Correct answer number <span class="muted">(first option is 1)</span><input type="number" min="1" max="20" data-question-field="correct_answer" value="{{ $correctAnswer ?? 1 }}"></label>
    <label class="question-correct-multiple">Correct answer numbers <span class="muted">(comma separated, e.g. 1, 3)</span><input data-question-field="correct_answers_text" value="{{ $correctAnswers }}"></label>
    <label class="question-correct-value">Correct answer<input data-question-field="correct_answer_value" value="{{ in_array($questionType, ['short_answer','number','true_false']) ? data_get($question, 'correct_answer') : '' }}"></label>
    <label>Answer feedback <span class="muted">(optional)</span><textarea data-question-field="feedback">{{ data_get($question, 'feedback') }}</textarea></label>
</div>
