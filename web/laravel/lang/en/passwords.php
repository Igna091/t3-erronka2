<?php

// Password reset messages. "sent", "throttled" and "user" never reach the page:
// App\Http\Responses\PasswordResetLinkResponse answers the same way to all three.
return [
    'reset' => 'Your password is changed, and every browser that was logged in is logged out. Log in with the new one.',
    'sent' => 'We’ve sent a link to choose a new password.',
    'throttled' => 'Please wait a minute before asking again.',
    'token' => 'That link has expired or was used already. Ask for a new one.',
    'user' => 'We’ve sent a link to choose a new password.',
];
