<?php
// 定义全局变量，存储授权地址和数据库加密密码
define('AUTH_URL', '');//token授权地址,必填
define('DB_PASSWORD', '');// 数据库密码,不知道不填会怎么样

// 数据库路径
define('DB_PATH', __DIR__ . '/data/devices.db');

// 验证token的方法
function checkToken($token) {
    $url = AUTH_URL . '?api=check&token=' . $token;
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    return $data;
}

// 初始化数据库
function initDatabase() {
    $db = new SQLite3(DB_PATH);
    $db->exec("PRAGMA key = '" . DB_PASSWORD . "'");
    $db->exec("CREATE TABLE IF NOT EXISTS devices (id TEXT PRIMARY KEY, logs TEXT)");
    return $db;
}

// 记录日志
function logAction($db, $name, $action) {
    $stmt = $db->prepare('INSERT INTO devices (id, logs) VALUES (:id, :logs) ON CONFLICT(id) DO UPDATE SET logs = logs || :logs');
    $logs = date('Y-m-d H:i:s') . ' - ' . $action . "\n";
    $stmt->bindValue(':id', $name, SQLITE3_TEXT);
    $stmt->bindValue(':logs', $logs, SQLITE3_TEXT);
    $stmt->execute();
}

// 注册新设备接口
function registerNewDevice($name, $token) {
    if (empty($token)) {
        return json_encode(['error' => '缺少参数: token']);
    }
    $tokenData = checkToken($token);
    if (!$tokenData['valid'] || $tokenData['value'] > -1) {
        return json_encode(['error' => '无效的令牌或权限不足']);
    }

    if (empty($name)) {
        return json_encode(['error' => '缺少参数: name']);
    }

    $dir = __DIR__ . "/data/$name";
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
        file_put_contents("$dir/policy.json", json_encode([
            'DisableProfileClassPlanEditing' => false,
            'DisableProfileTimeLayoutEditing' => false,
            'DisableProfileSubjectsEditing' => false,
            'DisableProfileEditing' => false,
            'DisableSettingsEditing' => false,
            'DisableSplashCustomize' => false,
            'DisableDebugMenu' => false,
            'AllowExitManagement' => false
        ], JSON_PRETTY_PRINT));

        // 记录日志
        $db = initDatabase();
        logAction($db, $name, 'Registered new device');

        return json_encode(['status' => '设备注册成功', 'name' => $name]);
    } else {
        return json_encode(['error' => '设备已被注册,请更换ID']);
    }
}

// 获取设备列表接口
function getDeviceList($token) {
    if (empty($token)) {
        return json_encode(['error' => '缺少参数: token']);
    }
    $tokenData = checkToken($token);
    if (!$tokenData['valid']) {
        return json_encode(['error' => '无效的令牌']);
    }

    $db = initDatabase();
    $result = $db->query('SELECT id FROM devices');
    $devices = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $devices[] = $row['id'];
    }

    return json_encode(['status' => '设备列表', 'devices' => $devices]);
}

// 初始化服务接口

function initService($url, $organizationName, $token) {
    if (empty($token) || empty($url)) {
        return json_encode(['error' => '缺少参数 token 或 URL']);
    }
    $tokenData = checkToken($token);
    if (!$tokenData['valid'] || $tokenData['value'] > -1) {
        return json_encode(['error' =>'无效的令牌或权限不足']);
    }

    $manifestPath = __DIR__ . '/manifest.json';
    if (!file_exists($manifestPath)) {
        $manifest = [
            'ClassPlanSource' => ['Value' => 'https://test.myxcbdz.asia/data/{id}/ClassPlan.json', 'Version' => 0],
            'TimeLayoutSource' => ['Value' => 'https://test.myxcbdz.asia/data/{id}/TimeLayout.json', 'Version' => 0],
            'SubjectsSource' => ['Value' => 'https://test.myxcbdz.asia/data/{id}/Subjects.json', 'Version' => 0],
            'DefaultSettingsSource' => ['Value' => 'https://test.myxcbdz.asia/data/{id}/DefaultSettings.json', 'Version' => 0],
            'PolicySource' => ['Value' => 'https://test.myxcbdz.asia/data/{id}/Policy.json', 'Version' => 0],
            'ServerKind' => 0,
            'OrganizationName' => ''
        ];
    } else {
        $manifest = json_decode(file_get_contents($manifestPath), true);
    }

    $manifest['OrganizationName'] = $organizationName;
    $manifest['ClassPlanSource']['Value'] = $url . '/{id}/ClassPlan.json';
    $manifest['TimeLayoutSource']['Value'] = $url . '/{id}/TimeLayout.json';
    $manifest['SubjectsSource']['Value'] = $url . '/{id}/Subjects.json';
    $manifest['DefaultSettingsSource']['Value'] = $url . '/{id}/DefaultSettings.json';
    $manifest['PolicySource']['Value'] = $url . '/{id}/Policy.json';

    // 增加版本号
    $manifest['ClassPlanSource']['Version'] += 1;
    $manifest['TimeLayoutSource']['Version'] += 1;
    $manifest['SubjectsSource']['Version'] += 1;
    $manifest['DefaultSettingsSource']['Version'] += 1;
    $manifest['PolicySource']['Version'] += 1;

    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // 记录日志
    $db = initDatabase();
    logAction($db, 'system', 'Service initialized with URL: ' . $url);

    return json_encode(['status' => 'Service initialized']);
}

// 更新课程接口
function updateClassPlan($name, $file, $token) {
    if (empty($token) || empty($name) || empty($file)) {
        return json_encode(['error' => '参数缺失:token,name或file']);
    }
    $tokenData = checkToken($token);
    if (!$tokenData['valid']) {
        return json_encode(['error' => '无效的令牌']);
    }

    $dir = __DIR__ . "/data/$name";
    if (file_exists($dir)) {
        move_uploaded_file($file['tmp_name'], "$dir/ClassPlan.json");

        // 更新manifest.json
        $manifestPath = __DIR__ . '/manifest.json';
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $manifest['ClassPlanSource']['Version'] += 1;
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 记录日志
        $db = initDatabase();
        logAction($db, $name, 'Class plan updated');

        return json_encode(['status' => '课程表上传', 'name' => $name]);
    } else {
        return json_encode(['error' => '设备未找到']);
    }
}

// 更新时间表接口
function updateTimeLayout($name, $file, $token) {
    if (empty($token) || empty($name) || empty($file)) {
        return json_encode(['error' => '参数缺失:token,name或file']);
    }
    $tokenData = checkToken($token);
    if (!$tokenData['valid']) {
        return json_encode(['error' => '无效的令牌']);
    }

    $dir = __DIR__ . "/data/$name";
    if (file_exists($dir)) {
        move_uploaded_file($file['tmp_name'], "$dir/TimeLayout.json");

        // 更新manifest.json
        $manifestPath = __DIR__ . '/manifest.json';
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $manifest['TimeLayoutSource']['Version'] += 1;
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 记录日志
        $db = initDatabase();
        logAction($db, $name, 'Time layout updated');

        return json_encode(['status' => '时间表上传', 'name' => $name]);
    } else {
        return json_encode(['error' => '设备未找到']);
    }
}

// 更新科目接口
function updateSubjects($name, $file, $token) {
    if (empty($token) || empty($name) || empty($file)) {
        return json_encode(['error' => '参数缺失:token,name或file']);
    }
    $tokenData = checkToken($token);
    if (!$tokenData['valid']) {
        return json_encode(['error' => '无效的令牌']);
    }

    $dir = __DIR__ . "/data/$name";
    if (file_exists($dir)) {
        move_uploaded_file($file['tmp_name'], "$dir/Subjects.json");

        // 更新manifest.json
        $manifestPath = __DIR__ . '/manifest.json';
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $manifest['SubjectsSource']['Version'] += 1;
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 记录日志
        $db = initDatabase();
        logAction($db, $name, 'Subjects updated');

        return json_encode(['status' => '科目上传', 'name' => $name]);
    } else {
        return json_encode(['error' => '设备未找到']);
    }
}

// 更新默认设置接口
function updateDefaultSettings($name, $file, $token) {
    if (empty($token) || empty($name) || empty($file)) {
        return json_encode(['error' => '参数缺失:token,name或file']);
    }
    $tokenData = checkToken($token);
    if (!$tokenData['valid']) {
        return json_encode(['error' => 'Invalid token']);
    }

    $dir = __DIR__ . "/data/$name";
    if (file_exists($dir)) {
        move_uploaded_file($file['tmp_name'], "$dir/DefaultSettings.json");

        // 更新manifest.json
        $manifestPath = __DIR__ . '/manifest.json';
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $manifest['DefaultSettingsSource']['Version'] += 1;
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 记录日志
        $db = initDatabase();
        logAction($db, $name, 'Default settings updated');

        return json_encode(['status' => '默认设置上传', 'name' => $name]);
    } else {
        return json_encode(['error' => '设备未找到']);
    }
}

// 获取日志接口
function getLogs($name, $token) {
    if (empty($token)) {
        return json_encode(['error' => '参数缺失:token']);
    }
    $tokenData = checkToken($token);
    if (!$tokenData['valid'] || $tokenData['value'] > -1) {
        return json_encode(['error' => '无效的令牌或权限不足']);
    }
    $db = initDatabase();
    $stmt = $db->prepare('SELECT logs FROM devices WHERE id = :id');
    $stmt->bindValue(':id', $name, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        return json_encode(['status' => 'Logs retrieved', 'logs' => $row['logs']]);
    } else {
        return json_encode(['error' => 'Device not found']);
    }
}
// 更新策略接口
function updatePolicy($name, $policyParams, $token) {
    if (empty($token) || empty($name)) {
        return json_encode(['error' => 'Missing parameter: token or name']);
    }
    $tokenData = checkToken($token);
    if (!$tokenData['valid']) {
        return json_encode(['error' => 'Invalid token']);
    }

    $dir = __DIR__ . "/data/$name";
    if (file_exists($dir)) {
        $policy = [
            'DisableProfileClassPlanEditing' => $policyParams['disable_profile_class_plan_editing'] ?? false,
            'DisableProfileTimeLayoutEditing' => $policyParams['disable_profile_time_layout_editing'] ?? false,
            'DisableProfileSubjectsEditing' => $policyParams['disable_profile_subjects_editing'] ?? false,
            'DisableProfileEditing' => $policyParams['disable_profile_editing'] ?? false,
            'DisableSettingsEditing' => $policyParams['disable_settings_editing'] ?? false,
            'DisableSplashCustomize' => $policyParams['disable_splash_customize'] ?? false,
            'DisableDebugMenu' => $policyParams['disable_debug_menu'] ?? false,
            'AllowExitManagement' => $policyParams['allow_exit_management'] ?? false
        ];
        file_put_contents("$dir/policy.json", json_encode($policy, JSON_PRETTY_PRINT));

        // 记录日志
        $db = initDatabase();
        logAction($db, $name, 'Policy updated');

        return json_encode(['status' => 'Policy updated', 'name' => $name]);
    } else {
        return json_encode(['error' => 'Device not found']);
    }
}
//设备删除接口
function deleteDevice($name, $token) {
    if (empty($token) || empty($name)) {
        return json_encode(['error' => '参数缺失:token或name']);
    }
    $tokenData = checkToken($token);
    if (!$tokenData['valid'] || $tokenData['value'] > -1) {
        return json_encode(['error' => '无效的令牌或权限不足']);
    }

    $dir = __DIR__ . "/data/$name";
    if (file_exists($dir)) {
        array_map('unlink', glob("$dir/*.*"));
        rmdir($dir);

        // 记录日志
        $db = initDatabase();
        $stmt = $db->prepare('DELETE FROM devices WHERE id = :id');
        $stmt->bindValue(':id', $name, SQLITE3_TEXT);
        $stmt->execute();

        return json_encode(['status' => 'Device deleted', 'name' => $name]);
    } else {
        return json_encode(['error' => 'Device not found']);
    }
}
// 主逻辑
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['api'] ?? null;
    $token = $_GET['token'] ?? null;
    if(!$action||!$token){
        http_response_code(400);
        echo json_encode(['error' => 'no api or no token'], JSON_PRETTY_PRINT);
        exit;
    }
    switch ($action) {
        case 'register_new_device':
            $name = $_GET['name'] ?? null;
            if(!$name){
                http_response_code(400);
                echo json_encode(['error' => 'no device name'], JSON_PRETTY_PRINT);
                exit;
            }
            echo registerNewDevice($name, $token);
            break;
        case 'get_device_list':
            echo getDeviceList($token);
            break;
        case 'init':
            $url = $_GET['url'] ?? null;
            $organizationName = $_GET['organization_name'] ?? null;
            if(!$url||!$organizationName){
                http_response_code(400);
                echo json_encode(['error' => 'no URL or organization_name'], JSON_PRETTY_PRINT);
                exit;
            }
            echo initService($url, $organizationName, $token);
            break;
        case 'update_class_plan':
                $name = $_GET['name'] ?? null;
                $file = $_FILES['file'];
                if(!$name||!$file){
                    http_response_code(400);
                    echo json_encode(['error' => 'no device name or file'], JSON_PRETTY_PRINT);
                    exit;
                }
            echo updateClassPlan($name, $file, $token);
            break;
        case 'update_schedule':
                $name = $_GET['name'] ?? null;
                $file = $_FILES['file'];
                if(!$name||!$file){
                    http_response_code(400);
                    echo json_encode(['error' => 'no device name or file'], JSON_PRETTY_PRINT);
                    exit;
                }
            echo updateTimeLayout($name, $file, $token);
            break;
        case 'update_subject':
                $name = $_GET['name'] ?? null;
                $file = $_FILES['file'] ?? null;
                if(!$name||!$file){
                    http_response_code(400);
                    echo json_encode(['error' => 'no device name or file'], JSON_PRETTY_PRINT);
                    exit;
                }
            echo updateSubjects($name, $file, $token);
            break;
        case 'update_setting':
                $name = $_GET['name'] ?? null;
                $file = $_FILES['file'] ?? null;
                if(!$name||!$file){
                    http_response_code(400);
                    echo json_encode(['error' => 'no device name or file'], JSON_PRETTY_PRINT);
                    exit;
                }
            echo updateDefaultSettings($name, $file, $token);
            break;
        case 'get_log':
            $name = $_GET['name'] ?? null;
            if(!$name){
                http_response_code(400);
                echo json_encode(['error' => 'no device name'], JSON_PRETTY_PRINT);
                exit;
            }
            echo getLogs($name, $token);
            break;
        case 'update_policy':
            $name = $_GET['name'] ?? null;
            if(!$name){
                http_response_code(400);
                echo json_encode(['error' => 'no device name'], JSON_PRETTY_PRINT);
                exit;
            }
            $policyParams = [
                'disable_profile_class_plan_editing' => $_GET['disable_profile_class_plan_editing'] ?? false,
                'disable_profile_time_layout_editing' => $_GET['disable_profile_time_layout_editing'] ?? false,
                'disable_profile_subjects_editing' => $_GET['disable_profile_subjects_editing'] ?? false,
                'disable_profile_editing' => $_GET['disable_profile_editing'] ?? false,
                'disable_settings_editing' => $_GET['disable_settings_editing'] ?? false,
                'disable_splash_customize' => $_GET['disable_splash_customize'] ?? false,
                'disable_debug_menu' => $_GET['disable_debug_menu'] ?? false,
                'allow_exit_management' => $_GET['allow_exit_management'] ?? false
            ];
            echo updatePolicy($name, $policyParams, $token);
            break;
        case 'delete_device':
            $name = $_GET['name'] ?? null;
            if(!$name){
                http_response_code(400);
                echo json_encode(['error' => 'no device name'], JSON_PRETTY_PRINT);
                exit;
            }
            echo deleteDevice($name, $token);
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>
