{{-- Print: hide the app shell (sidebar, top bar, tab bar) so a report prints as a clean page. --}}
<style>
    @media print {
        @page { size: A4 landscape; margin: 12mm; }
        body { background: #fff !important; }
        aside, header, body > div > nav, .print\:hidden { display: none !important; }
        main { padding: 0 !important; }
        .card { box-shadow: none !important; break-inside: avoid; }
        .overflow-x-auto { overflow: visible !important; }
        table { min-width: 0 !important; width: 100% !important; font-size: 11px; }
    }
</style>
