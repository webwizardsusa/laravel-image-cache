# Image Cache

Rendering stored images in named styles, serving the results, and cleaning up images nothing uses any more.

## Language

**Image**:
An original image file kept by the application, stored once and never changed afterwards. Replacing the picture means
storing a new Image.
_Avoid_: Media, upload, asset, file

**Image Style**:
A named set of transformations, such as "thumb" or "banner", defined by a developer in code. An Image Style can be
applied to any Image.
_Avoid_: Preset, conversion, size

**Variant**:
One Image rendered in one Image Style. An Image has at most one Variant per Image Style, and the Variant is generated
again after the Image Style is flushed.
_Avoid_: Derivative, conversion, thumbnail, rendition

**Generation Strategy**:
When an Image Style's Variants are made: on demand, the first time one is requested; on create, while the Image is
being stored; or queued, in the background right after it's stored. Whatever the strategy, a Variant that's missing
when it's requested is made then.
_Avoid_: Mode, trigger

**Visibility**:
Whether anyone with an Image's URL can see it (public) or only someone given a temporary link (private). An Image's
Variants always share its Visibility.
_Avoid_: Access, privacy

**Usage**:
A record's use of an Image, such as an article whose hero picture it is. A record has at most one Usage per Image.
_Avoid_: Reference, attachment, link

**Orphaned Image**:
An Image that had Usages and has lost its last one. Orphaned Images are deleted after a grace period. An Image that
never had a Usage is never Orphaned.
_Avoid_: Unused image, dangling image
