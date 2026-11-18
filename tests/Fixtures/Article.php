<?php

namespace Webwizardsusa\ImageCache\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Webwizardsusa\ImageCache\Concerns\TracksImageUsage;

class Article extends Model
{
    use SoftDeletes;
    use TracksImageUsage;

    protected $guarded = ['id'];

    public function referencedImages(): iterable
    {
        return $this->imagesFromAttributes('hero_image_id', 'thumbnail_image_id');
    }
}
