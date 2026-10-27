{{-- What a new password needs, under its field. account.js fills in the bar and the count as
     the person types; without JavaScript the rules still read correctly. --}}
<div class="password-rules" id="password-rules" data-password-rules="password">
    <div class="strength" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
    <p class="password-rules__level" data-strength-label hidden>Choose a password</p>
    <p class="password-rules__rule" data-length-rule>
        <span class="rule-mark" aria-hidden="true"></span>
        12 characters or more<span data-length-count hidden></span>
    </p>
    <p class="password-rules__rule muted">
        <span class="rule-mark rule-mark--info" aria-hidden="true"></span>
        Not one that has leaked before: we check it against lists of leaked passwords
    </p>
    <p class="muted">A short sentence is easy to remember and hard to guess, like “blue capsule at nine”.</p>
</div>
