@props(['name' => 'package'])
@php
    $svg = \App\Support\AssetCache::svg((string) $name);
    $callerClass = (string) $attributes->get('class', '');
    $hasSize = preg_match('/(?:^|\s)(?:[a-z0-9-]+:)*(?:size|w|h)-(?:\[[^\]]+\]|[a-z0-9.\/]+)/i', $callerClass) === 1;
    $defaultClass = ($hasSize ? '' : 'size-4 ').'shrink-0 block';
    $attrString = $attributes->merge(['class' => $defaultClass, 'aria-hidden' => 'true'])->toHtml();
    $svg = preg_replace('/<svg\b/', '<svg '.$attrString, $svg, 1);
@endphp
{!! $svg !!}
