@props(['url', 'logo' => null])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($logo)
<img src="{{ $logo }}" class="logo" alt="{{ trim(strip_tags($slot)) }}" height="34">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
