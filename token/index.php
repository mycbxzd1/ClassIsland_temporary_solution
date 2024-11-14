<?php
/*
    如果是服务器,请在目录下输入composer install拉取vendor/autoload.php,如果是虚拟主机,请下载虚拟主机专用包
*/
require 'vendor/autoload.php';

use Ramsey\Uuid\Uuid;

header('Content-Type: application/json'); 
$databasePath = __DIR__ . '/db.sqlite';

$adminPassword = '';//管理员密码
$secretPassword = '';//数据库密码

// 创建或打开 SQLite 数据库
$pdo = new PDO('sqlite:' . $databasePath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 检查并创建表结构
function createTables($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            value INTEGER DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            token TEXT NOT NULL UNIQUE,
            timestamp DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
    ");
}

// 如果数据库文件不存在，创建数据库和表结构
if (!file_exists($databasePath)) {
    createTables($pdo);
} else {
    // 检查表结构是否存在，不存在则创建
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    if ($tables->fetch() === false) {
        createTables($pdo);
    } else {
        // 检查是否需要添加新的列
        $columns = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('value', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN value INTEGER DEFAULT 0");
        }
    }
}

function hashPassword($password) {
    return hash('sha256', $password);
}

function authenticateUser($name, $password, $pdo) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE name = ?');
    $stmt->execute([$name]);
    $user = $stmt->fetch();
    if ($user && $user['password'] == hashPassword($password)) {
        return $user;
    }
    return null;
}

function refreshTokenIfNeeded($userId, $pdo) {
    $stmt = $pdo->prepare('SELECT * FROM tokens WHERE user_id = ?');
    $stmt->execute([$userId]);
    $tokenRecord = $stmt->fetch();
    
    if ($tokenRecord) {
        $timestamp = strtotime($tokenRecord['timestamp']);
        if (time() - $timestamp > 180) { // 超过3分钟
            $newToken = Uuid::uuid4()->toString();
            $newTimestamp = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare('UPDATE tokens SET token = ?, timestamp = ? WHERE user_id = ?');
            $stmt->execute([$newToken, $newTimestamp, $userId]);
            return $newToken;
        } else {
            return $tokenRecord['token'];
        }
    } else {
        $newToken = Uuid::uuid4()->toString();
        $newTimestamp = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare('INSERT INTO tokens (user_id, token, timestamp) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $newToken, $newTimestamp]);
        return $newToken;
    }
}

function handleRequest($handler) {
    try {
        error_log("Handler started"); // 添加日志
        $handler();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage(),'code'=>'201'], JSON_PRETTY_PRINT);
        error_log("Error: " . $e->getMessage()); // 添加日志
    }
    exit;
}

$api = $_GET['api'] ?? null;

if (!$api) {
    http_response_code(400);
    echo json_encode(['error' => 'no api','code'=>'201'], JSON_PRETTY_PRINT);
    exit;
}

switch ($api) {
    case 'register':
        handleRequest(function() use ($pdo, $adminPassword) {
            if (empty($_GET['pwd']) || $_GET['pwd'] !== $adminPassword) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid registration password','code'=>'201'], JSON_PRETTY_PRINT);
                return;
            }
            $name = $_GET['name'] ?? null;
            $password = $_GET['password'] ?? null;
            $value = $_GET['value'] ?? 0;
            if (!$name || !$password) {
                http_response_code(400);
                echo json_encode(['error' => 'Name and password are required','code'=>'201'], JSON_PRETTY_PRINT);
                return;
            }
            $hashedPassword = hashPassword($password);

            // 检查用户是否存在
            $stmt = $pdo->prepare('SELECT * FROM users WHERE name = ?');
            $stmt->execute([$name]);
            $user = $stmt->fetch();

            if ($user) {
                // 用户存在，更新信息
                $stmt = $pdo->prepare('UPDATE users SET password = ?, value = ? WHERE name = ?');
                $stmt->execute([$hashedPassword, $value, $name]);
                echo json_encode(['message' => 'User updated successfully','code'=>'200'], JSON_PRETTY_PRINT);
            } else {
                // 用户不存在，插入新记录
                $stmt = $pdo->prepare('INSERT INTO users (name, password, value) VALUES (?, ?, ?)');
                $stmt->execute([$name, $hashedPassword, $value]);
                echo json_encode(['message' => 'User registered successfully','code'=>'200'], JSON_PRETTY_PRINT);
            }
        });
        break;

    case 'refresh':
        handleRequest(function() use ($pdo) {
            $stmt = $pdo->query('SELECT * FROM users');
            $users = $stmt->fetchAll();
            foreach ($users as $user) {
                $newToken = Uuid::uuid4()->toString();
                $timestamp = date('Y-m-d H:i:s');
                $stmt = $pdo->prepare('DELETE FROM tokens WHERE user_id = ?');
                $stmt->execute([$user['id']]);
                $stmt = $pdo->prepare('INSERT INTO tokens (user_id, token, timestamp) VALUES (?, ?, ?)');
                $stmt->execute([$user['id'], $newToken, $timestamp]);
            }
            echo json_encode(['message' => 'Tokens refreshed for all users','code'=>'200'], JSON_PRETTY_PRINT);
        });
        break;

    case 'get':
        handleRequest(function() use ($pdo) {
            $name = $_GET['name'] ?? null;
            $password = $_GET['password'] ?? null;
            if (!$name || !$password) {
                http_response_code(400);
                echo json_encode(['error' => 'Name and password are required','code'=>'201'], JSON_PRETTY_PRINT);
                return;
            }
            $user = authenticateUser($name, $password, $pdo);
            if (!$user) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid username or password','code'=>'201'], JSON_PRETTY_PRINT);
                return;
            }
            $token = refreshTokenIfNeeded($user['id'], $pdo);
            echo json_encode(['token' => $token, 'value' => $user['value'],'code'=>'200'], JSON_PRETTY_PRINT);
        });
        break;

    case 'check':
        handleRequest(function() use ($pdo) {
            $token = $_GET['token'] ?? null;
            if (!$token) {
                http_response_code(400);
                echo json_encode(['error' => 'Token is required','code'=>'201'], JSON_PRETTY_PRINT);
                return;
            }
            $stmt = $pdo->prepare('SELECT t.*, u.value FROM tokens t JOIN users u ON t.user_id = u.id WHERE t.token = ?');
            $stmt->execute([$token]);
            $tokenRecord = $stmt->fetch();
            if ($tokenRecord) {
                $timestamp = strtotime($tokenRecord['timestamp']);
                if (time() - $timestamp > 180) { // 超过3分钟
                    $newToken = Uuid::uuid4()->toString();
                    $newTimestamp = date('Y-m-d H:i:s');
                    $stmt = $pdo->prepare('UPDATE tokens SET token = ?, timestamp = ? WHERE id = ?');
                    $stmt->execute([$newToken, $newTimestamp, $tokenRecord['id']]);
                    echo json_encode(['valid' => true, 'value' => $tokenRecord['value'],'code'=>'200'], JSON_PRETTY_PRINT);
                } else {
                    echo json_encode(['valid' => true, 'value' => $tokenRecord['value'],'code'=>'200'], JSON_PRETTY_PRINT);
                }
            } else {
                echo json_encode(['valid' => false,'code'=>'201'], JSON_PRETTY_PRINT);
            }
        });
        break;

    case 'delete':
        handleRequest(function() use ($pdo, $adminPassword) {
            if (empty($_GET['pwd']) || $_GET['pwd'] !== $adminPassword) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid deletion password','code'=>'201'], JSON_PRETTY_PRINT);
                return;
            }
            $name = $_GET['name'] ?? null;
            if (!$name) {
                http_response_code(400);
                echo json_encode(['error' => 'Name is required','code'=>'201'], JSON_PRETTY_PRINT);
                return;
            }
            $stmt = $pdo->prepare('DELETE FROM users WHERE name = ?');
            $stmt->execute([$name]);
            $stmt = $pdo->prepare('DELETE FROM tokens WHERE user_id = (SELECT id FROM users WHERE name = ?)');
            $stmt->execute([$name]);
            echo json_encode(['message' => 'User and associated token deleted successfully','code'=>'200'], JSON_PRETTY_PRINT);
        });
        break;

    case 'list':
        handleRequest(function() use ($pdo, $adminPassword) {
            if (empty($_GET['pwd']) || $_GET['pwd'] !== $adminPassword) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid password','code'=>'201'], JSON_PRETTY_PRINT);
                return;
            }
            $stmt = $pdo->query('SELECT name, password, value FROM users');
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['list'=>$users,'code'=>'200'], JSON_PRETTY_PRINT);
        });
        break;

    case 'list_token':
        handleRequest(function() use ($pdo, $adminPassword) {
            if (empty($_GET['pwd']) || $_GET['pwd'] !== $adminPassword) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid password','code'=>'201'], JSON_PRETTY_PRINT);
                return;
            }
            $stmt = $pdo->query('SELECT t.token, u.name, u.value FROM tokens t JOIN users u ON t.user_id = u.id');
            $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['token'=>$tokens,'code'=>'200'], JSON_PRETTY_PRINT);
        });
        break;

    case 'change':
        handleRequest(function() use ($pdo, $adminPassword) {
            if (empty($_GET['pwd']) || $_GET['pwd'] !== $adminPassword) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid password','code'=>'201'], JSON_PRETTY_PRINT);
                return;
            }
            $name = $_GET['name'] ?? null;
            $password = $_GET['password'] ?? null;
            $value = $_GET['value'] ?? null;
            if (!$name || (!$password && $value === null)) {
                http_response_code(400);
                echo json_encode(['error' => 'Name and either password or value are required','code'=>'201'], JSON_PRETTY_PRINT);
                return;
            }
            if ($password) {
                $hashedPassword = hashPassword($password);
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE name = ?');
                $stmt->execute([$hashedPassword, $name]);
            }
            if ($value !== null) {
                $stmt = $pdo->prepare('UPDATE users SET value = ? WHERE name = ?');
                $stmt->execute([$value, $name]);
            }
            echo json_encode(['message' => 'User information updated successfully','code'=>'200'], JSON_PRETTY_PRINT);
        });
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid API action','code'=>'201'], JSON_PRETTY_PRINT);
}

// 清理缓冲区，完成请求
ob_end_flush();
fastcgi_finish_request();
?>
