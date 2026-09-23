<?php
session_start();

if (!isset($_SESSION['tasks'])) {
    $_SESSION['tasks'] = [];
}

function addTask(string $task, array &$tasks): bool {
    $task = trim($task);
    if ($task === '') return false;
    $tasks[] = [
        'id' => uniqid('task_', true),
        'text' => $task,
        'completed' => false
    ];
    return true;
}

function updateTask(string $id, string $text, array &$tasks): bool {
    $text = trim($text);
    if ($text === '') return false;

    foreach ($tasks as &$task) {
        if ($task['id'] === $id) {
            $task['text'] = $text;
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

$editingTask = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';

    switch ($action) {
        case 'add':
            if (!addTask($_POST['task'] ?? '', $_SESSION['tasks'])) {
                $error = 'Task cannot be empty.';
            }
            break;
        case 'update':
            if (!updateTask($_POST['id'] ?? '', $_POST['task'] ?? '', $_SESSION['tasks'])) {
                $error = 'Task cannot be empty.';
            }
            break;
        case 'toggle':
            toggleTask($_POST['id'] ?? '', $_SESSION['tasks']);
            break;
        case 'delete':
            deleteTask($_POST['id'] ?? '', $_SESSION['tasks']);
            break;
    }

    header('Location: index.php');
    exit;
}

if (isset($_GET['edit'])) {
    $editingTask = findTask($_GET['edit'], $_SESSION['tasks']);
}

$tasks = $_SESSION['tasks'];
$total = count($tasks);
$done = count(array_filter($tasks, fn($task) => $task['completed']));
$pending = $total - $done;
$progress = $total > 0 ? round(($done / $total) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Task Dashboard</title>

    <style>
        :root {
            --primary: #1B2A4A;
            --secondary: #4A5670;
            --paper: #F4EFE3;
            --card: #FFFDF8;
            --border: #C9BFA5;
            --red: #A13D2C;
            --green: #5B7B54;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 20px;
            background: var(--paper);
            color: var(--primary);
            font-family: Arial, sans-serif;
        }

        .dashboard {
            max-width: 900px;
            margin: auto;
        }

        header {
            margin-bottom: 30px;
        }

        .kicker {
            color: var(--red);
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        h1 {
            margin: 0;
            font-size: 34px;
        }

        .subtitle {
            color: var(--secondary);
            margin-top: 8px;
        }

        /* Dashboard cards */
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.05);
        }

        .stat-card h3 {
            margin: 0;
            font-size: 14px;
            color: var(--secondary);
        }

        .stat-number {
            font-size: 30px;
            font-weight: bold;
            margin-top: 8px;
        }

        .progress-section {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .progress-bar {
            height: 12px;
            background: #E2DDCF;
            border-radius: 20px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            width: <?= $progress ?>%;
            background: var(--green);
            border-radius: 20px;
        }

        .task-container {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 25px;
        }

        .task-container h2 {
            margin-top: 0;
        }

        .task-form {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }

        .task-form input {
            flex: 1;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 15px;
        }

        button, .btn-cancel {
            border: none;
            border-radius: 6px;
            padding: 12px 18px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-cancel {
            background: #ddd;
            color: var(--primary);
        }

        .task-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .task-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 0;
            border-bottom: 1px solid var(--border);
        }

        .task-row:last-child {
            border-bottom: none;
        }

        .toggle-form, .delete-form {
            margin: 0;
        }

        .check-btn {
            width: 25px;
            height: 25px;
            padding: 0;
            border: 2px solid var(--secondary);
            border-radius: 50%;
            background: white;
            color: white;
        }

        .completed .check-btn {
            background: var(--green);
            border-color: var(--green);
        }

        .task-text {
            flex: 1;
        }

        .completed .task-text {
            text-decoration: line-through;
            color: var(--secondary);
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .edit {
            color: var(--secondary);
            text-decoration: none;
            font-size: 13px;
        }

        .delete {
            background: none;
            color: var(--red);
            padding: 0;
            font-size: 13px;
        }

        .empty {
            text-align: center;
            padding: 25px;
            color: var(--secondary);
        }

        .error {
            background: #F6E2DC;
            color: var(--red);
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        @media (max-width: 600px) {
            .stats {
                grid-template-columns: 1fr;
            }

            .task-form {
                flex-direction: column;
            }

            .task-row {
                flex-wrap: wrap;
            }

            .actions {
                width: 100%;
                margin-left: 37px;
            }
        }
    </style>
</head>
<body>

<div class="dashboard">

    <header>
        <p class="kicker">SEMESTER PLANNER</p>
        <h1>Student Task Dashboard</h1>
        <p class="subtitle">Manage your school tasks and track your progress.</p>
    </header>

    <!-- Dashboard Statistics -->
    <section class="stats">
        <div class="stat-card">
            <h3>Total Tasks</h3>
            <div class="stat-number"><?= $total ?></div>
        </div>

        <div class="stat-card">
            <h3>Completed</h3>
            <div class="stat-number"><?= $done ?></div>
        </div>

        <div class="stat-card">
            <h3>Pending</h3>
            <div class="stat-number"><?= $pending ?></div>
        </div>
    </section>

    <!-- Progress -->
    <section class="progress-section">
        <div class="progress-header">
            <span>Overall Progress</span>
            <span><?= $progress ?>%</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
    </section>

    <!-- Task Manager -->
    <section class="task-container">
        <h2><?= $editingTask ? 'Edit Task' : 'My Tasks' ?></h2>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" class="task-form">
            <?php if ($editingTask): ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= htmlspecialchars($editingTask['id']) ?>">
                <input type="text" name="task" value="<?= htmlspecialchars($editingTask['text']) ?>" required>
                <button type="submit" class="btn-primary">Save</button>
                <a href="index.php" class="btn-cancel">Cancel</a>
            <?php else: ?>
                <input type="hidden" name="action" value="add">
                <input type="text" name="task" placeholder="What do you need to get done?" required>
                <button type="submit" class="btn-primary">Add Task</button>
            <?php endif; ?>
        </form>

        <ul class="task-list">
            <?php if (empty($tasks)): ?>
                <li class="empty">No tasks yet. Add your first task above.</li>
            <?php endif; ?>

            <?php foreach ($tasks as $task): ?>
                <li class="task-row <?= $task['completed'] ? 'completed' : '' ?>">

                    <form method="POST" class="toggle-form">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($task['id']) ?>">
                        <button type="submit" class="check-btn">
                            <?= $task['completed'] ? '✓' : '' ?>
                        </button>
                    </form>

                    <span class="task-text">
                        <?= htmlspecialchars($task['text']) ?>
                    </span>

                    <div class="actions">
                        <a class="edit" href="?edit=<?= urlencode($task['id']) ?>">Edit</a>

                        <form method="POST" class="delete-form" onsubmit="return confirm('Delete this task?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($task['id']) ?>">
                            <button type="submit" class="delete">Delete</button>
                        </form>
                    </div>

                </li>
            <?php endforeach; ?>
        </ul>
    </section>

</div>

</body>
</html>
