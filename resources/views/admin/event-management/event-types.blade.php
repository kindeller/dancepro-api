@extends('layouts.admin', ['title' => 'Event types', 'heading' => 'Event types', 'subheading' => 'Manage the reusable event names and the workflow each one follows.'])

@section('content')
    @include('admin.event-management._tabs')
    <div class="card card-pad">
        <h2>Add event type</h2>
        <p class="muted">The workflow controls the scheduling and invoicing rules. Inactivate types you no longer use so historical records remain intact.</p>
        <form class="event-type-row" method="POST" action="{{ route('admin.event-types.store') }}">@csrf
            <label>Name<input name="name" value="{{ old('name') }}" required placeholder="Dance concert"></label>
            <label>Code<input name="code" value="{{ old('code') }}" required placeholder="dance-concert"></label>
            <label>Workflow<select name="system_category" required>@foreach($systemCategories as $category)<option value="{{ $category->value }}">{{ ucfirst($category->value) }}</option>@endforeach</select></label>
            <label>Description<input name="description" value="{{ old('description') }}" maxlength="1000"></label>
            <input type="hidden" name="is_active" value="1"><button>Add type</button>
        </form>
    </div>
    <div class="card card-pad">
        <h2>Existing event types</h2>
        @forelse($eventTypes as $eventType)
            <form class="event-type-row" method="POST" action="{{ route('admin.event-types.update', $eventType) }}">@csrf @method('PUT')
                <label>Name<input name="name" value="{{ $eventType->name }}" required></label>
                <label>Code<input name="code" value="{{ $eventType->code }}" required></label>
                <label>Workflow<select name="system_category" required>@foreach($systemCategories as $category)<option value="{{ $category->value }}" @selected($eventType->system_category === $category)>{{ ucfirst($category->value) }}</option>@endforeach</select></label>
                <label>Description<input name="description" value="{{ $eventType->description }}" maxlength="1000"></label>
                <label>Status<select name="is_active"><option value="1" @selected($eventType->is_active)>Active</option><option value="0" @selected(! $eventType->is_active)>Inactive</option></select></label><button>Save</button>
            </form>
        @empty<p class="muted">No event types have been configured.</p>@endforelse
    </div>
@endsection

@push('styles')
<style>.event-type-row{display:grid;grid-template-columns:minmax(150px,1fr) minmax(130px,.8fr) minmax(130px,.8fr) minmax(220px,1.5fr) minmax(100px,.6fr) auto;gap:10px;align-items:end;padding:12px 0;border-top:1px solid var(--line)}.event-type-row:first-of-type{border-top:0}@media(max-width:1050px){.event-type-row{grid-template-columns:1fr 1fr}}</style>
@endpush
