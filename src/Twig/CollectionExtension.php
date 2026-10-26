<?php

namespace App\Twig;

use App\Collection\CollectionStats;
use App\Entity\CardCollection;
use Twig\Attribute\AsTwigFunction;

class CollectionExtension
{
    #[AsTwigFunction('collection_stats')]
    public function stats(CardCollection $collection): CollectionStats
    {
        return CollectionStats::fromCollection($collection);
    }
}
