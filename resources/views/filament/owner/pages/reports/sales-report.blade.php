<x-filament-panels::page>
    {{-- Filter widget and charts are automatically rendered via getHeaderWidgets() and getFooterWidgets() --}}
</x-filament-panels::page>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Chart !== 'undefined' && typeof ChartDataLabels !== 'undefined') {
            Chart.register(ChartDataLabels);
        }
    });
</script>
@endpush


