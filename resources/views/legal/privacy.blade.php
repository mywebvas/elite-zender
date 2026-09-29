@include('legal.layout', ['title' => 'Privacy Policy', 'updated' => 'October 2026', 'slot' => new \Illuminate\Support\HtmlString(view('legal.privacy-body')->render())])
