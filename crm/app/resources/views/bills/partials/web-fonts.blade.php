{{-- Noto Sans (SIL OFL 1.1) for printable bill pages in the browser. Same files as the PDF. --}}
<style>
    @font-face { font-family: 'Noto Sans'; font-style: normal; font-weight: 400; font-display: swap; src: url("{{ route('bills.font', 'NotoSans-Regular.ttf') }}") format('truetype'); }
    @font-face { font-family: 'Noto Sans'; font-style: normal; font-weight: 500; font-display: swap; src: url("{{ route('bills.font', 'NotoSans-Medium.ttf') }}") format('truetype'); }
    @font-face { font-family: 'Noto Sans'; font-style: normal; font-weight: 600; font-display: swap; src: url("{{ route('bills.font', 'NotoSans-SemiBold.ttf') }}") format('truetype'); }
    .font-noto { font-family: 'Noto Sans', system-ui, sans-serif; font-variant-numeric: tabular-nums; }
</style>
