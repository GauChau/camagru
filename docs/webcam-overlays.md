# Webcam overlays

## Agreed behavior

- Show the available overlays from `storage/calcs/` in the webcam editor.
- Use one selected overlay per capture.
- Fix its position and resize it to fill the entire captured image.
- Compose the saved image on the server.
- Show the server-composed montage in the preview after a successful save.
- Enable capture only when both the webcam and an overlay are ready.

## Delivery

- Support transparent PNG stock images using the existing GD extension.
- Serve thumbnails from the overlay catalog without exposing other storage files.
- Show a clear empty state when no overlays are available.
- Preserve the existing login, CSRF, image validation and failed-save cleanup.
- Document where to add stock images; `storage/calcs/` is currently empty.

## Verification plan

Exercise the editor over HTTP with generated PNG captures and stock fixtures:
catalog display, required selection, composite pixels and rejected invalid inputs.
These tests cover PHP integration; real webcam permissions require browser testing.
Review baseline: `c9ef0d1`.
