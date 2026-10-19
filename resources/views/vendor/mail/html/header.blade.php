@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('images/logo.jpg') }}" class="logo" alt="{{ config('app.name') }}" style="height: 60px; width: auto; max-height: 60px;">
</a>
</td>
</tr>