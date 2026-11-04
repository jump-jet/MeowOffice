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

/** Avatar for a user row: their unlocked cat avatar if chosen, else their desk animal. */
function user_avatar(array $user, string $size = 'md'): string
{
    $cat = $user['cat_avatar'] ?? null;
    $cats = cat_avatars();
    if ($cat && isset($cats[$cat])) {
        return '<span class="avatar avatar-' . e($size) . ' avatar-cat-pack" title="' . e($cats[$cat][1]) . '" aria-hidden="true">' . $cats[$cat][0] . '</span>';
    }

    return animal_avatar((string) ($user['animal'] ?? 'cat'), $size);
}

function user_emoji(array $user): string
{
    $cat = $user['cat_avatar'] ?? null;
    $cats = cat_avatars();

    return ($cat && isset($cats[$cat])) ? $cats[$cat][0] : animal_emoji((string) ($user['animal'] ?? 'cat'));
}
