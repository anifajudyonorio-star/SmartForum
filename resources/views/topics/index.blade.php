@extends('layouts.app')

@section('content')

<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>💬 Discussion Topics</h2>

        <a href="{{ route('groups.index') }}" class="btn btn-secondary">
            ← Back to Groups
        </a>

    </div>

    @if($recommendedTopics->isNotEmpty())

        <div class="card border-info shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0">✨ Recommended for you</h4>
                    <span class="badge bg-info text-dark">AI suggestions</span>
                </div>

                <div class="row">
                    @foreach($recommendedTopics as $topic)
                        <div class="col-lg-6 mb-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="fw-semibold mb-0">{{ $topic->Title }}</h5>
                                    <span class="badge bg-warning text-dark">{{ number_format($topic->recommendation_score, 2) }}</span>
                                </div>
                                <p class="text-muted small mb-2">{{ Str::limit($topic->Topic_Description, 120) }}</p>
                                @if($topic->group)
                                    <p class="small text-muted mb-3">
                                        <i class="bi bi-people me-1"></i>{{ $topic->group->Group_Name }}
                                    </p>
                                @endif
                                @if($topic->group && $topic->can_view)
                                    <a href="{{ route('topics.show', $topic) }}" class="btn btn-outline-success btn-sm">
                                        View recommendation
                                    </a>
                                @elseif($topic->group)
                                    @include('groups.partials.join-request-button', [
                                        'group' => $topic->group,
                                        'joinStatus' => $topic->join_status ?? null,
                                    ])
                                @else
                                    <span class="text-muted small">Group details unavailable</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    @endif

    @if($topics->count())

        <div class="row">

            @foreach($topics as $topic)

            <div class="col-lg-6 mb-4">

                <div class="card shadow border-0 rounded-4 h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-start">

                            <div>

                                <h4 class="fw-bold">
                                    💬 {{ $topic->Title }}
                                </h4>

                                <span class="badge bg-primary">
                                    {{ $topic->group->Group_Name }}
                                </span>

                            </div>

                            <span class="badge bg-success">
                                {{ $topic->posts_count }} Posts
                            </span>

                        </div>

                        <hr>

                        <p class="text-muted">

                            {{ $topic->Topic_Description }}

                        </p>

                        <div class="row text-center my-4">

                            <div class="col">

                                <h5>{{ $topic->posts_count }}</h5>

                                <small class="text-muted">
                                    Posts
                                </small>

                            </div>

                            <div class="col">

                                <h5>{{ $topic->created_at->format('d M Y') }}</h5>

                                <small class="text-muted">
                                    Created
                                </small>

                            </div>

                        </div>

                        <p class="mb-4">

                            👤 Created by:

                            <strong>

                                {{ $topic->user->name }}

                            </strong>

                        </p>

                        <div class="d-flex justify-content-between">

                            <a href="{{ route('topics.show', $topic) }}"
                               class="btn btn-success">

                                Open Discussion

                            </a>

                            <div>

                                <a href="{{ route('topics.edit', $topic) }}"
                                   class="btn btn-warning">

                                    Edit

                                </a>

                                <form action="{{ route('topics.destroy', $topic) }}"
                                      method="POST"
                                      class="d-inline">

                                    @csrf
                                    @method('DELETE')

                                    <button
                                        class="btn btn-danger"
                                        onclick="return confirm('Delete this topic?')">

                                        Delete

                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            @endforeach

        </div>

    @else

        <div class="alert alert-info text-center">

            <h5>No Topics Available</h5>

            <p>Create a topic inside a group to begin the discussion.</p>

        </div>

    @endif

</div>

@endsection