<?php

namespace App\Service;

use Symfony\Component\String\Slugger\AsciiSlugger;

class Slugify
{
    private AsciiSlugger $slugger;

    public function __construct()
    {
        $this->slugger = new AsciiSlugger();
    }

    public function slugify(string $text): string
    {
        return $this->slugger->slug($text)->lower();
    }
}
