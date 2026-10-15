<x-mail::message>
# Integration setup help

**{{ $requesterName }}** asked to be forwarded to a technical supervisor for **{{ $integrationTitle }}**.

**Provider:** `{{ $provider }}`  
**From:** {{ $requesterEmail }}

@if($stuckOn)
**Stuck on**  
{{ $stuckOn }}
@endif

**What they said**  
{{ $note }}

Walk them through keys, a quick demo reply, and how the desk uses conversation context — like a salesperson, not a config dump.

<x-mail::button :url="$integrationsUrl">
Open Integrations
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
