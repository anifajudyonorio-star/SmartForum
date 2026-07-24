@extends('layouts.app')

@section('content')

<div class="container">

    <div class="card">

        <div class="card-header">
            <h3>Edit Group</h3>
        </div>

        <div class="card-body">

            @if ($errors->any())

                <div class="alert alert-danger">

                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>

                </div>

            @endif

            <form action="{{ route('groups.update', $group) }}" method="POST">

                @csrf
                @method('PUT')

                <div class="mb-3">

                    <label class="form-label">
                        Group Name
                    </label>

                    <input
                        type="text"
                        name="Group_Name"
                        class="form-control"
                        value="{{ old('Group_Name', $group->Group_Name) }}"
                        required>

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        Description
                    </label>

                    <textarea
                        name="Description"
                        rows="5"
                        class="form-control"
                        required>{{ old('Description', $group->Description) }}</textarea>

                </div>

                <div class="mb-3">
                    <label class="form-label">Join rules (optional)</label>
                    <textarea name="join_rules" rows="6" class="form-control"
                              placeholder="Rules new members must accept before requesting to join...">{{ old('join_rules', $group->join_rules) }}</textarea>
                    <div class="form-text">If provided, members must read and agree to these rules before sending a join request.</div>
                </div>

                <div class="card mb-3">
                    <div class="card-header py-2">
                        <strong>Inactivity monitoring</strong>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-3">
                            Automatically warn members who stop posting, then temporarily suspend them if they remain inactive after two warnings.
                        </p>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="inactivity_monitoring_enabled" value="1" id="inactivityEnabled"
                                @checked(old('inactivity_monitoring_enabled', $group->inactivity_monitoring_enabled ?? true))>
                            <label class="form-check-label" for="inactivityEnabled">
                                Enable automatic inactivity warnings
                            </label>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small">Days without posting before 1st warning</label>
                                <input type="number" min="1" max="365" class="form-control"
                                       name="inactivity_threshold_days"
                                       value="{{ old('inactivity_threshold_days', $group->inactivity_threshold_days ?? 14) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Grace days after each warning</label>
                                <input type="number" min="1" max="90" class="form-control"
                                       name="inactivity_grace_days"
                                       value="{{ old('inactivity_grace_days', $group->inactivity_grace_days ?? 7) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Suspension duration (days)</label>
                                <input type="number" min="1" max="365" class="form-control"
                                       name="inactivity_blacklist_days"
                                       value="{{ old('inactivity_blacklist_days', $group->inactivity_blacklist_days ?? 30) }}">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    Update Group
                </button>

                <a href="{{ route('groups.show', $group) }}" class="btn btn-secondary">
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

@endsection
