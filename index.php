<?php
session_start();

if (!isset($_SESSION['tasks'])) $_SESSION['tasks'] = [];
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

const PRIORITIES = ['low' => 1, 'medium' => 2, 'high' => 3];

function csrf(): string {
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

function cleanDate(string $d): string {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return ($dt && $dt->format('Y-m-d') === $d) ? $d : '';
}

function addTask(array $in, array &$tasks): bool {
    $text = trim($in['task'] ?? '');
    if ($text === '') return false;
    $priority = $in['priority'] ?? 'medium';
    $tasks[] = [
        'id' => uniqid('task_', true),
        'text' => $text,
        'subject' => trim($in['subject'] ?? ''),
        'priority' => isset(PRIORITIES[$priority]) ? $priority : 'medium',
        'due' => cleanDate($in['due'] ?? ''),
        'completed' => false,
        'created' => time()
    ];
    return true;
}

function updateTask(string $id, array $in, array &$tasks): bool {
    $text = trim($in['task'] ?? '');
    if ($text === '') return false;
    $priority = $in['priority'] ?? 'medium';
    foreach ($tasks as &$task) {
        if ($task['id'] === $id) {
            $task['text'] = $text;
            $task['subject'] = trim($in['subject'] ?? '');
            $task['priority'] = isset(PRIORITIES[$priority]) ? $priority : 'medium';
            $task['due'] = cleanDate($in['due'] ?? '');
            return true;
        }
    }
    return false;
}

function toggleTask(string $id, array &$tasks): void {
    foreach ($tasks as &$task) {
        if ($task['id'] === $id) {
            $task['completed'] = !$task['completed'];
            return;
        }
    }
}

function deleteTask(string $id, array &$tasks): void {
    foreach ($tasks as $index => $task) {
        if ($task['id'] === $id) {
            array_splice($tasks, $index, 1);
            return;
        }
    }
}

function findTask(string $id, array $tasks): ?array {
    foreach ($tasks as $task) {
        if ($task['id'] === $id) return $task;
    }
    return null;
}

function isOverdue(array $t): bool {
    return !$t['completed'] && $t['due'] !== '' && $t['due'] < date('Y-m-d');
}

function link_to(array $over = []): string {
    $q = array_merge(['filter' => $_GET['filter'] ?? 'all', 'q' => $_GET['q'] ?? '', 'sort' => $_GET['sort'] ?? 'newest'], $over);
    return 'index.php?' . http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== 'all' && $v !== 'newest'));
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE);
}

// Days from today until the due date (negative = in the past)
function daysUntil(string $due): int {
    return (int) round((strtotime($due) - strtotime(date('Y-m-d'))) / 86400);
}

// Human-friendly due label + tone ('late', 'soon' or '')
function dueInfo(array $t): ?array {
    if ($t['due'] === '') return null;
    $days = daysUntil($t['due']);
    $sameYear = substr($t['due'], 0, 4) === date('Y');
    $date = date($sameYear ? 'M j' : 'M j, Y', strtotime($t['due']));

    if ($t['completed']) return ['label' => $date, 'tone' => ''];
    if ($days < 0) return ['label' => 'Overdue · ' . abs($days) . (abs($days) === 1 ? ' day' : ' days'), 'tone' => 'late'];
    if ($days === 0) return ['label' => 'Due today', 'tone' => 'soon'];
    if ($days === 1) return ['label' => 'Due tomorrow', 'tone' => 'soon'];
    if ($days <= 7) return ['label' => "Due in $days days", 'tone' => ''];
    return ['label' => 'Due ' . $date, 'tone' => ''];
}

function icon(string $name, int $size = 18, string $class = ''): string {
    static $paths = [
        'check'    => '<path d="M20 6 9 17l-5-5"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'edit'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'trash'    => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'book'     => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'alert'    => '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>',
        'done'     => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        'layers'   => '<path d="m12 2 10 5-10 5L2 7z"/><path d="m2 17 10 5 10-5M2 12l10 5 10-5"/>',
        'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'     => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'x'        => '<path d="M18 6 6 18M6 6l12 12"/>',
        'inbox'    => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.1 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.9A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.1z"/>',
        'chevron'  => '<path d="m6 9 6 6 6-6"/>',
        'sort'     => '<path d="M3 6h18M6 12h12M10 18h4"/>',
        'checks'   => '<path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/>',
        'cap'      => '<path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/>',
    ];
    return '<svg class="icon ' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}

$editingTask = null;
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid request.');
    }

    $action = $_POST['action'] ?? 'add';
    $tasks = &$_SESSION['tasks'];

    switch ($action) {
        case 'add':
            if (!addTask($_POST, $tasks)) $_SESSION['error'] = 'Task cannot be empty.';
            break;
        case 'update':
            if (!updateTask($_POST['id'] ?? '', $_POST, $tasks)) $_SESSION['error'] = 'Task cannot be empty.';
            break;
        case 'toggle':
            toggleTask($_POST['id'] ?? '', $tasks);
            break;
        case 'delete':
            deleteTask($_POST['id'] ?? '', $tasks);
            break;
        case 'complete_all':
            foreach ($tasks as &$t) $t['completed'] = true;
            unset($t);
            break;
        case 'clear_completed':
            $tasks = array_values(array_filter($tasks, fn($t) => !$t['completed']));
            break;
    }

    // Keep the current filter, search and sort after any action
    parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
    unset($qs['edit']);
    header('Location: index.php' . ($qs ? '?' . http_build_query($qs) : ''));
    exit;
}

if (isset($_GET['edit'])) {
    $editingTask = findTask($_GET['edit'], $_SESSION['tasks']);
}

$all = $_SESSION['tasks'];
$total = count($all);
$done = count(array_filter($all, fn($t) => $t['completed']));
$pending = $total - $done;
$overdue = count(array_filter($all, 'isOverdue'));
$dueToday = count(array_filter($all, fn($t) => !$t['completed'] && $t['due'] === date('Y-m-d')));
$progress = $total > 0 ? round(($done / $total) * 100) : 0;

// Filter, search, sort
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$tasks = array_values(array_filter($all, function ($t) use ($filter, $search) {
    if ($filter === 'pending' && $t['completed']) return false;
    if ($filter === 'done' && !$t['completed']) return false;
    if ($filter === 'overdue' && !isOverdue($t)) return false;
    if ($search !== '' && stripos($t['text'] . ' ' . $t['subject'], $search) === false) return false;
    return true;
}));

usort($tasks, function ($a, $b) use ($sort) {
    if ($sort === 'due') {
        $x = $a['due'] ?: '9999-12-31';
        $y = $b['due'] ?: '9999-12-31';
        return $x <=> $y;
    }
    if ($sort === 'priority') return PRIORITIES[$b['priority']] <=> PRIORITIES[$a['priority']];
    if ($sort === 'az') return strcasecmp($a['text'], $b['text']);
    return $b['created'] <=> $a['created'];
});

$v = $editingTask ?? ['text' => '', 'subject' => '', 'priority' => 'medium', 'due' => ''];

// Sidebar: upcoming deadlines
$upcoming = array_values(array_filter($all, fn($t) => !$t['completed'] && $t['due'] !== ''));
usort($upcoming, fn($a, $b) => $a['due'] <=> $b['due']);
$upcoming = array_slice($upcoming, 0, 4);

// Sidebar: progress per subject (case-insensitive grouping)
$subjects = [];
foreach ($all as $t) {
    if ($t['subject'] === '') continue;
    $key = strtolower($t['subject']);
    $subjects[$key] ??= ['name' => $t['subject'], 'total' => 0, 'done' => 0];
    $subjects[$key]['total']++;
    if ($t['completed']) $subjects[$key]['done']++;
}
uasort($subjects, fn($a, $b) => $b['total'] <=> $a['total']);
$subjects = array_slice($subjects, 0, 5);

// Hero summary line
if ($total === 0) {
    $summary = 'Your planner is empty. Add your first task to get the semester oten rolling.';
} elseif ($pending === 0) {
    $summary = 'All caught up — every task is done. Great work!Oten hahahaha';
} else {
    $parts = [];
    if ($dueToday) $parts[] = "$dueToday due today";
    if ($overdue) $parts[] = "$overdue overdue";
    $summary = "You have $pending pending " . ($pending === 1 ? 'task' : 'tasks')
        . ($parts ? ' — ' . implode(' and ', $parts) : '') . '.';
}

$tabs = [
    'all' => ['All', $total],
    'pending' => ['Pending', $pending],
    'done' => ['Completed', $done],
    'overdue' => ['Overdue', $overdue],
];

$ringCirc = 2 * M_PI * 52;
$ringOffset = $ringCirc * (1 - $progress / 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Task Dashboard</title>

    <script>
        // Apply saved theme before paint to avoid a flash
        (function () {
            try {
                var t = localStorage.getItem('theme');
                if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t;
            } catch (e) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>

<div class="app">

    <header class="topbar">
        <div class="brand">
            <div class="brand-mark"><?= icon('cap', 22) ?></div>
            <div>
                <p class="brand-name">Semester Planner</p>
                <p class="brand-sub">Student Task Dashboard</p>
            </div>
        </div>
        <div class="topbar-right">
            <span class="today-chip"><?= icon('calendar', 16) ?><span data-today><?= date('l, M j') ?></span></span>
            <button type="button" class="icon-btn outlined theme-toggle" id="theme-toggle" aria-label="Toggle dark mode" title="Toggle dark mode">
                <?= icon('moon', 18, 'icon-moon') ?><?= icon('sun', 18, 'icon-sun') ?>
            </button>
        </div>
    </header>

    <section class="hero">
        <div class="hero-copy">
            <p class="hero-eyebrow">Student Task Dashboard</p>
            <h1><span data-greeting>Welcome back</span> 👋</h1>
            <p class="hero-text"><?= e($summary) ?></p>
            <div class="hero-actions">
                <a href="#composer" class="btn btn-white" data-focus-new><?= icon('plus', 16) ?>New task</a>
                <?php if ($overdue): ?>
                    <a href="<?= link_to(['filter' => 'overdue']) ?>" class="btn btn-glass"><?= icon('alert', 16) ?>Review overdue</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="ring" role="img" aria-label="<?= $progress ?>% of tasks completed">
            <svg viewBox="0 0 120 120">
                <circle class="ring-track" cx="60" cy="60" r="52" fill="none" stroke-width="10"/>
                <circle class="ring-fill" cx="60" cy="60" r="52" fill="none" stroke-width="10" stroke-linecap="round"
                        stroke-dasharray="<?= round($ringCirc, 2) ?>" stroke-dashoffset="<?= round($ringOffset, 2) ?>"
                        <?= $progress == 0 ? 'stroke-opacity="0"' : '' ?>/>
            </svg>
            <div class="ring-label">
                <div class="ring-value"><?= $progress ?>%</div>
                <div class="ring-caption">completed</div>
            </div>
        </div>
    </section>

    <section class="stats">
        <div class="stat">
            <div class="stat-icon total"><?= icon('layers', 22) ?></div>
            <div><p class="stat-label">Total tasks</p><p class="stat-value"><?= $total ?></p></div>
        </div>
        <div class="stat">
            <div class="stat-icon done"><?= icon('done', 22) ?></div>
            <div><p class="stat-label">Completed</p><p class="stat-value"><?= $done ?></p></div>
        </div>
        <div class="stat">
            <div class="stat-icon pending"><?= icon('clock', 22) ?></div>
            <div><p class="stat-label">Pending</p><p class="stat-value"><?= $pending ?></p></div>
        </div>
        <div class="stat <?= $overdue ? 'is-alert' : '' ?>">
            <div class="stat-icon overdue"><?= icon('alert', 22) ?></div>
            <div><p class="stat-label">Overdue</p><p class="stat-value"><?= $overdue ?></p></div>
        </div>
    </section>

    <div class="layout">

        <main class="main">

            <form method="POST" class="card composer <?= $editingTask ? 'is-editing' : '' ?>" id="composer">
                <?= csrf() ?>
                <input type="hidden" name="action" value="<?= $editingTask ? 'update' : 'add' ?>">
                <?php if ($editingTask): ?>
                    <input type="hidden" name="id" value="<?= e($editingTask['id']) ?>">
                    <span class="composer-tag"><?= icon('edit', 13) ?>Editing task · press Esc to cancel</span>
                <?php endif; ?>

                <?php if ($error): ?>
                    <p class="alert" role="alert"><?= icon('alert', 16) ?><?= e($error) ?></p>
                <?php endif; ?>

                <div class="composer-main">
                    <div class="composer-icon"><?= icon($editingTask ? 'edit' : 'plus', 18) ?></div>
                    <label for="task-input" class="sr-only">Task</label>
                    <input class="composer-input" id="task-input" type="text" name="task" value="<?= e($v['text']) ?>"
                           placeholder="What do you need to get done?" autocomplete="off" required <?= $editingTask ? 'autofocus' : '' ?>>
                </div>

                <div class="composer-bar">
                    <div class="field field-subject">
                        <?= icon('book', 16) ?>
                        <input class="control" type="text" name="subject" value="<?= e($v['subject']) ?>"
                               placeholder="Subject" maxlength="30" list="subject-options" aria-label="Subject" autocomplete="off">
                        <datalist id="subject-options">
                            <?php foreach ($subjects as $s): ?><option value="<?= e($s['name']) ?>"><?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="segmented" role="radiogroup" aria-label="Priority">
                        <?php foreach (array_keys(PRIORITIES) as $p): ?>
                            <label class="seg-<?= $p ?>">
                                <input type="radio" name="priority" value="<?= $p ?>" <?= $v['priority'] === $p ? 'checked' : '' ?>>
                                <span><?= ucfirst($p) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="field field-date">
                        <?= icon('calendar', 16) ?>
                        <input class="control" type="date" name="due" value="<?= e($v['due']) ?>" aria-label="Due date">
                    </div>

                    <div class="composer-actions">
                        <?php if ($editingTask): ?>
                            <a href="<?= link_to() ?>" class="btn btn-ghost" id="cancel-edit">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">
                            <?= icon($editingTask ? 'check' : 'plus', 16) ?><?= $editingTask ? 'Save changes' : 'Add task' ?>
                        </button>
                    </div>
                </div>
            </form>

            <section class="card">
                <div class="tasks-head">
                    <div class="tasks-head-top">
                        <div>
                            <h2 class="card-title">My Tasks</h2>
                            <p class="card-sub">
                                <?= $search !== '' ? 'Results for “' . e($search) . '”' : 'Everything on your plate this semester' ?>
                            </p>
                        </div>
                        <nav class="tabs" aria-label="Filter tasks">
                            <?php foreach ($tabs as $key => [$label, $count]): ?>
                                <a class="tab <?= $filter === $key ? 'active' : '' ?>" href="<?= link_to(['filter' => $key]) ?>"
                                   <?= $filter === $key ? 'aria-current="page"' : '' ?>>
                                    <?= $label ?>
                                    <span class="tab-count <?= $key === 'overdue' && $count ? 'alert-count' : '' ?>"><?= $count ?></span>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                    </div>

                    <form method="GET" class="toolbar" role="search">
                        <?php if ($filter !== 'all'): ?>
                            <input type="hidden" name="filter" value="<?= e($filter) ?>">
                        <?php endif; ?>
                        <div class="field search">
                            <?= icon('search', 16) ?>
                            <input class="control" type="search" name="q" id="search-input" value="<?= e($search) ?>"
                                   placeholder="Search tasks or subjects" aria-label="Search tasks">
                            <span class="search-end">
                                <?php if ($search !== ''): ?>
                                    <a href="<?= link_to(['q' => '']) ?>" class="icon-btn search-clear" aria-label="Clear search"><?= icon('x', 14) ?></a>
                                <?php else: ?>
                                    <kbd class="kbd">/</kbd>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="field field-sort">
                            <?= icon('sort', 16) ?>
                            <select class="control" name="sort" aria-label="Sort tasks" onchange="this.form.submit()">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                                <option value="due" <?= $sort === 'due' ? 'selected' : '' ?>>Due date</option>
                                <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority</option>
                                <option value="az" <?= $sort === 'az' ? 'selected' : '' ?>>A to Z</option>
                            </select>
                            <?= icon('chevron', 16, 'chevron') ?>
                        </div>
                        <button type="submit" class="sr-only">Search</button>
                    </form>
                </div>

                <?php if (empty($tasks)): ?>
                    <div class="empty">
                        <div class="empty-icon"><?= icon($total ? 'search' : 'inbox', 26) ?></div>
                        <?php if ($total): ?>
                            <h3>Nothing here</h3>
                            <p>No tasks match this view. Try another filter or search.</p>
                        <?php else: ?>
                            <h3>No tasks yet</h3>
                            <p>Add your first task above to start tracking your progress.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <ul class="task-list">
                        <?php foreach ($tasks as $task):
                            $due = dueInfo($task);
                            $isEditing = $editingTask && $editingTask['id'] === $task['id'];
                        ?>
                            <li class="task p-<?= $task['priority'] ?> <?= $task['completed'] ? 'completed' : '' ?> <?= $isEditing ? 'is-editing' : '' ?>">

                                <form method="POST">
                                    <?= csrf() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= e($task['id']) ?>">
                                    <button type="submit" class="check" aria-pressed="<?= $task['completed'] ? 'true' : 'false' ?>"
                                            aria-label="<?= $task['completed'] ? 'Mark as not done' : 'Mark as done' ?>"
                                            title="<?= $task['completed'] ? 'Mark as not done' : 'Mark as done' ?>">
                                        <?= icon('check', 14) ?>
                                    </button>
                                </form>

                                <div class="task-body">
                                    <p class="task-title"><?= e($task['text']) ?></p>
                                    <div class="chips">
                                        <span class="chip chip-prio <?= $task['priority'] ?>"><?= ucfirst($task['priority']) ?></span>
                                        <?php if ($task['subject'] !== ''): ?>
                                            <span class="chip chip-subject"><?= icon('book', 12) ?><?= e($task['subject']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($due): ?>
                                            <span class="chip chip-due <?= $due['tone'] ?>" title="<?= date('l, F j, Y', strtotime($task['due'])) ?>">
                                                <?= icon('calendar', 12) ?><?= $due['label'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="task-actions">
                                    <a class="icon-btn" href="<?= link_to(['edit' => $task['id']]) ?>#composer" aria-label="Edit task" title="Edit"><?= icon('edit', 16) ?></a>
                                    <form method="POST" onsubmit="return confirm('Delete this task?');">
                                        <?= csrf() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e($task['id']) ?>">
                                        <button type="submit" class="icon-btn danger" aria-label="Delete task" title="Delete"><?= icon('trash', 16) ?></button>
                                    </form>
                                </div>

                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($total): ?>
                    <div class="tasks-foot">
                        <span>Showing <?= count($tasks) ?> of <?= $total ?> <?= $total === 1 ? 'task' : 'tasks' ?></span>
                        <div class="bulk">
                            <?php if ($pending): ?>
                                <form method="POST">
                                    <?= csrf() ?>
                                    <input type="hidden" name="action" value="complete_all">
                                    <button type="submit" class="btn btn-ghost btn-sm"><?= icon('checks', 15) ?>Mark all done</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($done): ?>
                                <form method="POST" onsubmit="return confirm('Remove all completed tasks?');">
                                    <?= csrf() ?>
                                    <input type="hidden" name="action" value="clear_completed">
                                    <button type="submit" class="btn btn-ghost btn-sm danger"><?= icon('trash', 15) ?>Clear completed</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

        </main>

        <aside class="side">

            <section class="card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">Up next</h2>
                        <p class="card-sub">Closest deadlines</p>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($upcoming): ?>
                        <ul class="mini-list">
                            <?php foreach ($upcoming as $t): $due = dueInfo($t); ?>
                                <li class="mini-item">
                                    <div class="date-tile <?= $due['tone'] ?>">
                                        <span class="m"><?= date('M', strtotime($t['due'])) ?></span>
                                        <span class="d"><?= date('j', strtotime($t['due'])) ?></span>
                                    </div>
                                    <div class="mini-body">
                                        <p class="mini-title"><?= e($t['text']) ?></p>
                                        <p class="mini-meta <?= $due['tone'] ?>">
                                            <?= $due['label'] ?><?= $t['subject'] !== '' ? ' · ' . e($t['subject']) : '' ?>
                                        </p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="side-empty">No upcoming deadlines. Add a due date to a task to see it here.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">Subjects</h2>
                        <p class="card-sub">Progress by class</p>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($subjects): ?>
                        <ul class="subjects">
                            <?php foreach ($subjects as $s): $pct = round($s['done'] / $s['total'] * 100); ?>
                                <li>
                                    <a class="subject-link" href="<?= link_to(['q' => $s['name']]) ?>" title="Show <?= e($s['name']) ?> tasks">
                                        <div class="subject-top">
                                            <span class="subject-name"><?= e($s['name']) ?></span>
                                            <span class="subject-count"><?= $s['done'] ?>/<?= $s['total'] ?></span>
                                        </div>
                                        <div class="bar"><span style="width: <?= $pct ?>%"></span></div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="side-empty">Tag tasks with a subject to track progress per class.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card card-shortcuts">
                <div class="card-head">
                    <h2 class="card-title">Shortcuts</h2>
                </div>
                <div class="card-body">
                    <ul class="shortcuts">
                        <li>New task <kbd class="kbd">N</kbd></li>
                        <li>Search <kbd class="kbd">/</kbd></li>
                        <li>Cancel editing <kbd class="kbd">Esc</kbd></li>
                    </ul>
                </div>
            </section>

        </aside>
    </div>
</div>

<script>
(function () {
    var root = document.documentElement;
    var taskInput = document.getElementById('task-input');
    var searchInput = document.getElementById('search-input');
    var cancelEdit = document.getElementById('cancel-edit');

    // Greeting + date in the visitor's local time
    var now = new Date();
    var h = now.getHours();
    document.querySelector('[data-greeting]').textContent =
        h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
    document.querySelector('[data-today]').textContent =
        now.toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' });

    // Theme toggle
    document.getElementById('theme-toggle').addEventListener('click', function () {
        var current = root.dataset.theme ||
            (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        var next = current === 'dark' ? 'light' : 'dark';
        root.dataset.theme = next;
        try { localStorage.setItem('theme', next); } catch (e) {}
    });

    // "New task" buttons focus the composer
    document.querySelectorAll('[data-focus-new]').forEach(function (el) {
        el.addEventListener('click', function () { setTimeout(function () { taskInput.focus(); }, 0); });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
        var tag = (e.target.tagName || '').toLowerCase();
        var typing = tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable;

        if (e.key === 'Escape' && cancelEdit && e.target !== searchInput) {
            location.href = cancelEdit.href;
            return;
        }
        if (typing || e.metaKey || e.ctrlKey || e.altKey) return;

        if (e.key === '/') { e.preventDefault(); searchInput.focus(); searchInput.select(); }
        else if (e.key === 'n' || e.key === 'N') { e.preventDefault(); taskInput.focus(); }
    });

    // Keep scroll position when an action reloads the page
    try {
        var y = sessionStorage.getItem('scrollY');
        if (y !== null && !location.hash) window.scrollTo(0, +y);
        sessionStorage.removeItem('scrollY');
    } catch (e) {}

    document.querySelectorAll('form[method="POST"]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (e.defaultPrevented) return;
            try { sessionStorage.setItem('scrollY', String(window.scrollY)); } catch (err) {}
        });
    });
})();
</script>

</body>
</html>
