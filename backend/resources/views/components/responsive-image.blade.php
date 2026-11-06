{{--
    One uploaded or bundled image, rendered the same way everywhere: AVIF and WebP sources at three sizes when the
    image has them, its intrinsic size (so the page does not jump while it loads), its alt text, and its focal point
    as object-position, so cropping into any card keeps the subject in view.

    Props:
      image   App\Support\ResponsiveImage (from $event->coverImage(), $item->responsiveImage(), $media->responsiveImage())
      sizes   the sizes attribute: how wide the image is shown, e.g. "(min-width: 768px) 33vw, 100vw"
      alt     overrides the image's own alt text ('' marks it decorative, when nearby text already says it)
      eager   true for the first, above-the-fold image (loads at once with high priority); lazy otherwise
      fit     'cover' (default: fills its box, cropped around the focal point) or 'contain' (whole image shown)
--}}
@props(['image', 'sizes' => '100vw', 'alt' => null, 'eager' => false, 'fit' => 'cover'])
@php
    /** @var \App\Support\ResponsiveImage $image */
    $altText = $alt ?? $image->alt;
    $style = 'object-fit: ' . ($fit === 'contain' ? 'contain' : 'cover') . '; object-position: ' . $image->objectPosition() . ';';
    // A broken image is replaced by the image's own fallback (once), unless the caller handles errors itself.
    $onError = ($image->fallback && !$attributes->has('onerror'))
        ? "this.onerror=null; this.parentElement.querySelectorAll('source').forEach(s => s.remove()); this.removeAttribute('srcset'); this.src=" . e(json_encode($image->fallback, JSON_UNESCAPED_SLASHES)) . ';'
        : null;
@endphp
{{-- The <picture> wrapper takes no box of its own (display: contents), so a class or style given here lands on the
     <img> and existing card styles keep working. --}}
<picture class="eb-picture">
    @foreach($image->sources as $type => $srcset)
        <source type="{{ $type }}" srcset="{{ $srcset }}" sizes="{{ $sizes }}">
    @endforeach
    <img src="{{ $image->src }}" alt="{{ $altText }}"
         @if($image->width && $image->height) width="{{ $image->width }}" height="{{ $image->height }}" @endif
         loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" @if($eager) fetchpriority="high" @endif
         @if($onError) onerror="{!! $onError !!}" @endif
         {{ $attributes->merge(['style' => $style]) }}>
</picture>
