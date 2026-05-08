<?php

function page_card_start(string $title, ?string $subtitle = null): void
{
    echo '<section class="page-card overflow-hidden">';
    echo '<div class="page-card-header px-6 py-5 md:px-7 md:py-6">';
    echo '<h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white">' . htmlspecialchars($title) . '</h1>';
    if ($subtitle) {
        echo '<p class="mt-2 text-slate-500 dark:text-slate-400 max-w-3xl">' . htmlspecialchars($subtitle) . '</p>';
    }
    echo '</div>';
    echo '<div class="p-6 md:p-7">';
}

function page_card_end(): void
{
    echo '</div></section>';
}

function action_button(string $href, string $label, string $icon = 'plus', string $variant = 'primary'): void
{
    $classes = match ($variant) {
        'secondary' => 'bg-white dark:bg-slate-950 text-slate-700 dark:text-slate-200 ring-1 ring-slate-300 dark:ring-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800',
        'success' => 'bg-emerald-600 text-white hover:bg-emerald-700',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700',
        default => 'bg-blue-600 text-white hover:bg-blue-700',
    };

    echo '<a href="' . htmlspecialchars($href) . '" class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 font-semibold shadow-sm transition ' . $classes . '">';
    echo '<i data-lucide="' . htmlspecialchars($icon) . '" class="w-4 h-4"></i>';
    echo '<span>' . htmlspecialchars($label) . '</span>';
    echo '</a>';
}

function flash_message(?array $flash): void
{
    if (!$flash) {
        return;
    }

    $isSuccess = ($flash['type'] ?? '') === 'success';
    $toastId = 'toast_' . uniqid();

    echo '<div id="' . $toastId . '" data-auto-dismiss="4800" class="app-toast mb-6 transition duration-200 ' .
        ($isSuccess ? 'app-toast-success' : 'app-toast-error') . '">';
    echo '<div class="flex items-start gap-3">';
    echo '<div class="mt-0.5">';
    echo '<i data-lucide="' . ($isSuccess ? 'badge-check' : 'alert-circle') . '" class="w-5 h-5"></i>';
    echo '</div>';
    echo '<div class="min-w-0 flex-1">';
    echo '<div class="font-semibold">' . ($isSuccess ? 'Success' : 'Something went wrong') . '</div>';
    echo '<div class="mt-1 text-sm leading-6">' . htmlspecialchars($flash['message'] ?? '') . '</div>';
    echo '</div>';
    echo '<button type="button" onclick="dismissToast(\'' . $toastId . '\')" class="text-current/70 hover:text-current transition">';
    echo '<i data-lucide="x" class="w-4 h-4"></i>';
    echo '</button>';
    echo '</div>';
    echo '</div>';
}

function ui_input_class(): string
{
    return 'w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:focus:ring-blue-900/40 transition';
}

function ui_textarea_class(): string
{
    return ui_input_class();
}

function ui_select_class(): string
{
    return ui_input_class();
}

function ui_primary_button(string $label, string $icon = 'save'): void
{
    echo '<button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-blue-700 transition">';
    echo '<i data-lucide="' . htmlspecialchars($icon) . '" class="w-4 h-4"></i>';
    echo '<span>' . htmlspecialchars($label) . '</span>';
    echo '</button>';
}

function ui_status_badge(string $status): string
{
    $normalized = strtolower(trim($status));
    $classes = match ($normalized) {
        'active', 'approved', 'completed' => 'app-badge app-badge-emerald',
        'inactive', 'rejected', 'for_revision', 'for revision' => 'app-badge app-badge-rose',
        'draft', 'pending', 'confidential' => 'app-badge app-badge-amber',
        'submitted', 'in_review', 'in review', 'unread', 'public' => 'app-badge app-badge-blue',
        'archived', 'cancelled', 'internal', 'read', 'normal' => 'app-badge app-badge-slate',
        'successful', 'low' => 'app-badge app-badge-emerald',
        'high' => 'app-badge app-badge-amber',
        'urgent', 'failed', 'closed' => 'app-badge app-badge-rose',
        'received', 'under_review', 'endorsed', 'in_progress', 'notice' => 'app-badge app-badge-blue',
        default => 'app-badge app-badge-sky',
    };

    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="' . $classes . '">' . htmlspecialchars($label) . '</span>';
}

function ui_stat_card(string $title, string|int|float $value, string $icon = 'bar-chart-3', string $tone = 'blue', ?string $meta = null): void
{
    $toneClasses = [
        'blue' => 'text-blue-600 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/40',
        'indigo' => 'text-indigo-600 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/40',
        'emerald' => 'text-emerald-600 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40',
        'amber' => 'text-amber-600 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40',
        'rose' => 'text-rose-600 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/40',
        'slate' => 'text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-900',
    ];
    $toneClass = $toneClasses[$tone] ?? $toneClasses['blue'];

    echo '<div class="dashboard-stat">';
    echo '<div class="flex items-start justify-between gap-4">';
    echo '<div>';
    echo '<div class="label">' . htmlspecialchars($title) . '</div>';
    echo '<div class="value mt-3">' . htmlspecialchars((string)$value) . '</div>';
    if ($meta) {
        echo '<div class="mt-2 text-sm text-slate-500 dark:text-slate-400">' . htmlspecialchars($meta) . '</div>';
    }
    echo '</div>';
    echo '<div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl ' . $toneClass . '">';
    echo '<i data-lucide="' . htmlspecialchars($icon) . '" class="w-5 h-5"></i>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

function ui_quick_link(string $href, string $label, string $description, string $icon = 'arrow-right'): void
{
    echo '<a href="' . htmlspecialchars($href) . '" class="group rounded-3xl border border-slate-200 dark:border-slate-800 bg-white/85 dark:bg-slate-900/80 p-5 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition">';
    echo '<div class="flex items-start justify-between gap-3">';
    echo '<div>';
    echo '<div class="font-semibold text-slate-900 dark:text-white">' . htmlspecialchars($label) . '</div>';
    echo '<div class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">' . htmlspecialchars($description) . '</div>';
    echo '</div>';
    echo '<div class="rounded-2xl bg-blue-50 dark:bg-blue-900/30 p-3 text-blue-600 dark:text-blue-300">';
    echo '<i data-lucide="' . htmlspecialchars($icon) . '" class="w-5 h-5"></i>';
    echo '</div>';
    echo '</div>';
    echo '</a>';
}

function ui_empty_state(string $title, string $message, string $icon = 'inbox'): void
{
    echo '<div class="app-empty-state">';
    echo '<div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">';
    echo '<i data-lucide="' . htmlspecialchars($icon) . '" class="w-7 h-7"></i>';
    echo '</div>';
    echo '<div class="font-semibold text-slate-900 dark:text-white">' . htmlspecialchars($title) . '</div>';
    echo '<div class="max-w-xl text-sm leading-6 text-slate-500 dark:text-slate-400">' . htmlspecialchars($message) . '</div>';
    echo '</div>';
}
