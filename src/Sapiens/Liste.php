<?php

namespace App\Sapiens;

/** struct liste: a list of references (objects carried, sentences, actions...): 1-based ref numbers. */
final class Liste
{
    /** @var int[] 30 elements */
    public array $elements;

    /** @param int[] $elements */
    public function __construct(public int $typeRef, public int $taille, public int $maxtaille, array $elements = [])
    {
        $this->elements = array_pad($elements, 30, 0);
    }
}
