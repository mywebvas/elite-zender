@include('legal.layout', ['title' => 'Terms of Service', 'updated' => 'October 2026', 'slot' => new \Illuminate\Support\HtmlString(view('legal.terms-body')->render())])
