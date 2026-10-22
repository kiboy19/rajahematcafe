# Changelog Redesign

## Batch 9 — Polish & Final Audit

- Added global `scroll-padding-top` so anchor navigation and keyboard focus remain visible below sticky headers.
- Updated shared SPA Toast semantics: success messages use polite live announcements; errors use `role="alert"` and assertive announcements.
- Audited customer shell, POS/KDS, public navigation, form controls, status badges, and icon-only controls for accessible labels and focus targets.
- Confirmed customer order status timeline is announced through `aria-live`, and customer order receipts support print styling.
- Confirmed customer top-up controls expose numeric input mode, explicit processing/pending/failed states, and retry feedback.
- Confirmed image-backed table QR output includes intrinsic dimensions and descriptive alternative text.

## Verification

- `npm run build` passes.
- Laravel Pint passes for modified PHP files.
- Targeted customer account/order/auth tests pass.
- Midtrans top-up integration tests remain environment-dependent when gateway credentials are unavailable (HTTP 503); no backend contract was changed by the redesign UI work.
