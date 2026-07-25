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
            <div class="responsive-table-wrap">
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

            <div class="responsive-card-wrap p-2">
                <div class="data-card-list">
                    @foreach($groupAdminSummaries as $summary)
                        <div class="data-card-item">
                            <p class="data-card-item-title">{{ $summary->group->Group_Name }}</p>
                            <div class="data-card-item-meta">
                                <span><i class="bi bi-people me-1"></i>{{ $summary->members_count }} members</span>
                                <span><i class="bi bi-bookmark me-1"></i>{{ $summary->topics_count }} topics</span>
                                <span><i class="bi bi-chat me-1"></i>{{ $summary->posts_count }} posts</span>
                            </div>
                            <div class="data-card-item-actions">
                                <a href="{{ route('statistics.group', $summary->group) }}" class="btn btn-outline-primary btn-sm w-100">
                                    Group Stats
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
