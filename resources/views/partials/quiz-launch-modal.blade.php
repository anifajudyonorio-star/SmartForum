{{-- Student quiz launch popup: appears when a scheduled quiz becomes active --}}
@auth
    @if(auth()->user()->isStudent())
        <meta name="quiz-launch-poll-url" content="{{ route('student.quizzes.launch-poll') }}">

        <div class="modal fade" id="quizLaunchModal" tabindex="-1" aria-labelledby="quizLaunchModalTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content quiz-launch-modal">
                    <div class="modal-header border-0 pb-0">
                        <div>
                            <p class="quiz-launch-eyebrow mb-1"><i class="bi bi-patch-question-fill me-1"></i>Quiz time</p>
                            <h5 class="modal-title fw-bold" id="quizLaunchModalTitle">Quiz is ready</h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="quizLaunchDismiss"></button>
                    </div>
                    <div class="modal-body pt-2">
                        <p class="text-muted small mb-3" id="quizLaunchDescription"></p>
                        <div class="quiz-launch-timer-wrap mb-3">
                            <p class="small text-muted mb-1" id="quizLaunchTimerLabel">Starts in</p>
                            <div class="quiz-launch-timer" id="quizLaunchTimer">00:00</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 small text-muted" id="quizLaunchMeta"></div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="quizLaunchLater">Later</button>
                        <a href="#" class="btn btn-primary" id="quizLaunchStartBtn">
                            <i class="bi bi-play-fill me-1"></i><span id="quizLaunchStartLabel">Start Quiz</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endauth
