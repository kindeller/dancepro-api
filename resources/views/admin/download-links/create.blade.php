@extends('layouts.admin', [
    'title' => 'Create Download Links',
    'heading' => 'Create Download Links',
    'subheading' => 'Paste private S3 object keys and generate public Laravel tracking URLs.',
])

@section('content')
    @if (session('created_links'))
        <section class="card" style="margin-bottom: 18px;">
            <div class="card-pad">
                <h2>New Tracking URLs</h2>
                <p class="muted">These URLs are shown once because raw tokens are not stored.</p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Storage key</th>
                        <th>Tracking URL</th>
                        <th>Expires</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (session('created_links') as $createdLink)
                        <tr>
                            <td class="truncate">{{ $createdLink['key'] }}</td>
                            <td style="overflow-wrap:anywhere;">
                                <a href="{{ $createdLink['url'] }}" target="_blank" rel="noopener">{{ $createdLink['url'] }}</a>
                            </td>
                            <td>{{ $createdLink['expires_at'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <form class="card card-pad" method="POST" action="{{ route('admin.download-links.store') }}">
        @csrf

        <div class="grid two-col">
            <label>
                Storage keys
                <textarea name="keys" placeholder="competition-folder/video-001.mp4&#10;competition-folder/video-002.mp4" required>{{ old('keys') }}</textarea>
            </label>

            <div class="grid">
                <label>
                    Disk
                    <select name="disk">
                        @foreach ($allowedDisks as $disk)
                            <option value="{{ $disk }}" @selected(old('disk', $defaultDisk) === $disk)>{{ $disk }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Valid days
                    <input name="days" type="number" min="1" max="60" value="{{ old('days', 60) }}">
                </label>

                <label>
                    Purpose
                    <input name="purpose" value="{{ old('purpose', 'Competition download links') }}" maxlength="150">
                </label>

                <label>
                    Notes
                    <input name="notes" value="{{ old('notes') }}" maxlength="1000">
                </label>
            </div>
        </div>

        <div style="margin-top: 16px;">
            <button type="submit">Generate tracking URLs</button>
            <a class="button secondary" href="{{ route('admin.download-links.index') }}">Back to table</a>
        </div>
    </form>
@endsection
