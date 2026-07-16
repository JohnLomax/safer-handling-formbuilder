@if (session('status'))
    <div class="admin-alert-success" role="status">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="admin-alert-error" role="alert">
        <ul class="list-disc space-y-1 ps-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
