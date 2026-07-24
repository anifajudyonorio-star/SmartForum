/**
 * Polls for student quizzes that are starting / active and shows a launch popup.
 * Starting the quiz opens the take page where the duration countdown runs.
 */
(function () {
    const pollUrl = document.querySelector('meta[name="quiz-launch-poll-url"]')?.content;
    if (!pollUrl) return;

    const modalEl = document.getElementById('quizLaunchModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    const titleEl = document.getElementById('quizLaunchModalTitle');
    const descEl = document.getElementById('quizLaunchDescription');
    const timerEl = document.getElementById('quizLaunchTimer');
    const timerLabelEl = document.getElementById('quizLaunchTimerLabel');
    const metaEl = document.getElementById('quizLaunchMeta');
    const startBtn = document.getElementById('quizLaunchStartBtn');
    const startLabel = document.getElementById('quizLaunchStartLabel');

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const STORAGE_KEY = 'smartforum.quizLaunch.dismissed';
    const PRESTART_SECONDS = 60;

    let tickHandle = null;
    let activeQuiz = null;
    let localDeadlineMs = null;
    let mode = 'until_start'; // until_start | until_end | duration

    function dismissedIds() {
        try {
            return JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]');
        } catch {
            return [];
        }
    }

    function markDismissed(id) {
        const ids = dismissedIds().filter((value) => value !== id);
        ids.push(id);
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
    }

    function onQuizTakePage(quizId) {
        return window.location.pathname.includes(`/student/quizzes/${quizId}`);
    }

    function formatClock(totalSeconds) {
        const seconds = Math.max(0, Math.floor(totalSeconds));
        const hours = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        if (hours > 0) {
            return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }
        return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    function stopTick() {
        if (tickHandle) {
            clearInterval(tickHandle);
            tickHandle = null;
        }
    }

    function updateTimerDisplay() {
        if (!localDeadlineMs || !timerEl) return;
        const remaining = Math.max(0, Math.round((localDeadlineMs - Date.now()) / 1000));
        timerEl.textContent = formatClock(remaining);
        timerEl.classList.toggle('is-urgent', remaining <= 30);

        if (remaining <= 0 && mode === 'until_start' && activeQuiz) {
            // Flip to active launch state.
            mode = 'duration';
            localDeadlineMs = Date.now() + (activeQuiz.duration_minutes * 60 * 1000);
            if (timerLabelEl) timerLabelEl.textContent = 'Quiz duration';
            if (titleEl) titleEl.textContent = `${activeQuiz.title} is live`;
            if (startLabel) startLabel.textContent = activeQuiz.has_open_attempt ? 'Resume Quiz' : 'Start Quiz';
            if (startBtn) startBtn.href = activeQuiz.start_url;
        }
    }

    function showQuiz(quiz, preferredMode) {
        if (!quiz || onQuizTakePage(quiz.id)) return;
        if (dismissedIds().includes(quiz.id) && preferredMode !== 'force') return;

        activeQuiz = quiz;
        mode = preferredMode;

        if (titleEl) {
            titleEl.textContent = preferredMode === 'until_start'
                ? `${quiz.title} starts soon`
                : `${quiz.title} is live`;
        }
        if (descEl) {
            descEl.textContent = quiz.description || 'Your scheduled quiz window is open. Start now to begin the countdown.';
        }
        if (metaEl) {
            metaEl.innerHTML = `
                <span><i class="bi bi-clock me-1"></i>${quiz.duration_minutes} min</span>
                <span><i class="bi bi-question-circle me-1"></i>${quiz.questions_count} questions</span>
            `;
        }

        if (preferredMode === 'until_start') {
            if (timerLabelEl) timerLabelEl.textContent = 'Starts in';
            localDeadlineMs = Date.now() + (quiz.seconds_until_start * 1000);
            if (startLabel) startLabel.textContent = 'Preview';
            if (startBtn) startBtn.href = quiz.preview_url;
        } else {
            if (timerLabelEl) timerLabelEl.textContent = 'Quiz duration';
            localDeadlineMs = Date.now() + (quiz.duration_minutes * 60 * 1000);
            if (startLabel) startLabel.textContent = quiz.has_open_attempt ? 'Resume Quiz' : 'Start Quiz';
            if (startBtn) startBtn.href = quiz.start_url;
        }

        updateTimerDisplay();
        stopTick();
        tickHandle = setInterval(updateTimerDisplay, 250);
        modal.show();
    }

    function pickQuiz(quizzes) {
        const dismissed = dismissedIds();
        const active = quizzes.find((quiz) =>
            quiz.status === 'Active'
            && !dismissed.includes(quiz.id)
            && !onQuizTakePage(quiz.id)
        );
        if (active) return { quiz: active, mode: 'duration' };

        const soon = quizzes.find((quiz) =>
            quiz.status === 'Scheduled'
            && quiz.seconds_until_start > 0
            && quiz.seconds_until_start <= PRESTART_SECONDS
            && !dismissed.includes(quiz.id)
            && !onQuizTakePage(quiz.id)
        );
        if (soon) return { quiz: soon, mode: 'until_start' };

        return null;
    }

    async function poll() {
        try {
            const response = await fetch(pollUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) return;
            const data = await response.json();
            const choice = pickQuiz(data.quizzes || []);
            if (!choice) return;

            // If modal already showing the same quiz, only refresh timing when scheduled.
            if (activeQuiz && activeQuiz.id === choice.quiz.id && modalEl.classList.contains('show')) {
                if (choice.mode === 'until_start') {
                    localDeadlineMs = Date.now() + (choice.quiz.seconds_until_start * 1000);
                }
                return;
            }

            showQuiz(choice.quiz, choice.mode);
        } catch {
            // Ignore transient poll errors.
        }
    }

    function dismissCurrent() {
        if (activeQuiz) markDismissed(activeQuiz.id);
        stopTick();
    }

    modalEl.addEventListener('hidden.bs.modal', dismissCurrent);
    document.getElementById('quizLaunchLater')?.addEventListener('click', dismissCurrent);
    document.getElementById('quizLaunchDismiss')?.addEventListener('click', dismissCurrent);

    startBtn?.addEventListener('click', () => {
        // Leaving for the quiz page; keep dismissed so it doesn't re-pop mid-redirect.
        if (activeQuiz) markDismissed(activeQuiz.id);
        stopTick();
    });

    poll();
    setInterval(poll, 5000);
})();
