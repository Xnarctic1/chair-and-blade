@if(session('status'))
    <div class="success" role="status">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="error-summary" role="alert"><strong>Please check your details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
