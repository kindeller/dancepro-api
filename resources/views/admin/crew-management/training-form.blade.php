@extends('layouts.admin', ['title' => $course->exists ? 'Edit training course' : 'Add training course', 'heading' => $course->exists ? 'Edit training course' : 'Add training course', 'subheading' => 'Build short updates or complete role training with ordered sections and reusable content blocks.'])

@section('content')
@include('admin.crew-management._tabs')
<form method="POST" action="{{ $course->exists ? route('admin.training-courses.update', $course) : route('admin.training-courses.store') }}" class="grid" id="course-builder">
@csrf @if($course->exists) @method('PUT') @endif
<section class="card card-pad grid"><h2>Course details</h2><div class="grid two-col">
<label>Course name<input name="title" value="{{ old('title', $course->title) }}" required></label>
<label>For crew role<select name="crew_role_id"><option value="">All crew</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected((int) old('crew_role_id', $course->crew_role_id) === $role->id)>{{ $role->name }}</option>@endforeach</select></label>
<label>Estimated minutes<input type="number" min="1" name="estimated_minutes" value="{{ old('estimated_minutes', $course->estimated_minutes) }}"></label>
<label>Status<select name="status">@foreach(['draft'=>'Draft','published'=>'Published','archived'=>'Archived'] as $value=>$label)<option value="{{ $value }}" @selected(old('status', $course->status ?: 'draft') === $value)>{{ $label }}</option>@endforeach</select></label>
<label>Renewal of<select name="renewal_of_course_id"><option value="">Not a renewal</option>@foreach($courses as $existing)<option value="{{ $existing->id }}" @selected((int) old('renewal_of_course_id', $course->renewal_of_course_id) === $existing->id)>{{ $existing->title }}</option>@endforeach</select></label>
<label style="display:flex;flex-direction:row;align-items:center;gap:8px"><input type="checkbox" name="is_required" value="1" @checked(old('is_required', $course->is_required)) style="width:auto"> Required training</label>
<label style="display:flex;flex-direction:row;align-items:center;gap:8px"><input type="checkbox" name="grants_role_qualification" value="1" @checked(old('grants_role_qualification', $course->grants_role_qualification)) style="width:auto"> Automatically approve the selected role when completed</label>
</div><label>Description<textarea name="description">{{ old('description', $course->description) }}</textarea></label></section>
<section class="grid"><div class="toolbar"><div><h2>Course content</h2><p class="muted">Drag sections or blocks into the order crew should complete them.</p></div><button type="button" class="secondary" id="add-section">Add section</button></div>
<div id="sections" class="grid">
@php
$savedSections = $course->sections->map(fn($section) => [...$section->toArray(), 'blocks' => $section->modules->map(fn($block) => [...$block->toArray(), 'quiz_options_text' => collect($block->quiz_options)->join("\n")])->all()])->all();
$sectionRows = old('sections', $savedSections ?: [['title'=>'Course content','blocks'=>[['module_type'=>'text']]]]);
@endphp
@foreach($sectionRows as $section) @include('admin.crew-management._training-section', compact('section')) @endforeach
</div></section>
<div class="toolbar"><a class="button secondary" href="{{ route('admin.crew-management.training') }}">Cancel</a><button>Save course</button></div>
</form>
@php($templateSection = ['title' => 'New section', 'blocks' => [['module_type' => 'text']]])
@php($templateBlock = ['module_type' => 'text'])
@php($templateQuestion = ['type' => 'single_choice', 'points' => 1])
<template id="section-template">@include('admin.crew-management._training-section', ['section' => $templateSection])</template>
<template id="block-template">@include('admin.crew-management._training-block', ['block' => $templateBlock])</template>
<template id="question-template">@include('admin.crew-management._training-question', ['question' => $templateQuestion])</template>
@endsection

@push('styles')
<style>.training-section{border-left:4px solid #0aa4d8}.training-block{background:#f8fbfc}.drag-handle,.question-drag-handle{cursor:grab;font-size:20px;margin-right:8px}.training-section.dragging,.training-block.dragging,.assessment-question.dragging{opacity:.45}.add-block,.add-question{justify-self:start}.assessment-fields{padding:14px;border:1px solid #cde2eb;border-radius:7px;background:#eef8fc}.assessment-question{background:white}</style>
@endpush
@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
const sections=document.getElementById('sections'),blockTemplate=document.getElementById('block-template').innerHTML,questionTemplate=document.getElementById('question-template').innerHTML;
function updateQuestion(question){const type=question.querySelector('.question-type').value;question.querySelector('.question-options').hidden=!['single_choice','multiple_choice','ordering'].includes(type);question.querySelector('.question-correct-single').hidden=type!=='single_choice';question.querySelector('.question-correct-multiple').hidden=type!=='multiple_choice';question.querySelector('.question-correct-value').hidden=!['true_false','short_answer','number'].includes(type)}
function bindQuestion(question){question.querySelector('.question-type').addEventListener('change',()=>updateQuestion(question));question.querySelector('.remove-question').addEventListener('click',()=>question.remove());question.addEventListener('dragstart',e=>{e.stopPropagation();question.classList.add('dragging')});question.addEventListener('dragend',()=>question.classList.remove('dragging'));updateQuestion(question)}
function updateBlock(block){const type=block.querySelector('.block-type').value;block.querySelector('.media-fields').hidden=!['image','gallery','video','audio','file','embed','labelled_image','external_link'].includes(type);block.querySelector('.items-fields').hidden=!['gallery','process','accordion','flashcards'].includes(type);block.querySelector('.confirmation-fields').hidden=type!=='confirmation';block.querySelector('.quiz-fields').hidden=type!=='quiz';block.querySelector('.assessment-fields').hidden=type!=='assessment'}
function bindBlock(block){block.querySelector('.block-type').addEventListener('change',()=>updateBlock(block));block.querySelector('.remove-block').addEventListener('click',()=>block.remove());block.querySelector('.correct-option-display').addEventListener('input',e=>block.querySelector('[data-block-field="correct_option"]').value=Math.max(0,e.target.value-1));const feedback=block.querySelector('.show-feedback'),feedbackValue=block.querySelector('[data-assessment-field="show_feedback"]');feedback.addEventListener('change',()=>feedbackValue.value=feedback.checked?'1':'0');block.querySelector('.add-question').addEventListener('click',()=>{block.querySelector('.assessment-questions').insertAdjacentHTML('beforeend',questionTemplate);bindQuestion(block.querySelector('.assessment-questions').lastElementChild)});block.querySelectorAll('.assessment-question').forEach(bindQuestion);block.querySelector('.assessment-questions').addEventListener('dragover',e=>{e.preventDefault();const question=document.querySelector('.assessment-question.dragging');if(question)insertAtY(e.currentTarget,question,e.clientY,'.assessment-question')});block.addEventListener('dragstart',e=>{if(e.target===block){e.stopPropagation();block.classList.add('dragging')}});block.addEventListener('dragend',()=>block.classList.remove('dragging'));updateBlock(block)}
function bindSection(section){section.querySelector('.remove-section').addEventListener('click',()=>{if(sections.children.length>1)section.remove()});section.querySelector('.add-block').addEventListener('click',()=>{section.querySelector('.block-list').insertAdjacentHTML('beforeend',blockTemplate);bindBlock(section.querySelector('.block-list').lastElementChild)});section.querySelectorAll('.training-block').forEach(bindBlock);section.addEventListener('dragstart',e=>{if(e.target===section)section.classList.add('dragging')});section.addEventListener('dragend',()=>section.classList.remove('dragging'))}
function insertAtY(container,item,y,selector){const next=[...container.querySelectorAll(`:scope > ${selector}:not(.dragging)`)].reduce((closest,child)=>{const box=child.getBoundingClientRect(),offset=y-box.top-box.height/2;return offset<0&&offset>closest.offset?{offset,element:child}:closest},{offset:-Infinity}).element;container.insertBefore(item,next||null)}
sections.addEventListener('dragover',e=>{e.preventDefault();const block=document.querySelector('.training-block.dragging');if(block){const list=e.target.closest('.block-list');if(list)insertAtY(list,block,e.clientY,'.training-block');return}const section=document.querySelector('.training-section.dragging');if(section)insertAtY(sections,section,e.clientY,'.training-section')});
document.querySelectorAll('.training-section').forEach(bindSection);document.getElementById('add-section').addEventListener('click',()=>{sections.insertAdjacentHTML('beforeend',document.getElementById('section-template').innerHTML);bindSection(sections.lastElementChild)});
document.getElementById('course-builder').addEventListener('submit',()=>{[...sections.children].forEach((section,i)=>{section.querySelectorAll('[data-section-field]').forEach(input=>input.name=`sections[${i}][${input.dataset.sectionField}]`);[...section.querySelector('.block-list').children].forEach((block,j)=>{const base=`sections[${i}][blocks][${j}]`;block.querySelectorAll('[data-block-field]').forEach(input=>input.name=`${base}[${input.dataset.blockField}]`);block.querySelectorAll('[data-assessment-field]').forEach(input=>input.name=`${base}[${input.dataset.assessmentField}]`);[...block.querySelectorAll('.assessment-question')].forEach((question,k)=>question.querySelectorAll('[data-question-field]').forEach(input=>input.name=`${base}[questions][${k}][${input.dataset.questionField}]`))})})});
</script>
@endpush
