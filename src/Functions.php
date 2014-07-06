<?php

function abspath($path)
{
    $root = realpath(__DIR__ . '/../');
    $path = ltrim($path, '/\\');
    return "$root/$path";
}

/**
 * Returns a color between red and green matching the given vote in the range
 * of 1 (red) to 5 (green).
 */
function voteColor($vote)
{
    if (!is_numeric($vote) || $vote < 1 || $vote > 5) {
        return null;
    }

    // Map the vote to 0-1 range
    $vote = ($vote - 1) / 4;

    // Convert to RGB & hex encode
    $red = dechex((1 - $vote) * 255);
    $green = dechex($vote * 255);
    $blue = dechex(0);

    return implode('', [
        str_pad($red, 2, '0', STR_PAD_LEFT),
        str_pad($green, 2, '0', STR_PAD_LEFT),
        str_pad($blue, 2, '0', STR_PAD_LEFT),
    ]);
}
