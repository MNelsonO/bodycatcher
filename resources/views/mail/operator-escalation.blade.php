<x-mail::message>
# Desk needs your input

The AI paused on this lead and is waiting for you.

**Name:** {{ $contact }}@if($business) · {{ $business }}@endif  
**Status:** {{ $status }}  
**System interested in:** {{ $product }}  
**Current position:** {{ $stage }}  
**Phone:** {{ $phone }}

**Why it stopped**  
{{ $reason }}

@if($snippet)
**Latest chat**  
{{ $snippet }}
@endif

@if($suggested)
**Suggested reply (not sent)**  
{{ $suggested }}
@endif

Feed unfamiliar facts on Intelligence → Unfamiliar questions, or correct this lead directly.

<x-mail::button :url="$leadUrl">
Open lead & correct
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
