# Tenant-Scoped Refactor – Step 1

Tracking checklist for migrating owner dashboard & Filament resources from legacy store-scoped assumptions to tenant + multi-store aware flows.

## Wave 1 – Panel Navigation & Global Filters

- [x] Owner panel navigation groups finalized & sorted (see `OwnerPanelProvider`)
- [x] Global dashboard filter form (tenant, store, date) via `OwnerDashboard`
- [x] `GlobalFilterService` keeps filter state + exposes helpers for widgets
- [x] Store switcher and tenant context middleware deployed

## Wave 2 – Owner Dashboard Widgets

- [x] `OwnerStatsWidget`, `SalesRevenueChartWidget`, `BestBranchesWidget`
- [x] `TopMenuTableWidget` & `TopMenuPieWidget` (multi-store, tenant-scoped, Nov 21)
- [x] `CogsSummaryWidget`, `LowStockWidget`, `RecentOrdersWidget`
- [x] Advanced analytics stack (`ProfitAnalysisWidget`, `AdvancedAnalyticsWidget`, `BusinessRecommendationsWidget`) now consume `GlobalFilterService`
- [ ] Future: add QA coverage for new widgets + empty-state telemetry

## Wave 3 – Tenant-Scoped Resources

- [ ] Review remaining Filament resources/forms that still read `auth()->user()->store_id`
- [ ] Ensure reporting & FnB analytics services accept tenant + store arrays
- [ ] Verify manual test suites (`docs/UNIFIED_DASHBOARD_TEST_GUIDE.md`) cover new widgets

## Notes

- Latest audit (Nov 21, 2025) shows dashboard widgets no longer hit `products.store_id` and all analytics queries now route through store-id arrays from the global filter.
- Outstanding tasks remain around legacy form filters (e.g. resource schemas) which still intentionally scope to a single store; flag them when we start Step 2 of the refactor.

