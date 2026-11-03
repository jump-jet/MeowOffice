<?php

declare(strict_types=1);

function animal_choices(): array
{
    return [
        'cat' => 'Mochi the Cat',
        'bunny' => 'Bean the Bunny',
        'puppy' => 'Toast the Puppy',
        'panda' => 'Bao the Panda',
        'fox' => 'Maple the Fox',
        'owl' => 'Noodle the Owl',
    ];
}

function animal_emoji(string $animal): string
{
    return [
        'cat' => '🐱',
        'bunny' => '🐰',
        'puppy' => '🐶',
        'panda' => '🐼',
        'fox' => '🦊',
        'owl' => '🦉',
    ][$animal] ?? '🐾';
}

function animal_avatar(string $animal, string $size = 'md'): string
{
    $emoji = animal_emoji($animal);
    $label = e(animal_choices()[$animal] ?? 'Office friend');

    return '<span class="avatar avatar-' . e($size) . ' avatar-' . e($animal) . '" title="' . $label . '" aria-hidden="true">' . $emoji . '</span>';
}
