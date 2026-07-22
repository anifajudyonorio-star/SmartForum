@if(($canManage ?? false) && ($pendingReports ?? collect())->isNotEmpty())
    <div class="card mb-3 fly-in" id="reported-posts">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0"><i class="bi bi-flag-fill text-danger me-2"></i>Reported Messages</h2>
            <span class="badge bg-danger">{{ $pendingReports->count() }} pending</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Topic</th>
                            <th>Message</th>
                            <th>Author</th>
                            <th>Reported by</th>
                            <th>Reason</th>
                            <th style="min-width: 220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingReports as $report)
                            <tr>
                                <td>
                                    @if($report->post?->topic)
                                        <a href="{{ route('topics.show', $report->post->topic) }}" class="text-decoration-none">
                                            {{ $report->post->topic->Title }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small" style="max-width: 260px;">
                                    {{ Str::limit($report->post?->Post_Content, 120) }}
                                </td>
                                <td>{{ $report->post?->user?->name ?? 'Unknown' }}</td>
                                <td>{{ $report->reporter?->name ?? 'Unknown' }}</td>
                                <td class="small text-muted">{{ $report->reason ?: '—' }}</td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <form action="{{ route('groups.post-reports.restore', [$group, $report]) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i> Restore
                                            </button>
                                        </form>
                                        <form action="{{ route('groups.post-reports.destroy', [$group, $report]) }}" method="POST"
                                              onsubmit="return confirm('Permanently delete this message?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="bi bi-trash-fill me-1"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer small text-muted">
            Reported messages are hidden from the discussion until you restore or permanently delete them.
        </div>
    </div>
@endif
