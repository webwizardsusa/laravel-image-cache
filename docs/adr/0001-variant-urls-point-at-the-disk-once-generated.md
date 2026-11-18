# Variant URLs point at the disk once the Variant exists, and at the package's route until then

An Image's URL for a style is worked out when it's asked for. If the Variant has been generated, the URL is the one its
disk serves it at (`Storage::url()`, or `temporaryUrl()` for a private Image), so the web server, S3 or a CDN serves
it and PHP isn't involved. If it hasn't, the URL is the package's route (`/image-cache/{style}/{ulid}`, signed and
expiring for a private Image), which generates the Variant under a lock and redirects to the disk URL. Only the first
request for a Variant pays for it.

## Considered Options

- **Every Variant URL is the package's route**, which generates the Variant if needed and streams it. It works on any
  disk and the URL never changes, but PHP serves every image request, and caching depends on the app's headers.
- **Drupal's fall-through**: the URL is always the Variant's path on the public disk, and the web server hands requests
  for missing files to Laravel (`try_files`). URLs never change and PHP is only hit once, but it needs web server
  configuration, only works on a local public disk, and can't support private Images.

## Consequences

- An Image's URL changes once its Variant exists. HTML that saved the route URL still works, because the route
  redirects to the Variant, but it costs a redirect each time. Store Images, not URLs, and ask for URLs when rendering.
- Working out a URL needs the Image's Variant rows. Eager-load `variants` when rendering many Images.
- Flushing a style sends its URLs back to the route until the Variants are generated again.
- A private Image's URL is always temporary, whether it points at the disk or at the route.
