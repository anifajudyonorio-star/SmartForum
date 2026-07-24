@extends('layouts.auth')

@section('content')
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-card-brand">
            <div class="auth-logo" style="font-size:1.2rem;">✉</div>
            <h1 class="auth-title">Check your email</h1>
            <p class="auth-subtitle">We sent a 6-digit code to <strong>{{ auth()->user()->email }}</strong></p>
        </div>

        <div class="auth-card-form">
            @if(session('status'))
                <div class="alert alert-success py-2 small mb-3">{{ session('status') }}</div>
            @endif
            @if(session('resend_error'))
                <div class="alert alert-warning py-2 small mb-3">{{ session('resend_error') }}</div>
            @endif
            @error('code')
                <div class="alert alert-danger py-2 small mb-3">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('verification.verify') }}" id="otpForm">
                @csrf
                <input type="hidden" name="code" id="codeInput">

                <div class="otp-boxes mb-3" id="otpBoxes">
                    @for($i = 0; $i < 6; $i++)
                        <input type="text" inputmode="numeric" maxlength="1"
                               class="otp-box" autocomplete="off" pattern="[0-9]">
                    @endfor
                </div>

                <button type="submit" class="btn btn-primary w-100 auth-submit" id="otpSubmit">
                    Verify Email
                </button>
            </form>

            <div class="text-center mt-3">
                <p class="small text-muted mb-1">Didn't receive it? Check your spam folder or</p>
                <form method="POST" action="{{ route('verification.send') }}" id="resendForm">
                    @csrf
                    <button type="submit" class="btn btn-link p-0 small fw-600" id="resendBtn"
                            style="color:var(--primary-dark);font-weight:600;">
                        Resend code
                    </button>
                    <span id="resendCountdown" class="small text-muted d-none"></span>
                </form>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="text-center mt-2">
                @csrf
                <button type="submit" class="btn btn-link p-0 small text-muted">Sign out</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
.otp-boxes {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
}
.otp-box {
    width: 44px;
    height: 52px;
    text-align: center;
    font-size: 1.4rem;
    font-weight: 700;
    border: 2px solid var(--primary-border);
    border-radius: 0.4rem;
    outline: none;
    transition: border-color 0.15s ease;
    color: #111827;
}
.otp-box:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-soft);
}
.otp-box.filled {
    border-color: var(--primary);
    background: var(--primary-muted);
}
</style>
<script>
(function () {
    const boxes = Array.from(document.querySelectorAll('.otp-box'));
    const codeInput = document.getElementById('codeInput');
    const form = document.getElementById('otpForm');

    boxes.forEach((box, i) => {
        box.addEventListener('input', () => {
            box.value = box.value.replace(/\D/g, '').slice(-1);
            box.classList.toggle('filled', box.value !== '');
            if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
            syncCode();
        });

        box.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !box.value && i > 0) {
                boxes[i - 1].value = '';
                boxes[i - 1].classList.remove('filled');
                boxes[i - 1].focus();
                syncCode();
            }
        });

        box.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasted = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
            pasted.split('').forEach((ch, j) => {
                if (boxes[j]) { boxes[j].value = ch; boxes[j].classList.add('filled'); }
            });
            const next = boxes[Math.min(pasted.length, boxes.length - 1)];
            next && next.focus();
            syncCode();
            if (pasted.length === 6) form.submit();
        });
    });

    function syncCode() {
        const code = boxes.map(b => b.value).join('');
        codeInput.value = code;
        if (code.length === 6) form.submit();
    }

    boxes[0].focus();

    // Resend cooldown
    @if(session('status'))
    startCooldown(60);
    @endif

    function startCooldown(seconds) {
        const btn = document.getElementById('resendBtn');
        const countdown = document.getElementById('resendCountdown');
        btn.disabled = true;
        btn.classList.add('d-none');
        countdown.classList.remove('d-none');
        let remaining = seconds;
        countdown.textContent = `Resend in ${remaining}s`;
        const interval = setInterval(() => {
            remaining--;
            if (remaining <= 0) {
                clearInterval(interval);
                btn.disabled = false;
                btn.classList.remove('d-none');
                countdown.classList.add('d-none');
            } else {
                countdown.textContent = `Resend in ${remaining}s`;
            }
        }, 1000);
    }

    document.getElementById('resendForm').addEventListener('submit', () => startCooldown(60));
})();
</script>
@endpush
