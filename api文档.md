## API 文档

### 文档由AI生成,出现问题请提交issues,看到并核验后将修正

本文档描述了当前目录下的index.php代码所实现的 API 接口，包括每个接口的功能、请求方法、参数、返回值和错误处理。

### 全局配置(PHP中)

- `AUTH_URL`：用于验证令牌的授权地址。必须配置。
- `DB_PASSWORD`：数据库密码。可以留空，如果不填可能无法访问加密数据库。
- `DB_PATH`：数据库文件路径，默认为 `./data/devices.db`。

### 接口说明

#### 1. 注册新设备

- **接口**：`GET /api?api=register_new_device`
- **参数**：
  - `name` (string): 设备名称。
  - `token` (string): 访问令牌。
- **返回**：
  - 成功：`{"status": "设备注册成功", "name": "设备名称"}`
  - 失败：
    - `{"error": "缺少参数: token"}`
    - `{"error": "无效的令牌或权限不足"}`
    - `{"error": "缺少参数: name"}`
    - `{"error": "设备已被注册,请更换ID"}`
- **描述**：注册新的设备。如果设备名称不存在，则创建新的设备并记录日志；否则返回设备已被注册的错误信息。

#### 2. 获取设备列表

- **接口**：`GET /api?api=get_device_list`
- **参数**：
  - `token` (string): 访问令牌。
- **返回**：
  - 成功：`{"status": "设备列表", "devices": ["设备1", "设备2"]}`
  - 失败：
    - `{"error": "缺少参数: token"}`
    - `{"error": "无效的令牌"}`
- **描述**：获取所有已注册设备的列表。

#### 3. 初始化服务

- **接口**：`GET /api?api=init`
- **参数**：
  - `url` (string): 资源基础 URL。
  - `organization_name` (string): 组织名称。
  - `token` (string): 访问令牌。
- **返回**：
  - 成功：`{"status": "Service initialized"}`
  - 失败：
    - `{"error": "缺少参数 token 或 URL"}`
    - `{"error": "无效的令牌或权限不足"}`
- **描述**：初始化服务并创建或更新 `manifest.json` 文件。记录初始化操作的日志。

#### 4. 更新课程计划

- **接口**：`POST /api?api=update_class_plan`
- **参数**：
  - `name` (string): 设备名称。
  - `file` (file): 上传的课程计划文件 (JSON)。
  - `token` (string): 访问令牌。
- **返回**：
  - 成功：`{"status": "课程表上传", "name": "设备名称"}`
  - 失败：
    - `{"error": "参数缺失:token,name或file"}`
    - `{"error": "无效的令牌"}`
    - `{"error": "设备未找到"}`
- **描述**：更新指定设备的课程计划文件，并更新 `manifest.json` 文件中的版本号。记录更新日志。

#### 5. 更新时间表

- **接口**：`POST /api?api=update_schedule`
- **参数**：
  - `name` (string): 设备名称。
  - `file` (file): 上传的时间表文件 (JSON)。
  - `token` (string): 访问令牌。
- **返回**：
  - 成功：`{"status": "时间表上传", "name": "设备名称"}`
  - 失败：
    - `{"error": "参数缺失:token,name或file"}`
    - `{"error": "无效的令牌"}`
    - `{"error": "设备未找到"}`
- **描述**：更新指定设备的时间表文件，并更新 `manifest.json` 文件中的版本号。记录更新日志。

#### 6. 更新科目

- **接口**：`POST /api?api=update_subject`
- **参数**：
  - `name` (string): 设备名称。
  - `file` (file): 上传的科目文件 (JSON)。
  - `token` (string): 访问令牌。
- **返回**：
  - 成功：`{"status": "科目上传", "name": "设备名称"}`
  - 失败：
    - `{"error": "参数缺失:token,name或file"}`
    - `{"error": "无效的令牌"}`
    - `{"error": "设备未找到"}`
- **描述**：更新指定设备的科目文件，并更新 `manifest.json` 文件中的版本号。记录更新日志。

#### 7. 更新默认设置

- **接口**：`POST /api?api=update_setting`
- **参数**：
  - `name` (string): 设备名称。
  - `file` (file): 上传的默认设置文件 (JSON)。
  - `token` (string): 访问令牌。
- **返回**：
  - 成功：`{"status": "默认设置上传", "name": "设备名称"}`
  - 失败：
    - `{"error": "参数缺失:token,name或file"}`
    - `{"error": "无效的令牌"}`
    - `{"error": "设备未找到"}`
- **描述**：更新指定设备的默认设置文件，并更新 `manifest.json` 文件中的版本号。记录更新日志。

#### 8. 获取日志

- **接口**：`GET /api?api=get_log`
- **参数**：
  - `name` (string): 设备名称。
  - `token` (string): 访问令牌。
- **返回**：
  - 成功：`{"status": "Logs retrieved", "logs": "日志内容"}`
  - 失败：
    - `{"error": "参数缺失:token"}`
    - `{"error": "无效的令牌或权限不足"}`
    - `{"error": "设备未找到"}`
- **描述**：获取指定设备的日志记录。

#### 9. 更新策略

- **接口**：`GET /api?api=update_policy`
- **参数**：
  - `name` (string): 设备名称。
  - `policyParams` (object): 策略参数，如 `disable_profile_class_plan_editing` 等。
  - `token` (string): 访问令牌。
- **返回**：
  - 成功：`{"status": "Policy updated", "name": "设备名称"}`
  - 失败：
    - `{"error": "缺少参数: token 或 name"}`
    - `{"error": "无效的令牌"}`
    - `{"error": "设备未找到"}`
- **描述**：更新指定设备的策略文件并记录日志。

#### 10. 删除设备

- **接口**：`GET /api?api=delete_device`
- **参数**：
  - `name` (string): 设备名称。
  - `token` (string): 访问令牌。
- **返回**：
  - 成功：`{"status": "Device deleted", "name": "设备名称"}`
  - 失败：
    - `{"error": "参数缺失:token或name"}`
    - `{"error": "无效的令牌或权限不足"}`
    - `{"error": "设备未找到"}`
- **描述**：删除指定的设备及其所有文件，并从数据库中移除相关记录。记录删除日志。

### 错误处理

- **400 Bad Request**：请求参数缺失或无效。
- **401 Unauthorized**：令牌无效或权限不足。
- **404 Not Found**：请求的资源不存在（例如设备未找到）。
- **500 Internal Server Error**：服务器内部错误，通常是由于代码中的异常或数据库问题。
