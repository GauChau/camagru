# Webcam overlays

## Agreed behavior

- Show the available overlays from `storage/calcs/` in the webcam editor.
- Select zero, one or several overlays with checkboxes (up to 20).
- Stack selected overlays in catalogue order, both in the preview and on the server.
- Fix its position and resize it to fill the entire captured image.
- Compose the saved image on the server.
- Show the selected overlay above the webcam frame whether the camera is active or stopped.
- After saving, show the server-composed montage and hide the preview overlays to avoid applying them twice.
- Enable capture when the webcam or an imported image is ready; overlays are optional.

## Delivery

- Support transparent PNG and opaque JPEG stock images (`.jpg` or `.jpeg`, case-insensitive) using the existing GD extension.
- Serve each overlay with its actual image MIME type and apply JPEG EXIF orientation before composition.
- Serve thumbnails from the overlay catalog without exposing other storage files.
- Show a clear empty state when no overlays are available.
- Preserve the existing login, CSRF, image validation and failed-save cleanup.
- Reject combinations that exceed the PHP memory budget with a controlled validation error.
- Add stock PNG or JPEG images to `storage/calcs/` (5 MiB and 4096 × 4096 pixels maximum).
- Decode overlays one at a time to avoid holding every decoded sticker in memory.

## Verification plan

Exercise the editor over HTTP with generated PNG captures and stock fixtures:
catalog display, optional/multiple selection, stacking order, composite pixels and rejected invalid inputs.
These tests cover PHP integration; real webcam permissions require browser testing.
Review baseline: `c9ef0d1`.
