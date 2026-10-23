<?php
/**
 * Hook System
 * Gør det muligt for moduler at køre kode på specifikke tidspunkter.
 */

$hooks = [];

/**
 * Registrer en funktion til et hook
 */
function add_action(string $hook_name, callable $callback): void
{
    global $hooks;

    if (!isset($hooks[$hook_name])) {
        $hooks[$hook_name] = [];
    }

    $hooks[$hook_name][] = $callback;
}

/**
 * Kør alle funktioner der er registreret til et hook
 * Returnerer TRUE hvis mindst én callback aktivt håndterede hooket
 */
function do_action(string $hook_name, ...$args): bool
{
    global $hooks;

    if (empty($hooks[$hook_name])) {
        return false;
    }

    $handled = false;

    foreach ($hooks[$hook_name] as $callback) {
        if (!is_callable($callback)) {
            continue;
        }

        $result = call_user_func_array($callback, $args);

        if ($result === true) {
            $handled = true;
        }
    }

    return $handled;
}
