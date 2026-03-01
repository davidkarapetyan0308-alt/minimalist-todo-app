<?php

// Подключение к базе данных
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '8889');
define('DB_NAME', 'minimalist_todo');
define('DB_USER', 'root');
define('DB_PASS', 'root');

function getDB()
{
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Ошибка подключения: " . $e->getMessage());
    }
}

session_start();

// --- ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ---

function validateTaskTitle(string $title): array {
    $title = trim($title);
    if (mb_strlen($title) < 3) {
        return ['ok' => false, 'error' => "Название слишком короткое (минимум 3 символа)!"];
    }
    return ['ok' => true, 'error' => ""];
}

function validatePriority(string $priority): array {
    $allowed = ['low', 'medium', 'high'];
    if (!in_array($priority, $allowed)) {
        return ['ok' => false, 'error' => "Некорректный приоритет!"];
    }
    return ['ok' => true, 'error' => ""];
}

// --- ФУНКЦИИ РАБОТЫ С БД ---

function getAllTasksFromDB() {
    $pdo = getDB();
    $sql = "SELECT * FROM tasks ORDER BY created_at DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function addTaskToDB($title, $priority) {
    $pdo = getDB();
    $sql = "INSERT INTO tasks (title, priority, status) VALUES (:title, :priority, 'todo')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':title' => $title, ':priority' => $priority]);
    return $pdo->lastInsertId();
}

function deleteTaskFromDB($id) {
    $pdo = getDB();
    $sql = "DELETE FROM tasks WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
}

function toggleTaskInDB($id) {
    $pdo = getDB();
    $sql = "UPDATE tasks SET status = IF(status = 'todo', 'done', 'todo') WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
}

function getTasksSorted($orderBy = 'created_at', $direction = 'DESC')
{
    $pdo = getDB();
    $allowedFields = ['title', 'priority', 'created_at'];
    if (!in_array($orderBy, $allowedFields)) {
        $orderBy = 'created_at';
    }
    $direction = ($direction === 'ASC') ? 'ASC' : 'DESC';

    $sql = "SELECT * FROM tasks ORDER BY $orderBy $direction";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $title = $_POST['title'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';

    $titleCheck = validateTaskTitle($title);
    $prioCheck = validatePriority($priority);

    if ($titleCheck['ok'] && $prioCheck['ok']) {
        addTaskToDB(trim($title), $priority);
        header("Location: index.php");
        exit;
    } else {
        $errorMsg = $titleCheck['error'] ?: $prioCheck['error'];
    }
}

if (isset($_GET['action'], $_GET['id'])) {
    $id = $_GET['id'];
    if ($_GET['action'] === 'delete') {
        deleteTaskFromDB($id);
    } elseif ($_GET['action'] === 'toggle') {
        toggleTaskInDB($id);
    }
    header("Location: index.php");
    exit;
}

$sortBy = $_GET['sort'] ?? 'created_at';
$sortDir = $_GET['dir'] ?? 'DESC';
$tasks = getTasksSorted($sortBy, $sortDir);
$displayTasks = $tasks;

$statusFilter = $_GET['status'] ?? 'all';
if ($statusFilter !== 'all') {
    $displayTasks = array_filter($displayTasks, fn($t) => $t['status'] === $statusFilter);
}

$searchQuery = $_GET['q'] ?? '';
if (!empty($searchQuery)) {
    $displayTasks = array_filter($displayTasks, function($t) use ($searchQuery) {
        return mb_stripos($t['title'], $searchQuery) !== false;
    });
}

$totalCount = count($tasks);
$doneCount = count(array_filter($tasks, fn($t) => $t['status'] === 'done'));
$todoCount = $totalCount - $doneCount;

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minimalist To-Do</title>

    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
            max-width:600px;
            margin:60px auto;
            padding:0 20px;
            line-height:1.6;
            position:relative;
            overflow-x:hidden;
        }

        /* ==== ФОН ==== */
        .parallax-bg{
            position:fixed;
            inset:0;
            background:url("ChatGPT Image 25 февр. 2026 г., 23_03_52.png") center/contain no-repeat;
            opacity:0.08;
            z-index:-1;
            transition:transform .15s ease-out;
            pointer-events:none;
        }

        /* Заголовок */
        .header-container{
            position:relative;
            display:inline-block;
            margin-bottom:40px;
        }

        h1{
            font-size:32px;
            font-weight:800;
            letter-spacing:-1px;
            margin:0;
        }

        /* Человечек под буквой S */
        .hanging-man {
            position: absolute;
            width: 15px;
            top: 18px;
            left: 78px;
            z-index: -1;
            pointer-events: none;
        }

        /* Статистика */
        .stats{
            font-size:12px;
            color:#888;
            border-bottom:1px solid #eee;
            padding-bottom:10px;
            margin-bottom:30px;
            text-transform:uppercase;
            letter-spacing:.5px;
        }

        .stats b{color:#000;}

        .error{
            color:#ff3b30;
            font-size:13px;
            margin-bottom:20px;
        }

        nav{
            margin-bottom:30px;
            display:flex;
            align-items:center;
            gap:15px;
            font-size:13px;
            flex-wrap:wrap;
        }

        nav a{
            text-decoration:none;
            color:#888;
        }

        nav a.active{
            color:#000;
            font-weight:bold;
        }

        /* КНОПКИ СОРТИРОВКИ */
        .sort-buttons{
            margin:20px 0;
            padding:15px;
            background:#f8f9fa;
            border-radius:10px;
            display:flex;
            gap:10px;
            flex-wrap:wrap;
            align-items:center;
        }

        .sort-buttons strong{
            margin-right:5px;
            font-size:13px;
        }

        .sort-btn{
            padding:8px 16px;
            background:#fff;
            border:1px solid #e0e0e0;
            border-radius:6px;
            text-decoration:none;
            color:#000;
            font-size:13px;
            font-weight:500;
            transition:all .2s;
        }

        .sort-btn:hover{
            background:#000;
            color:#fff;
            border-color:#000;
        }

        .sort-btn.active{
            background:#000;
            color:#fff;
            border-color:#000;
        }

        form.add-form{
            display:flex;
            gap:10px;
            margin-bottom:40px;
            flex-wrap:wrap;
        }

        input[type="text"],select{
            border:1px solid #e0e0e0;
            padding:12px;
            flex-grow:1;
            border-radius:6px;
            outline:none;
            min-width:150px;
        }

        input:focus{
            border-color:#000;
        }

        button{
            background:#000;
            color:#fff;
            border:none;
            padding:10px 25px;
            cursor:pointer;
            font-weight:600;
            border-radius:6px;
        }

        button:hover{
            background:#333;
        }

        .task-item{
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:15px 0;
            border-bottom:1px solid #f5f5f5;
            transition:.2s;
            gap:10px;
        }

        .task-item.done .task-text{
            text-decoration:line-through;
            color:#ccc;
        }

        .task-text{
            display:flex;
            align-items:center;
            gap:10px;
            flex:1;
            min-width:0;
        }

        .task-text span{
            word-break:break-word;
        }

        .actions{
            display:flex;
            gap:8px;
            flex-shrink:0;
        }

        .actions a{
            text-decoration:none;
            font-size:12px;
            font-weight:600;
            color:#000;
            white-space:nowrap;
        }

        .actions a.delete{
            color:#ff3b30;
        }

        .priority-dot{
            display:inline-block;
            width:8px;
            height:8px;
            border-radius:50%;
            flex-shrink:0;
        }

        .p-high{background:#ff3b30;}
        .p-medium{background:#ffcc00;}
        .p-low{background:#34c759;}

        .task-pic{
            width:50px;
            height:50px;
            object-fit:cover;
            border-radius:8px;
            flex-shrink:0;
        }

        /* ==== МОБИЛЬНАЯ ВЕРСИЯ ==== */
        @media (max-width: 640px) {
            body{
                margin:30px auto;
                padding:0 15px;
            }

            h1{
                font-size:24px;
            }

            .hanging-man{
                width:12px;
                top:14px;
                left:58px;
            }

            nav{
                gap:10px;
            }

            nav form{
                width:100%;
            }

            nav form input{
                width:100% !important;
            }

            .sort-buttons{
                padding:10px;
            }

            .sort-btn{
                padding:6px 12px;
                font-size:12px;
            }

            form.add-form{
                flex-direction:column;
            }

            form.add-form input,
            form.add-form select,
            form.add-form button{
                width:100%;
            }

            .task-pic{
                width:40px;
                height:40px;
            }

            .task-item{
                flex-wrap:wrap;
            }

            .actions{
                width:100%;
                justify-content:flex-end;
                margin-top:5px;
            }
        }

        @media (max-width: 400px) {
            .task-text{
                font-size:14px;
            }

            .task-pic{
                width:35px;
                height:35px;
            }

            .stats{
                font-size:11px;
            }
        }
    </style>
</head>

<body>

<div class="parallax-bg" id="parallax"></div>

<div class="header-container">
    <img src="image (23.jpg" class="hanging-man" alt="">
    <h1>Tasks.</h1>
</div>

<div class="stats">
    Total: <b><?= $totalCount ?></b>
    &nbsp; Done: <b><?= $doneCount ?></b>
    &nbsp; Todo: <b><?= $todoCount ?></b>
</div>

<nav>
    <form method="GET" style="margin:0; display:flex; gap:5px; flex:1;">
        <input type="text" name="q" placeholder="Find..." value="<?= htmlspecialchars($searchQuery) ?>" style="padding:5px; flex:1;">
        <button type="submit" style="padding:5px 10px; font-size:12px;">search</button>
    </form>

    <a href="?status=all" class="<?= $statusFilter==='all'?'active':'' ?>">All</a>
    <a href="?status=todo" class="<?= $statusFilter==='todo'?'active':'' ?>">Todo</a>
    <a href="?status=done" class="<?= $statusFilter==='done'?'active':'' ?>">Done</a>
</nav>

<!-- КНОПКИ СОРТИРОВКИ -->
<div class="sort-buttons">
    <strong>SORT:</strong>
    <a href="?sort=created_at&dir=DESC" class="sort-btn <?= ($sortBy==='created_at' && $sortDir==='DESC')?'active':'' ?>">
        📅 New First
    </a>
    <a href="?sort=created_at&dir=ASC" class="sort-btn <?= ($sortBy==='created_at' && $sortDir==='ASC')?'active':'' ?>">
        📅 Old First
    </a>
    <a href="?sort=priority&dir=DESC" class="sort-btn <?= ($sortBy==='priority')?'active':'' ?>">
        🔴 Priority
    </a>
</div>

<form method="POST" class="add-form">
    <input type="text" name="title" placeholder="New task..." required>
    <select name="priority" style="width:auto; flex-grow:0;">
        <option value="low">Low</option>
        <option value="medium" selected>Mid</option>
        <option value="high">High</option>
    </select>
    <button type="submit" name="add_task">Add</button>
</form>

<?php if(isset($errorMsg)): ?>
    <p class="error"><?= $errorMsg ?></p>
<?php endif; ?>

<div class="task-list">
    <?php foreach($displayTasks as $task): ?>
        <div class="task-item <?= $task['status']==='done'?'done':'' ?>">
            <div class="task-text">
                <img src="image (2).jpg" class="task-pic" alt="">
                <span class="priority-dot p-<?= $task['priority'] ?>"></span>
                <span><?= htmlspecialchars($task['title']) ?></span>
            </div>
            <div class="actions">
                <a href="?action=toggle&id=<?= $task['id'] ?>">
                    <?= $task['status']==='done'?'Undo':'Done' ?>
                </a>
                <a href="?action=delete&id=<?= $task['id'] ?>" class="delete" onclick="return confirm('Удалить?')">
                    Del
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    const bg=document.getElementById('parallax');

    window.addEventListener('scroll',()=>{
        bg.style.transform=`translateY(${window.scrollY*0.06}px)`;
    });

    window.addEventListener('mousemove',(e)=>{
        const x=(window.innerWidth/2-e.clientX)/100;
        const y=(window.innerHeight/2-e.clientY)/100;
        bg.style.transform=`translate(${x}px,${y}px)`;
    });
</script>

</body>
</html>