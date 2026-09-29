@props(['subjectLine' => null, 'preheader' => null])

@include('emails.layout', [
    'slot' => $slot,
    'subjectLine' => $subjectLine,
    'preheader' => $preheader,
])
