@props(['message', 'type' => 'success'])

@if($message !== '')
    <div class="mb-4 rounded-lg border p-4 text-sm
        {{ $type === 'success'
            ? 'border-green-700/50 bg-green-900/20 text-green-300'
            : 'border-red-700/50 bg-red-900/20 text-red-300' }}">
        {{ $message }}
    </div>
@endif
