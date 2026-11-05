{{--
    Noto Sans (SIL OFL 1.1, resources/fonts/OFL.txt) for dompdf documents: bills, and quotations
    can @include this too. dompdf knows only "normal" and "bold" per family, so:
      'Noto Sans'          normal = Regular 400, bold (font-weight 600) = SemiBold 600
      'Noto Sans Medium'   normal = Medium 500 (item names)
    Noto Sans has the ₹ sign and lining figures, so amount columns line up.
--}}
@php $fontDir = resource_path('fonts'); @endphp
<style>
    @font-face { font-family: 'Noto Sans'; font-style: normal; font-weight: normal; src: url("{{ $fontDir }}/NotoSans-Regular.ttf") format('truetype'); }
    @font-face { font-family: 'Noto Sans'; font-style: normal; font-weight: bold; src: url("{{ $fontDir }}/NotoSans-SemiBold.ttf") format('truetype'); }
    @font-face { font-family: 'Noto Sans Medium'; font-style: normal; font-weight: normal; src: url("{{ $fontDir }}/NotoSans-Medium.ttf") format('truetype'); }
</style>
