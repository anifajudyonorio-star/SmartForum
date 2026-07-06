@extends('layouts.app')

@section('content')

<div class="container">

    <h2 class="mb-4">Notifications</h2>

    @forelse($notifications as $notification)

        <a href="{{ route('notifications.read', $notification) }}"
   class="text-decoration-none text-dark">

    <div class="card mb-3 {{ !$notification->is_read ? 'border-primary' : '' }}">

        <div class="card-body">

            <h5>{{ $notification->title }}</h5>

            <p>{{ $notification->message }}</p>

            <small class="text-muted">
                {{ $notification->created_at->diffForHumans() }}
            </small>

        </div>

    </div>

</a>
@empty
    <p>No notifications to display.</p>
@endforelse

</div>

@endsection