@extends('layouts.crew', ['title' => $trainingCourse->title])

@section('content')
<div class="page-heading"><div><div class="crew-hub-brand"><img src="{{ asset('images/brand/dancepro-icon-blue.png') }}" alt=""><span>CREW HUB</span></div><h1>{{ $trainingCourse->title }}</h1><p class="muted">{{ $trainingCourse->description }}</p></div><a class="button secondary" href="{{ route('crew.training.index') }}">Back to My Training</a></div>
@if($enrolment->status === 'completed')<div class="notice" style="margin:16px 0">✓ Completed {{ $enrolment->completed_at->format('j F Y, g:i a') }}. This record remains in your training history.</div>@endif

@foreach($trainingCourse->sections as $section)
<section class="grid training-course-section">
    <div><span class="type-label">SECTION {{ $loop->iteration }}</span><h2>{{ $section->title }}</h2>@if($section->description)<p class="muted">{{ $section->description }}</p>@endif</div>
    @foreach($section->modules as $module)
        @php($progress=$enrolment->moduleProgress->firstWhere('training_module_id',$module->id))
        @php($settings=$module->settings ?? [])
        @php($isComplete=filled(optional($progress)->completed_at))
        @php($latestAttempt=optional($assessmentAttempts->get($module->id))->first())
        @php($assessment=data_get($settings,'assessment',[]))
        @php($maxAttempts=data_get($assessment,'max_attempts'))
        @php($attemptsExhausted=$module->module_type==='assessment' && $maxAttempts && optional($progress)->attempts >= $maxAttempts)
        <article class="card">
            <div class="section-heading"><div><span class="type-label">{{ str($module->module_type)->replace('_', ' ')->title() }}</span><h3>{{ $module->title }}</h3></div>@if($isComplete)<span class="status-pill done">✓ Complete</span>@endif</div>
            @if($module->content)<p style="white-space:pre-wrap">{{ $module->content }}</p>@endif
            @if(in_array($module->module_type, ['image','labelled_image']) && data_get($settings,'media_url'))<img src="{{ data_get($settings,'media_url') }}" alt="{{ $module->title }}" style="display:block;max-width:100%;border-radius:10px">@endif
            @if($module->module_type === 'gallery')<div class="training-gallery">@foreach(data_get($settings,'items',[]) as $url)<img src="{{ $url }}" alt="{{ $module->title }}">@endforeach</div>@endif
            @if($module->module_type === 'audio' && data_get($settings,'media_url'))<audio controls style="width:100%"><source src="{{ data_get($settings,'media_url') }}"></audio>@endif
            @if($module->module_type === 'embed' && data_get($settings,'media_url'))<iframe src="{{ data_get($settings,'media_url') }}" title="{{ $module->title }}" sandbox="allow-scripts allow-same-origin allow-presentation" loading="lazy" style="width:100%;min-height:360px;border:0;border-radius:10px"></iframe>@endif
            @if(in_array($module->module_type,['process','accordion','flashcards']))<ol>@foreach(data_get($settings,'items',[]) as $item)<li>{{ $item }}</li>@endforeach</ol>@endif
            @if($module->module_type === 'callout')<div class="notice">{{ $module->content }}</div>@endif
            @if(in_array($module->module_type,['video','file','external_link']) && ($url=data_get($settings,'media_url',$module->video_url)))<p><a class="button secondary" href="{{ $url }}" target="_blank" rel="noopener">{{ data_get($settings,'button_label') ?: match($module->module_type){'video'=>'Watch video ↗','file'=>'Open file ↗',default=>'Open link ↗'} }}</a></p>@endif
            @if($module->module_type === 'assessment')
                <p class="muted">Pass mark: {{ data_get($assessment,'pass_mark',80) }}% · {{ $maxAttempts ? $maxAttempts.' attempts allowed' : 'Unlimited attempts' }}@if(optional($progress)->attempts) · {{ $progress->attempts }} attempted @endif</p>
                @if($latestAttempt && data_get($assessment,'show_feedback'))
                    <div class="assessment-feedback"><strong>Latest score: {{ number_format((float)$latestAttempt->score_percent, 0) }}%</strong>
                        @foreach($latestAttempt->results as $result)<p class="{{ $result['correct'] ? 'correct' : 'incorrect' }}">{{ $result['correct'] ? '✓ Correct' : '✕ Review this answer' }}@if($result['feedback']) — {{ $result['feedback'] }}@endif</p>@endforeach
                    </div>
                @endif
            @endif
            @unless($isComplete)
                @if($attemptsExhausted)<div class="notice">You have used all available attempts. Ask your team leader or administrator for help.</div>@else
                <form method="POST" action="{{ route('crew.training.modules.complete', [$trainingCourse, $module]) }}" class="grid training-assessment-form">@csrf
                    @if($module->module_type === 'quiz')
                        <h3>{{ $module->quiz_question }}</h3>
                        @foreach($module->quiz_options as $optionIndex => $option)
                            <label style="display:flex;flex-direction:row;align-items:center"><input type="radio" name="selected_option" value="{{ $optionIndex }}" required style="width:auto;min-height:0"> {{ $option }}</label>
                        @endforeach
                    @endif
                    @if($module->module_type === 'assessment')
                        @foreach(data_get($assessment,'questions',[]) as $questionIndex=>$question)
                            <fieldset class="assessment-question"><legend><strong>{{ $loop->iteration }}. {{ $question['prompt'] }}</strong> <span class="muted">({{ $question['points'] }} {{ Str::plural('point',$question['points']) }})</span></legend>
                                @if($question['type']==='single_choice')
                                    @foreach($question['options'] as $optionIndex=>$option)<label><input type="radio" name="answers[{{ $questionIndex }}]" value="{{ $optionIndex }}" required> {{ $option }}</label>@endforeach
                                @elseif($question['type']==='multiple_choice')
                                    @foreach($question['options'] as $optionIndex=>$option)<label><input type="checkbox" name="answers[{{ $questionIndex }}][]" value="{{ $optionIndex }}"> {{ $option }}</label>@endforeach
                                @elseif($question['type']==='true_false')
                                    <label><input type="radio" name="answers[{{ $questionIndex }}]" value="true" required> True</label><label><input type="radio" name="answers[{{ $questionIndex }}]" value="false" required> False</label>
                                @elseif($question['type']==='number')
                                    <input type="number" step="any" name="answers[{{ $questionIndex }}]" required>
                                @elseif($question['type']==='ordering')
                                    @foreach($question['options'] as $position=>$unused)<label>Position {{ $position+1 }}<select name="answers[{{ $questionIndex }}][]" required><option value="">Choose…</option>@foreach($question['options'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select></label>@endforeach
                                @else
                                    <input name="answers[{{ $questionIndex }}]" required>
                                @endif
                            </fieldset>
                        @endforeach
                    @endif
                    <button>{{ in_array($module->module_type,['quiz','assessment']) ? 'Submit answers' : data_get($settings,'confirmation_text','Mark complete') }}</button>
                </form>
                @endif
            @endunless
        </article>
    @endforeach
</section>
@endforeach
@endsection

@push('styles')
<style>.training-course-section{margin-top:28px}.training-gallery{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}.training-gallery img{width:100%;height:180px;object-fit:cover;border-radius:10px}.assessment-question{display:grid;gap:8px;margin:0;padding:14px;border:1px solid var(--line);border-radius:7px}.assessment-question label{display:flex;flex-direction:row;align-items:center;gap:7px}.assessment-question input[type=radio],.assessment-question input[type=checkbox]{width:auto;min-height:0}.assessment-question select{min-height:36px;border:1px solid var(--line);border-radius:5px}.assessment-feedback{margin:12px 0;padding:12px;border-radius:7px;background:#f2f7f9}.assessment-feedback p{margin:6px 0 0}.assessment-feedback .correct{color:var(--green)}.assessment-feedback .incorrect{color:var(--amber)}</style>
@endpush
