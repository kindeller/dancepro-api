@extends('layouts.admin',['title'=>'Invoice settings','heading'=>'Invoice settings','subheading'=>'Business details for new studio invoices'])
@section('content')
@include('studio-invoices._styles')
<a class="button secondary" href="{{ route('admin.studio-invoices.index') }}">Back</a>
<form class="studio-billing-form" method="POST" action="{{ route('admin.studio-invoices.settings.save') }}">@csrf @method('PUT')
@foreach(['business_name'=>'Business name','abn'=>'ABN (11 digits)','address'=>'Business address (optional)','email'=>'Contact email','bank_name'=>'Bank account name (optional)','bank_bsb'=>'BSB (optional)','bank_account'=>'Bank account number (optional)'] as $field=>$label)<label>{{ $label }}<input name="{{ $field }}" value="{{ old($field,$settings?->$field) }}" @required(in_array($field,['business_name','abn','email']))></label>@endforeach
<button>Save invoice settings</button><p>Issued invoices retain a snapshot of their original business and payment details.</p></form>
@endsection
