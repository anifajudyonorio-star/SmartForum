@if(isset($groupAdminSummaries) && $groupAdminSummaries->isNotEmpty())
    <div class="card dashboard-card mb-3 fly-in">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-shield-check me-1 text-primary"></i>
                My Groups (Group Admin)
            </h6>
            <a href="{{ route('statistics.index') }}" class="btn btn-primary btn-sm">View Statistics</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 dashboard-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Group</th>
                            <th>Members</th>
                            <th>Topics</th>
                            <th>Posts</th>
                            <th class="pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($groupAdminSummaries as $summary)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $summary->group->Group_Name }}</td>
                                <td>{{ $summary->members_count }}</td>
                                <td>{{ $summary->topics_count }}</td>
                                <td>{{ $summary->posts_count }}</td>
                                <td class="pe-3">
                                    <a href="{{ route('statistics.group', $summary->group) }}" class="btn btn-outline-primary btn-sm">
                                        Group Stats
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
