---
title: Concepts
weight: 20
description: Images, Image Styles, Variants, Generation Strategies, Visibility, Usages and Orphaned Images.
---

## Images

An Image is an original image file kept by the application. You store it once, with `Image::store()`, and it never
changes afterwards. To replace the picture, or to change whether it's public or private, store a new Image.

Each Image records the disk and path it was written to, its media type, its width and height, its size in bytes and a
SHA-256 hash of its contents. Routes find Images by a ULID rather than by their id, so the URLs of other Images can't
be guessed.

## Image Styles and Variants

An Image Style is a named set of transformations defined in code. A Variant is one Image rendered in one Image Style.
An Image has at most one Variant per style.

Variants are stored and found by the name the style declares, never by its class name, so renaming or moving the class
doesn't cut its Variants off. They're written to `{prefix}/{style}/{original path}`, with the extension changed when
the style changes the format: `images/abc.jpg` in a WebP style named `banner` becomes `image-cache/banner/images/abc.webp`.

## Generation Strategies

Each Image Style chooses when its Variants are generated:

| Strategy | When |
|---|---|
| `GenerationStrategy::OnDemand` (default) | The first time the Variant is requested. |
| `GenerationStrategy::OnCreate` | While the Image is being stored. If it fails, the Image isn't stored. |
| `GenerationStrategy::Queued` | On the queue, right after the Image is stored. |

Whatever the strategy, a Variant that's missing when it's requested is generated then. A queued job that hasn't run
yet, or a style that's been flushed, never means a broken image.

## Visibility

An Image is public or private, and its Variants share its Visibility. The URLs of public Images can be shared freely.
Private Images and their Variants are written to private disks, and their URLs are temporary: whoever has one can see
the Image until it expires. See [private Images](usage/private-images.md).

## Usages and Orphaned Images

A Usage is a record's use of an Image, such as an article whose hero picture it is. Models with the `TracksImageUsage`
trait report the Images they use every time they're saved.

An Image that had Usages and has lost its last one is **Orphaned**. `image-cache:prune` deletes Images that have been
Orphaned for longer than a grace period, which is 24 hours by default. An Image that has never had a Usage is never
Orphaned, so Images you don't track aren't deleted. See [usage tracking](usage/usage-tracking.md).
