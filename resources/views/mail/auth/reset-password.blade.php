<x-mail::message>
# Reset your desk password

Hi {{ $name }},

Someone requested a password reset for your **{{ $brand }}** sales desk account ({{ $email }}).

Click the button below to choose a new password. This link expires in {{ $expire }} minutes.

<x-mail::button :url="$url" color="primary">
Reset password
</x-mail::button>

If you did not ask for this, you can ignore this email. Your password will stay the same.

Thanks,<br>
{{ $brand }}
</x-mail::message>
