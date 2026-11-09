{{--
    Says what a picture is when it is not the record's own image: "Illustrative photo" on a library photograph shown
    for an event with no flyer. Place it inside the image's positioned container. Renders nothing otherwise.

    Props: image  App\Support\ResponsiveImage
           corner 'bottom-left' (default), 'top-left' or 'top-right'
--}}
@props(['image', 'corner' => 'bottom-left'])
@if($image->label)
    <span class="eb-image-label" title="{{ $image->alt }}"
          style="position: absolute; {{ $corner === 'top-right' ? 'right: 10px' : 'left: 10px' }}; {{ $corner === 'bottom-left' ? 'bottom: 10px' : 'top: 10px' }}; z-index: 3; padding: 3px 9px; border-radius: 999px;
                 background: rgba(7, 17, 31, .8); color: #F8FAFC; font-size: 11px; font-weight: 600; letter-spacing: .01em; pointer-events: none;">
        {{ $image->label }}
    </span>
@endif
