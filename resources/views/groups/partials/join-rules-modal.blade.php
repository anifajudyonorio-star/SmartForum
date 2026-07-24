<div class="modal fade" id="joinRulesModal{{ $group->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('groups.join', $group) }}" method="POST">
                @csrf
                <input type="hidden" name="accepted_rules" value="1">
                <div class="modal-header">
                    <h5 class="modal-title">Group rules — {{ $group->Group_Name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Please read the rules below. You must agree before your join request can be sent to the group admin.
                    </p>
                    <div class="border rounded p-3 bg-light small" style="max-height: 320px; overflow-y: auto; white-space: pre-wrap;">{{ $group->join_rules }}</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Decline</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check2-circle me-1"></i> Agree &amp; Request to Join
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
